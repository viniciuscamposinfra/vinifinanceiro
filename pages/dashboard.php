<?php

require "../config.php";


if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| FUNÇÕES
|--------------------------------------------------------------------------
*/

function e($texto): string
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, "UTF-8");
}

function valorBrasileiro($valor): string
{
    return "R$ " . number_format((float)$valor, 2, ",", ".");
}

function normalizarValor($valor): float
{
    $valor = trim((string)$valor);

    /* Aceita 1500, 1500.50, 1500,50 e 1.500,50. */
    if (strpos($valor, ",") !== false) {
        $valor = str_replace(".", "", $valor);
        $valor = str_replace(",", ".", $valor);
    }

    return (float)$valor;
}

function redirecionarMes(int $mes, int $ano): void
{
    header("Location: dashboard.php?mes=" . $mes . "&ano=" . $ano);
    exit;
}

/*
|--------------------------------------------------------------------------
| MÊS SELECIONADO
|--------------------------------------------------------------------------
*/

$mes = isset($_REQUEST["mes"]) ? (int)$_REQUEST["mes"] : (int)date("n");
$ano = isset($_REQUEST["ano"]) ? (int)$_REQUEST["ano"] : (int)date("Y");

if ($mes < 1 || $mes > 12) {
    $mes = (int)date("n");
}

if ($ano < 2000 || $ano > 2100) {
    $ano = (int)date("Y");
}

$meses = [
    1 => "Janeiro", 2 => "Fevereiro", 3 => "Março", 4 => "Abril",
    5 => "Maio", 6 => "Junho", 7 => "Julho", 8 => "Agosto",
    9 => "Setembro", 10 => "Outubro", 11 => "Novembro", 12 => "Dezembro"
];

/*
|--------------------------------------------------------------------------
| SALÁRIO DO MÊS
|--------------------------------------------------------------------------
*/

if (isset($_POST["salvar_salario"])) {
    $salario = normalizarValor($_POST["salario"] ?? "0");

    $consulta = $pdo->prepare("SELECT id FROM configuracoes WHERE mes = ? AND ano = ? LIMIT 1");
    $consulta->execute([$mes, $ano]);

    if ($consulta->fetchColumn()) {
        $pdo->prepare("UPDATE configuracoes SET salario = ? WHERE mes = ? AND ano = ?")
            ->execute([$salario, $mes, $ano]);
    } else {
        $pdo->prepare("INSERT INTO configuracoes (mes, ano, salario) VALUES (?, ?, ?)")
            ->execute([$mes, $ano, $salario]);
    }

    redirecionarMes($mes, $ano);
}

/*
|--------------------------------------------------------------------------
| STATUS DA FATURA
|--------------------------------------------------------------------------
*/

if (isset($_POST["alterar_fatura"])) {
    $formaId = (int)($_POST["forma_pagamento_id"] ?? 0);
    $novoStatus = (int)($_POST["novo_status"] ?? 0) === 1 ? 1 : 0;

    $verificarForma = $pdo->prepare("SELECT id FROM formas_pagamento WHERE id = ? LIMIT 1");
    $verificarForma->execute([$formaId]);

    if ($verificarForma->fetchColumn()) {
        if ($novoStatus) {
            $pdo->prepare(
                "INSERT INTO faturas (forma_pagamento_id, mes, ano, pago, data_pagamento)
                 VALUES (?, ?, ?, 1, CURDATE())
                 ON DUPLICATE KEY UPDATE pago = 1, data_pagamento = CURDATE()"
            )->execute([$formaId, $mes, $ano]);
        } else {
            $pdo->prepare(
                "INSERT INTO faturas (forma_pagamento_id, mes, ano, pago, data_pagamento)
                 VALUES (?, ?, ?, 0, NULL)
                 ON DUPLICATE KEY UPDATE pago = 0, data_pagamento = NULL"
            )->execute([$formaId, $mes, $ano]);
        }
    }

    redirecionarMes($mes, $ano);
}

/*
|--------------------------------------------------------------------------
| EXCLUIR COMPRA
|--------------------------------------------------------------------------
*/

if (isset($_GET["excluir"])) {
    $id = (int)$_GET["excluir"];
    $pdo->prepare("DELETE FROM compras WHERE id = ?")->execute([$id]);
    redirecionarMes($mes, $ano);
}

/*
|--------------------------------------------------------------------------
| CARREGAR COMPRA PARA EDIÇÃO
|--------------------------------------------------------------------------
*/

$editando = false;
$compraEditar = [
    "id" => "", "descricao" => "", "categoria" => "", "valor" => "",
    "data_compra" => date("Y-m-d"), "pessoa_id" => "", "forma_pagamento_id" => "",
    "parcela_atual" => null, "total_parcelas" => null
];

if (isset($_GET["editar"])) {
    $consulta = $pdo->prepare("SELECT * FROM compras WHERE id = ? LIMIT 1");
    $consulta->execute([(int)$_GET["editar"]]);
    $registro = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($registro) {
        $editando = true;
        $compraEditar = $registro;
    }
}

/*
|--------------------------------------------------------------------------
| SALVAR OU ATUALIZAR COMPRA
|--------------------------------------------------------------------------
*/

if (isset($_POST["salvar_compra"])) {
    $id = (int)($_POST["id"] ?? 0);
    $descricao = trim($_POST["descricao"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");
    $valorInformado = normalizarValor($_POST["valor"] ?? "0");
    $pessoaId = (int)($_POST["pessoa"] ?? 0);
    $formaId = (int)($_POST["forma"] ?? 0);
    $data = $_POST["data"] ?? date("Y-m-d");
    $totalParcelas = max(1, min(360, (int)($_POST["total_parcelas"] ?? 1)));
    $valorEhTotal = isset($_POST["valor_total"]);

    if ($descricao === "" || $categoria === "" || $valorInformado <= 0 || $pessoaId <= 0 || $formaId <= 0) {
        redirecionarMes($mes, $ano);
    }

    if ($id > 0) {
        $valorParcela = $valorInformado;
        if ($valorEhTotal && $totalParcelas > 1) {
            $valorParcela = round($valorInformado / $totalParcelas, 2);
        }

        $pdo->prepare(
            "UPDATE compras
             SET descricao = ?, categoria = ?, valor = ?, data_compra = ?, pessoa_id = ?, forma_pagamento_id = ?
             WHERE id = ?"
        )->execute([$descricao, $categoria, $valorParcela, $data, $pessoaId, $formaId, $id]);

        redirecionarMes($mes, $ano);
    }

    $valorParcela = $totalParcelas > 1 && $valorEhTotal
        ? round($valorInformado / $totalParcelas, 2)
        : $valorInformado;

    $dataInicial = new DateTime($data);

    try {
        $pdo->beginTransaction();

        $inserir = $pdo->prepare(
            "INSERT INTO compras
             (descricao, categoria, valor, data_compra, pessoa_id, forma_pagamento_id, pago, parcela_atual, total_parcelas, compra_principal)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)"
        );

        $compraPrincipal = null;

        for ($parcela = 1; $parcela <= $totalParcelas; $parcela++) {
            $dataParcela = clone $dataInicial;
            if ($parcela > 1) {
                $dataParcela->modify("+" . ($parcela - 1) . " month");
            }

            /* Ajusta apenas a última parcela para que a soma seja exatamente o valor total. */
            $valorDaParcela = $valorParcela;
            if ($valorEhTotal && $totalParcelas > 1 && $parcela === $totalParcelas) {
                $valorDaParcela = round($valorInformado - ($valorParcela * ($totalParcelas - 1)), 2);
            }

            $inserir->execute([
                $descricao,
                $categoria,
                $valorDaParcela,
                $dataParcela->format("Y-m-d"),
                $pessoaId,
                $formaId,
                $totalParcelas > 1 ? $parcela : null,
                $totalParcelas > 1 ? $totalParcelas : null,
                $compraPrincipal
            ]);

            if ($compraPrincipal === null) {
                $compraPrincipal = (int)$pdo->lastInsertId();
                if ($totalParcelas > 1) {
                    $pdo->prepare("UPDATE compras SET compra_principal = ? WHERE id = ?")
                        ->execute([$compraPrincipal, $compraPrincipal]);
                }
            }
        }

        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }

    redirecionarMes($mes, $ano);
}

/*
|--------------------------------------------------------------------------
| DADOS DO DASHBOARD
|--------------------------------------------------------------------------
*/

$consulta = $pdo->prepare("SELECT salario FROM configuracoes WHERE mes = ? AND ano = ? LIMIT 1");
$consulta->execute([$mes, $ano]);
$salario = (float)($consulta->fetchColumn() ?: 0);

$pessoas = $pdo->query("SELECT * FROM pessoas ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$formas = $pdo->query("SELECT * FROM formas_pagamento ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

if (strtolower($_SESSION["tipo"]) == "admin") {

    $consulta = $pdo->prepare("
        SELECT COALESCE(SUM(valor),0)
        FROM compras
        WHERE MONTH(data_compra)=?
        AND YEAR(data_compra)=?
    ");

    $consulta->execute([$mes, $ano]);

} else {

    $consulta = $pdo->prepare("
        SELECT COALESCE(SUM(valor),0)
        FROM compras
        WHERE pessoa_id=?
        AND MONTH(data_compra)=?
        AND YEAR(data_compra)=?
    ");

    $consulta->execute([
        $_SESSION["pessoa_id"],
        $mes,
        $ano
    ]);

}

$totalGasto = (float)$consulta->fetchColumn();
$saldo = $salario - $totalGasto;

if ($_SESSION["tipo"] == "admin") {

    $consulta = $pdo->prepare("
        SELECT
            c.*,
            p.nome AS pessoa,
            fp.nome AS forma
        FROM compras c
        INNER JOIN pessoas p ON p.id=c.pessoa_id
        INNER JOIN formas_pagamento fp ON fp.id=c.forma_pagamento_id
        WHERE MONTH(c.data_compra)=?
        AND YEAR(c.data_compra)=?
        ORDER BY c.data_compra DESC,c.id DESC
    ");

    $consulta->execute([$mes,$ano]);

} else {

    $consulta = $pdo->prepare("
        SELECT
            c.*,
            p.nome AS pessoa,
            fp.nome AS forma
        FROM compras c
        INNER JOIN pessoas p ON p.id=c.pessoa_id
        INNER JOIN formas_pagamento fp ON fp.id=c.forma_pagamento_id
        WHERE c.pessoa_id=?
        AND MONTH(c.data_compra)=?
        AND YEAR(c.data_compra)=?
        ORDER BY c.data_compra DESC,c.id DESC
    ");

    $consulta->execute([
        $_SESSION["pessoa_id"],
        $mes,
        $ano
    ]);

}

$compras = $consulta->fetchAll(PDO::FETCH_ASSOC);

$comprasPorForma = [];
foreach ($formas as $forma) {
    $comprasPorForma[(int)$forma["id"]] = [];
}
foreach ($compras as $compra) {
    $comprasPorForma[(int)$compra["forma_pagamento_id"]][] = $compra;
}

$consulta = $pdo->prepare("SELECT forma_pagamento_id, pago, data_pagamento FROM faturas WHERE mes = ? AND ano = ?");
$consulta->execute([$mes, $ano]);
$faturas = [];
foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fatura) {
    $faturas[(int)$fatura["forma_pagamento_id"]] = $fatura;
}

$totalFaturasPagas = 0;
foreach ($formas as $forma) {
    $formaId = (int)$forma["id"];
    $totalDaForma = array_sum(array_column($comprasPorForma[$formaId], "valor"));
    if (!empty($faturas[$formaId]["pago"])) {
        $totalFaturasPagas += $totalDaForma;
    }
}

?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Financeiro Campos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --ink: #172033; --muted: #667085; --surface: #ffffff; --page: #f4f7fb; }
        body { background: var(--page); color: var(--ink); }
        .app-card { border: 0; border-radius: 18px; box-shadow: 0 8px 24px rgba(16, 24, 40, .07); }
        .summary-card { min-height: 132px; }
        .summary-label { color: var(--muted); font-size: .9rem; font-weight: 600; }
        .summary-value { font-size: 1.6rem; font-weight: 800; letter-spacing: -.03em; }
        .invoice-card { overflow: hidden; }
        .invoice-header { background: linear-gradient(135deg, #172033, #27364f); color: white; }
        .invoice-total { font-size: 1.45rem; font-weight: 800; }
        .purchase-row:last-child { border-bottom: 0 !important; }
        .purchase-description { font-weight: 700; }
        .installment { font-size: .78rem; }
        .empty-state { color: var(--muted); }
        @media (max-width: 767px) { .invoice-header .text-end { text-align: left !important; margin-top: 1rem; } }
    </style>
</head>
<body>
<main class="container py-4 py-md-5">
    <header class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1">💰 Financeiro Campos</h1>
            <p class="text-muted mb-0">Olá, <strong><?= e($_SESSION["nome"] ?? "") ?></strong>. Controle de <?= e($meses[$mes]) ?> de <?= e($ano) ?>.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <form method="get" class="d-flex gap-2">
                <select name="mes" class="form-select" onchange="this.form.submit()" aria-label="Mês">
                    <?php foreach ($meses as $numero => $nome): ?>
                        <option value="<?= $numero ?>" <?= $numero === $mes ? "selected" : "" ?>><?= e($nome) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="ano" value="<?= $ano ?>" min="2000" max="2100" class="form-control" style="width: 100px" onchange="this.form.submit()" aria-label="Ano">
            </form>
            <a href="../logout.php" class="btn btn-outline-danger">Sair</a>
        </div>
    </header>

    <?php if (strtolower($_SESSION["tipo"]) == "admin"): ?>
    <section class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><div class="card app-card summary-card"><div class="card-body"><div class="summary-label">💵 Salário</div><div class="summary-value text-success mt-2"><?= valorBrasileiro($salario) ?></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card app-card summary-card"><div class="card-body"><div class="summary-label">💸 Total gasto</div><div class="summary-value text-danger mt-2"><?= valorBrasileiro($totalGasto) ?></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card app-card summary-card"><div class="card-body"><div class="summary-label">✅ Faturas pagas</div><div class="summary-value text-primary mt-2"><?= valorBrasileiro($totalFaturasPagas) ?></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card app-card summary-card"><div class="card-body"><div class="summary-label">💰 Saldo</div><div class="summary-value <?= $saldo >= 0 ? "text-success" : "text-danger" ?> mt-2"><?= valorBrasileiro($saldo) ?></div></div></div></div>
    </section>

    <section class="card app-card mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h5 mb-0">Salário do mês</h2>
                <span class="text-muted small"><?= e($meses[$mes]) ?>/<?= $ano ?></span>
            </div>
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="mes" value="<?= $mes ?>"><input type="hidden" name="ano" value="<?= $ano ?>">
                <div class="col-sm-4 col-md-3"><label class="form-label">Valor</label><input type="number" step="0.01" min="0" name="salario" value="<?= e(number_format($salario, 2, ".", "")) ?>" class="form-control" required></div>
                <div class="col-sm-auto"><button name="salvar_salario" class="btn btn-success">Salvar salário</button></div>
            </form>
        </div>
    </section>

    <section class="card app-card mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0"><?= $editando ? "✏️ Editar compra" : "🛒 Nova compra" ?></h2>
                <?php if ($editando): ?><a href="dashboard.php?mes=<?= $mes ?>&ano=<?= $ano ?>" class="btn btn-sm btn-outline-secondary">Cancelar edição</a><?php endif; ?>
            </div>
            <form method="post" id="form-compra" class="row g-3">
                <input type="hidden" name="mes" value="<?= $mes ?>"><input type="hidden" name="ano" value="<?= $ano ?>"><input type="hidden" name="id" value="<?= e($compraEditar["id"]) ?>">
                <div class="col-md-4"><label class="form-label">Descrição</label><input type="text" name="descricao" value="<?= e($compraEditar["descricao"]) ?>" class="form-control" required></div>
                <div class="col-md-2"><label class="form-label">Categoria</label><select name="categoria" class="form-select" required><option value="">Selecione</option><?php foreach ($categorias as $categoria): ?><option value="<?= e($categoria["nome"]) ?>" <?= $categoria["nome"] === $compraEditar["categoria"] ? "selected" : "" ?>><?= e($categoria["nome"]) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Pessoa</label><select name="pessoa" class="form-select" required><option value="">Selecione</option><?php foreach ($pessoas as $pessoa): ?><option value="<?= $pessoa["id"] ?>" <?= (string)$pessoa["id"] === (string)$compraEditar["pessoa_id"] ? "selected" : "" ?>><?= e($pessoa["nome"]) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Forma</label><select name="forma" class="form-select" required><option value="">Selecione</option><?php foreach ($formas as $forma): ?><option value="<?= $forma["id"] ?>" <?= (string)$forma["id"] === (string)$compraEditar["forma_pagamento_id"] ? "selected" : "" ?>><?= e($forma["nome"]) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Data</label><input type="date" name="data" value="<?= e($compraEditar["data_compra"]) ?>" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label">Valor <span id="rotulo-valor">da compra</span></label><input type="number" step="0.01" min="0.01" name="valor" value="<?= $editando ? e(number_format((float)$compraEditar["valor"], 2, ".", "")) : "" ?>" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label">Parcelas</label><input type="number" min="1" max="360" name="total_parcelas" id="total-parcelas" value="<?= $editando ? e($compraEditar["total_parcelas"] ?: 1) : 1 ?>" class="form-control" <?= $editando ? "readonly" : "" ?>></div>
                <?php if (!$editando): ?>
                    <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="valor_total" id="valor-total" checked><label class="form-check-label" for="valor-total">O valor é o total da compra</label></div></div>
                <?php else: ?>
                    <div class="col-md-3 d-flex align-items-end"><div class="small text-muted mb-2">A edição altera apenas esta parcela.</div></div>
                <?php endif; ?>
                <div class="col-md-3 d-flex align-items-end"><button type="submit" name="salvar_compra" class="btn btn-primary w-100"><?= $editando ? "Atualizar compra" : "Salvar compra" ?></button></div>
            </form>
        </div>
    </section>
<?php endif; ?>
    <section>
        <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h4 mb-0">💳 Compras por forma de pagamento</h2><span class="text-muted small"><?= count($compras) ?> lançamento(s)</span></div>
        <div class="row g-4">
            <?php foreach ($formas as $forma): ?>
                <?php
                    $formaId = (int)$forma["id"];
                    $lista = $comprasPorForma[$formaId];
                    $totalDaForma = array_sum(array_column($lista, "valor"));
                    $fatura = $faturas[$formaId] ?? null;
                    $faturaPaga = !empty($fatura["pago"]);
                ?>
                <div class="col-12 col-xl-6">
                    <article class="card app-card invoice-card h-100">
                        <div class="invoice-header p-4 d-flex flex-column flex-md-row justify-content-between">
                            <div><h3 class="h5 mb-1">💳 <?= e($forma["nome"]) ?></h3><span class="opacity-75 small"><?= count($lista) ?> compra(s) no mês</span></div>
                            <div class="text-md-end"><div class="invoice-total"><?= valorBrasileiro($totalDaForma) ?></div><div class="small opacity-75">Total da fatura</div></div>
                        </div>
                        <div class="card-body p-0">
                            <div class="p-3 border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                                <?php if ($faturaPaga): ?>
                                    <div><span class="badge text-bg-success">✓ Pago</span><span class="small text-muted ms-1">em <?= date("d/m/Y", strtotime($fatura["data_pagamento"])) ?></span></div>
                                    <form method="post"><input type="hidden" name="mes" value="<?= $mes ?>"><input type="hidden" name="ano" value="<?= $ano ?>"><input type="hidden" name="forma_pagamento_id" value="<?= $formaId ?>"><input type="hidden" name="novo_status" value="0"><button name="alterar_fatura" class="btn btn-sm btn-outline-secondary">Desfazer pagamento</button></form>
                                <?php else: ?>
                                    <span class="badge text-bg-warning">● Não pago</span>
                                    <form method="post"><input type="hidden" name="mes" value="<?= $mes ?>"><input type="hidden" name="ano" value="<?= $ano ?>"><input type="hidden" name="forma_pagamento_id" value="<?= $formaId ?>"><input type="hidden" name="novo_status" value="1"><button name="alterar_fatura" class="btn btn-sm btn-success" <?= !$lista ? "disabled" : "" ?>>Marcar fatura como paga</button></form>
                                <?php endif; ?>
                            </div>
                            <?php if (!$lista): ?>
                                <p class="empty-state text-center mb-0 p-4">Nenhuma compra nesta forma de pagamento.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($lista as $compra): ?>
                                        <div class="list-group-item purchase-row px-3 py-3">
                                            <div class="d-flex gap-3 justify-content-between align-items-start">
                                                <div><div class="purchase-description"><?= e($compra["descricao"]) ?><?php if (!empty($compra["parcela_atual"]) && !empty($compra["total_parcelas"])): ?> <span class="badge text-bg-light border installment"><?= (int)$compra["parcela_atual"] ?>/<?= (int)$compra["total_parcelas"] ?></span><?php endif; ?></div><div class="small text-muted"><?= e($compra["categoria"]) ?> · <?= e($compra["pessoa"]) ?> · <?= date("d/m/Y", strtotime($compra["data_compra"])) ?></div></div>
                                                <div class="text-end flex-shrink-0"><strong><?= valorBrasileiro($compra["valor"]) ?></strong><?php if(strtolower($_SESSION["tipo"]) == "admin"): ?><div class="mt-2"><a href="dashboard.php?mes=<?= $mes ?>&ano=<?= $ano ?>&editar=<?= $compra["id"] ?>" class="btn btn-sm btn-outline-primary" title="Editar">✏️</a> <a href="dashboard.php?mes=<?= $mes ?>&ano=<?= $ano ?>&excluir=<?= $compra["id"] ?>" class="btn btn-sm btn-outline-danger" title="Excluir" onclick="return confirm('Deseja excluir esta <?= !empty($compra["total_parcelas"]) ? "parcela" : "compra" ?>?')">🗑️</a></div><?php endif; ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<script>
    const parcelas = document.getElementById('total-parcelas');
    const valorTotal = document.getElementById('valor-total');
    const rotuloValor = document.getElementById('rotulo-valor');
    function atualizarRotuloValor() {
        if (!parcelas || !rotuloValor) return;
        const parcelado = Number(parcelas.value) > 1;
        rotuloValor.textContent = parcelado && valorTotal && valorTotal.checked ? 'total da compra' : parcelado ? 'de cada parcela' : 'da compra';
    }
    if (parcelas) parcelas.addEventListener('input', atualizarRotuloValor);
    if (valorTotal) valorTotal.addEventListener('change', atualizarRotuloValor);
    atualizarRotuloValor();
</script>
</body>
</html>
