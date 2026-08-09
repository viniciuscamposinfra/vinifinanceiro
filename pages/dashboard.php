<?php

require "../config.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit;
}

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

function valorBrasileiro($valor): string
{
    return "R$ " . number_format((float)$valor, 2, ",", ".");
}

function redirecionarMes(int $mes, int $ano): void
{
    header("Location: dashboard.php?mes=" . $mes . "&ano=" . $ano);
    exit;
}

function normalizarValor($valor): float
{
    $valor = trim((string)$valor);

    if (strpos($valor, ",") !== false) {
        $valor = str_replace(".", "", $valor);
        $valor = str_replace(",", ".", $valor);
    }

    return (float)$valor;
}

function colunaExiste(PDO $pdo, string $tabela, string $coluna): bool
{
    try {
        $consulta = $pdo->prepare("SHOW COLUMNS FROM `$tabela` LIKE ?");
        $consulta->execute([$coluna]);
        return (bool)$consulta->fetch();
    } catch (Throwable $erro) {
        return false;
    }
}

$mes = isset($_REQUEST["mes"]) ? (int)$_REQUEST["mes"] : (int)date("n");
$ano = isset($_REQUEST["ano"]) ? (int)$_REQUEST["ano"] : (int)date("Y");
$mes = ($mes >= 1 && $mes <= 12) ? $mes : (int)date("n");
$ano = ($ano >= 2000 && $ano <= 2100) ? $ano : (int)date("Y");

$meses = [
    1 => "Janeiro", 2 => "Fevereiro", 3 => "Março", 4 => "Abril",
    5 => "Maio", 6 => "Junho", 7 => "Julho", 8 => "Agosto",
    9 => "Setembro", 10 => "Outubro", 11 => "Novembro", 12 => "Dezembro"
];

$tipo = strtolower((string)($_SESSION["tipo"] ?? ""));
$ehAdmin = in_array($tipo, ["admin", "administrador"], true);

/* O login pode guardar o vínculo diretamente na sessão ou apenas o id do
   usuário. Os dois formatos são aceitos para manter as permissões antigas. */
$pessoaId = (int)($_SESSION["pessoa_id"] ?? $_SESSION["id_pessoa"] ?? $_SESSION["pessoa"] ?? 0);
if (!$ehAdmin && $pessoaId <= 0) {
    $colunaVinculo = colunaExiste($pdo, "usuarios", "pessoa_id") ? "pessoa_id"
        : (colunaExiste($pdo, "usuarios", "id_pessoa") ? "id_pessoa" : null);

    if ($colunaVinculo !== null) {
        $consulta = $pdo->prepare("SELECT `$colunaVinculo` FROM usuarios WHERE id = ? LIMIT 1");
        $consulta->execute([(int)$_SESSION["id"]]);
        $pessoaId = (int)($consulta->fetchColumn() ?: 0);
    }
}

/* Usuário comum nunca pode consultar nem alterar dados de outra pessoa. */
if (!$ehAdmin && $pessoaId <= 0) {
    http_response_code(403);
    exit("Seu usuário não está vinculado a uma pessoa. Peça ao administrador para concluir esse vínculo.");
}

/* Retorna o filtro de pessoa aplicado às consultas de compras. */
$filtroPessoa = !$ehAdmin ? " AND c.pessoa_id = ?" : "";
$parametrosPessoa = !$ehAdmin ? [$pessoaId] : [];

/* Algumas instalações antigas possuem salário geral; instalações novas podem
   associá-lo à pessoa. Detectamos isso sem exigir alteração de banco. */
$configuracoesTemPessoaId = colunaExiste($pdo, "configuracoes", "pessoa_id");
$temSolicitacoesAjuste = colunaExiste($pdo, "solicitacoes_ajuste", "id");
$temReservasMensais = colunaExiste($pdo, "reservas_mensais", "id");
$reservasTemDiasRestantes = colunaExiste($pdo, "reservas_mensais", "dias_fim_semana");

/* SALÁRIO --------------------------------------------------------------- */
if ($ehAdmin && !$configuracoesTemPessoaId && isset($_POST["salvar_salario"])) {
    $salarioNovo = normalizarValor($_POST["salario"] ?? 0);
    $consulta = $pdo->prepare("SELECT id FROM configuracoes WHERE mes = ? AND ano = ? LIMIT 1");
    $consulta->execute([$mes, $ano]);

    if ($consulta->fetchColumn()) {
        $pdo->prepare("UPDATE configuracoes SET salario = ? WHERE mes = ? AND ano = ?")
            ->execute([$salarioNovo, $mes, $ano]);
    } else {
        $pdo->prepare("INSERT INTO configuracoes (mes, ano, salario) VALUES (?, ?, ?)")
            ->execute([$mes, $ano, $salarioNovo]);
    }

    redirecionarMes($mes, $ano);
}

/* GUARDAR / INVESTIR ---------------------------------------------------- */
if ($ehAdmin && $temReservasMensais && isset($_POST["salvar_reserva"])) {
    $valorGuardar = normalizarValor($_POST["valor_guardar"] ?? 0);
    $diasFimDeSemana = max(0, min(8, (int)($_POST["dias_fim_semana"] ?? 8)));
    $consulta = $pdo->prepare("SELECT id FROM reservas_mensais WHERE mes = ? AND ano = ? LIMIT 1");
    $consulta->execute([$mes, $ano]);

    if ($consulta->fetchColumn()) {
        if ($reservasTemDiasRestantes) {
            $pdo->prepare("UPDATE reservas_mensais SET valor = ?, dias_fim_semana = ? WHERE mes = ? AND ano = ?")
                ->execute([$valorGuardar, $diasFimDeSemana, $mes, $ano]);
        } else {
            $pdo->prepare("UPDATE reservas_mensais SET valor = ? WHERE mes = ? AND ano = ?")
                ->execute([$valorGuardar, $mes, $ano]);
        }
    } else {
        if ($reservasTemDiasRestantes) {
            $pdo->prepare("INSERT INTO reservas_mensais (mes, ano, valor, dias_fim_semana) VALUES (?, ?, ?, ?)")
                ->execute([$mes, $ano, $valorGuardar, $diasFimDeSemana]);
        } else {
            $pdo->prepare("INSERT INTO reservas_mensais (mes, ano, valor) VALUES (?, ?, ?)")
                ->execute([$mes, $ano, $valorGuardar]);
        }
    }

    redirecionarMes($mes, $ano);
}

/* FATURA --------------------------------------------------------------- */
if ($ehAdmin && isset($_POST["alterar_fatura"])) {
    $formaId = (int)($_POST["forma_pagamento_id"] ?? 0);
    $pago = (int)($_POST["novo_status"] ?? 0) === 1 ? 1 : 0;

    if ($formaId > 0) {
        $pdo->prepare(
            "INSERT INTO faturas (forma_pagamento_id, mes, ano, pago, data_pagamento)
             VALUES (?, ?, ?, ?, CASE WHEN ? = 1 THEN CURDATE() ELSE NULL END)
             ON DUPLICATE KEY UPDATE pago = VALUES(pago), data_pagamento = VALUES(data_pagamento)"
        )->execute([$formaId, $mes, $ano, $pago, $pago]);
    }

    redirecionarMes($mes, $ano);
}

/* SOLICITAÇÕES DE AJUSTE DE VALOR -------------------------------------- */
if (!$ehAdmin && $temSolicitacoesAjuste && isset($_POST["solicitar_ajuste"])) {
    $compraId = (int)($_POST["compra_id"] ?? 0);
    $valorSolicitado = normalizarValor($_POST["valor_solicitado"] ?? 0);
    $observacao = trim($_POST["observacao"] ?? "");

    $consulta = $pdo->prepare("SELECT id, valor FROM compras WHERE id = ? AND pessoa_id = ? LIMIT 1");
    $consulta->execute([$compraId, $pessoaId]);
    $compra = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($compra && $valorSolicitado > 0) {
        $pendente = $pdo->prepare("SELECT id FROM solicitacoes_ajuste WHERE compra_id = ? AND status = 'pendente' LIMIT 1");
        $pendente->execute([$compraId]);

        if (!$pendente->fetchColumn()) {
            $pdo->prepare(
                "INSERT INTO solicitacoes_ajuste (compra_id, pessoa_id, valor_atual, valor_solicitado, observacao)
                 VALUES (?, ?, ?, ?, ?)"
            )->execute([$compraId, $pessoaId, $compra["valor"], $valorSolicitado, $observacao]);
        }
    }

    redirecionarMes($mes, $ano);
}

if ($ehAdmin && $temSolicitacoesAjuste && isset($_POST["analisar_ajuste"])) {
    $solicitacaoId = (int)($_POST["solicitacao_id"] ?? 0);
    $decisao = $_POST["decisao"] ?? "";

    if (in_array($decisao, ["aprovada", "recusada"], true)) {
        $pdo->beginTransaction();
        try {
            $consulta = $pdo->prepare("SELECT * FROM solicitacoes_ajuste WHERE id = ? AND status = 'pendente' FOR UPDATE");
            $consulta->execute([$solicitacaoId]);
            $solicitacao = $consulta->fetch(PDO::FETCH_ASSOC);

            if ($solicitacao) {
                if ($decisao === "aprovada") {
                    $pdo->prepare("UPDATE compras SET valor = ? WHERE id = ?")
                        ->execute([$solicitacao["valor_solicitado"], $solicitacao["compra_id"]]);
                }

                $pdo->prepare("UPDATE solicitacoes_ajuste SET status = ?, analisado_em = NOW(), analisado_por = ? WHERE id = ?")
                    ->execute([$decisao, (int)$_SESSION["id"], $solicitacaoId]);
            }

            $pdo->commit();
        } catch (Throwable $erro) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $erro;
        }
    }

    redirecionarMes($mes, $ano);
}

/* EXCLUIR -------------------------------------------------------------- */
if ($ehAdmin && isset($_GET["excluir"])) {
    $id = (int)$_GET["excluir"];
    $sql = "DELETE FROM compras WHERE id = ?" . (!$ehAdmin ? " AND pessoa_id = ?" : "");
    $consulta = $pdo->prepare($sql);
    $consulta->execute($ehAdmin ? [$id] : [$id, $pessoaId]);
    redirecionarMes($mes, $ano);
}

/* SALVAR COMPRA -------------------------------------------------------- */
if ($ehAdmin && isset($_POST["salvar_compra"])) {
    $id = (int)($_POST["id"] ?? 0);
    $descricao = trim($_POST["descricao"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");
    $valor = normalizarValor($_POST["valor"] ?? 0);
    $data = !empty($_POST["data"]) ? $_POST["data"] : date("Y-m-d");
    $formaId = (int)($_POST["forma"] ?? 0);
    $pessoaCompraId = $ehAdmin ? (int)($_POST["pessoa"] ?? 0) : $pessoaId;
    $contaCompartilhada = !$id && isset($_POST["conta_compartilhada"]);
    $pessoaCompartilhadaId = $contaCompartilhada ? (int)($_POST["pessoa_compartilhada"] ?? 0) : 0;

    if ($descricao !== "" && $valor > 0 && $formaId > 0 && $pessoaCompraId > 0 && (!$contaCompartilhada || ($pessoaCompartilhadaId > 0 && $pessoaCompartilhadaId !== $pessoaCompraId))) {
        if ($id > 0) {
            $sql = "UPDATE compras SET descricao = ?, categoria = ?, valor = ?, data_compra = ?, forma_pagamento_id = ?";
            $parametros = [$descricao, $categoria, $valor, $data, $formaId];

            if ($ehAdmin) {
                $sql .= ", pessoa_id = ? WHERE id = ?";
                $parametros[] = $pessoaCompraId;
                $parametros[] = $id;
            } else {
                $sql .= " WHERE id = ? AND pessoa_id = ?";
                $parametros[] = $id;
                $parametros[] = $pessoaId;
            }

            $pdo->prepare($sql)->execute($parametros);
        } else {
            $parcelas = max(1, min(360, (int)($_POST["total_parcelas"] ?? 1)));
            $valorTotal = isset($_POST["valor_total"]);
            $valorParcela = ($parcelas > 1 && $valorTotal) ? round($valor / $parcelas, 2) : $valor;
            $dataInicial = new DateTime($data);
            $pdo->beginTransaction();

            try {
                $inserir = $pdo->prepare(
                    "INSERT INTO compras
                    (descricao, categoria, valor, data_compra, pessoa_id, forma_pagamento_id, pago, parcela_atual, total_parcelas, compra_principal)
                    VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)"
                );
                $pessoasDestino = [$pessoaCompraId];
                if ($contaCompartilhada) {
                    $pessoasDestino[] = $pessoaCompartilhadaId;
                }
                $principais = [];

                for ($parcela = 1; $parcela <= $parcelas; $parcela++) {
                    $dataParcela = clone $dataInicial;
                    $dataParcela->modify("+" . ($parcela - 1) . " month");
                    $valorDaParcela = ($valorTotal && $parcelas > 1 && $parcela === $parcelas)
                        ? round($valor - ($valorParcela * ($parcelas - 1)), 2)
                        : $valorParcela;

                    foreach ($pessoasDestino as $pessoaDestinoId) {
                        $inserir->execute([
                            $descricao, $categoria, $valorDaParcela, $dataParcela->format("Y-m-d"),
                            $pessoaDestinoId, $formaId,
                            $parcelas > 1 ? $parcela : null,
                            $parcelas > 1 ? $parcelas : null,
                            $principais[$pessoaDestinoId] ?? null
                        ]);

                        if (!isset($principais[$pessoaDestinoId])) {
                            $principais[$pessoaDestinoId] = (int)$pdo->lastInsertId();
                            if ($parcelas > 1) {
                                $pdo->prepare("UPDATE compras SET compra_principal = ? WHERE id = ?")
                                    ->execute([$principais[$pessoaDestinoId], $principais[$pessoaDestinoId]]);
                            }
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
        }
    }

    redirecionarMes($mes, $ano);
}

/* DADOS ---------------------------------------------------------------- */
if ($ehAdmin && $configuracoesTemPessoaId) {
    $consulta = $pdo->prepare("SELECT COALESCE(SUM(salario), 0) FROM configuracoes WHERE mes = ? AND ano = ?");
    $consulta->execute([$mes, $ano]);
} elseif (!$ehAdmin && $configuracoesTemPessoaId) {
    $consulta = $pdo->prepare("SELECT salario FROM configuracoes WHERE mes = ? AND ano = ? AND pessoa_id = ? LIMIT 1");
    $consulta->execute([$mes, $ano, $pessoaId]);
} else {
    $consulta = $pdo->prepare("SELECT salario FROM configuracoes WHERE mes = ? AND ano = ? LIMIT 1");
    $consulta->execute([$mes, $ano]);
}
/* Nunca mostramos o salário geral a um usuário comum quando o banco ainda
   não possui salário por pessoa. */
$salario = ($ehAdmin || $configuracoesTemPessoaId) ? (float)($consulta->fetchColumn() ?: 0) : 0;

$valorGuardar = 0;
$diasFimDeSemana = 8;
if ($ehAdmin && $temReservasMensais) {
    $camposReserva = $reservasTemDiasRestantes ? "valor, dias_fim_semana" : "valor";
    $consulta = $pdo->prepare("SELECT $camposReserva FROM reservas_mensais WHERE mes = ? AND ano = ? LIMIT 1");
    $consulta->execute([$mes, $ano]);
    $reserva = $consulta->fetch(PDO::FETCH_ASSOC);
    if ($reserva) {
        $valorGuardar = (float)$reserva["valor"];
        if ($reservasTemDiasRestantes) {
            $diasFimDeSemana = max(0, (int)$reserva["dias_fim_semana"]);
        }
    }
}

$pessoas = $pdo->query("SELECT * FROM pessoas ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$formas = $pdo->query("SELECT * FROM formas_pagamento ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$consulta = $pdo->prepare(
    "SELECT COALESCE(SUM(c.valor), 0) FROM compras c
     WHERE MONTH(c.data_compra) = ? AND YEAR(c.data_compra) = ?" . $filtroPessoa
);
$consulta->execute(array_merge([$mes, $ano], $parametrosPessoa));
$totalGasto = (float)$consulta->fetchColumn();
$saldo = $salario - $totalGasto - $valorGuardar;
$mediaFimDeSemana = $diasFimDeSemana > 0 ? $saldo / $diasFimDeSemana : 0;

$consulta = $pdo->prepare(
    "SELECT c.*, p.nome AS pessoa, fp.nome AS forma
     FROM compras c
     INNER JOIN pessoas p ON p.id = c.pessoa_id
     INNER JOIN formas_pagamento fp ON fp.id = c.forma_pagamento_id
     WHERE MONTH(c.data_compra) = ? AND YEAR(c.data_compra) = ?" . $filtroPessoa .
    " ORDER BY c.data_compra DESC, c.id DESC"
);
$consulta->execute(array_merge([$mes, $ano], $parametrosPessoa));
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
    if (!empty($faturas[$formaId]["pago"])) {
        $totalFaturasPagas += array_sum(array_column($comprasPorForma[$formaId], "valor"));
    }
}

$solicitacoesPendentes = [];
$comprasComSolicitacaoPendente = [];
if ($temSolicitacoesAjuste) {
    if ($ehAdmin) {
        $solicitacoesPendentes = $pdo->query(
            "SELECT s.*, c.descricao, p.nome AS pessoa
             FROM solicitacoes_ajuste s
             INNER JOIN compras c ON c.id = s.compra_id
             INNER JOIN pessoas p ON p.id = s.pessoa_id
             WHERE s.status = 'pendente'
             ORDER BY s.solicitado_em ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $consulta = $pdo->prepare("SELECT compra_id FROM solicitacoes_ajuste WHERE pessoa_id = ? AND status = 'pendente'");
        $consulta->execute([$pessoaId]);
        $comprasComSolicitacaoPendente = array_flip(array_map("intval", $consulta->fetchAll(PDO::FETCH_COLUMN)));
    }
}

$editando = false;
$compraEditar = ["id" => "", "descricao" => "", "categoria" => "", "valor" => "", "data_compra" => date("Y-m-d"), "pessoa_id" => "", "forma_pagamento_id" => "", "total_parcelas" => 1];
if (isset($_GET["editar"])) {
    $sql = "SELECT * FROM compras WHERE id = ?" . (!$ehAdmin ? " AND pessoa_id = ?" : "") . " LIMIT 1";
    $consulta = $pdo->prepare($sql);
    $consulta->execute($ehAdmin ? [(int)$_GET["editar"]] : [(int)$_GET["editar"], $pessoaId]);
    $registro = $consulta->fetch(PDO::FETCH_ASSOC);
    if ($registro) {
        $editando = true;
        $compraEditar = $registro;
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
        :root { --ink:#172033; --muted:#667085; --page:#f4f7fb; }
        body { background:var(--page); color:var(--ink); }
        .app-card { border:0; border-radius:18px; box-shadow:0 8px 24px rgba(16,24,40,.07); }
        .summary-card { min-height:132px; }
        .summary-label { color:var(--muted); font-size:.9rem; font-weight:600; }
        .summary-value { font-size:1.6rem; font-weight:800; letter-spacing:-.03em; }
        .invoice-header { background:linear-gradient(135deg,#172033,#27364f); color:#fff; }
        .invoice-total { font-size:1.45rem; font-weight:800; }
        .valor-financeiro { white-space:nowrap; }
    </style>
</head>
<body>
<main class="container py-4 py-md-5">
    <header class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1">💰 Financeiro Campos</h1>
            <p class="text-muted mb-0">Olá, <strong><?= e($_SESSION["nome"] ?? "") ?></strong>. Controle de <?= e($meses[$mes]) ?> de <?= $ano ?>.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <form method="get" class="d-flex gap-2">
                <select name="mes" class="form-select" onchange="this.form.submit()" aria-label="Mês">
                    <?php foreach ($meses as $numero => $nome): ?>
                        <option value="<?= $numero ?>" <?= $numero === $mes ? "selected" : "" ?>><?= e($nome) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="ano" value="<?= $ano ?>" min="2000" max="2100" class="form-control" style="width:100px" onchange="this.form.submit()" aria-label="Ano">
            </form>
            <?php if ($ehAdmin): ?><a href="usuarios.php" class="btn btn-outline-primary">👥 Usuários</a><?php endif; ?>
            <button id="btnOcultar" type="button" class="btn btn-outline-secondary" onclick="alternarValores()" title="Ocultar valores" aria-label="Ocultar valores">👁️</button>
            <a href="../logout.php" class="btn btn-outline-danger">🚪 Sair</a>
        </div>
    </header>

    <?php if ($ehAdmin): ?>
    <section class="card app-card mb-4"><div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0"><?= $editando ? "✏️ Editar compra" : "🛒 Nova compra" ?></h2><?php if ($editando): ?><a href="dashboard.php?mes=<?= $mes ?>&ano=<?= $ano ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a><?php endif; ?></div>
        <form method="post" class="row g-3"><input type="hidden" name="mes" value="<?= $mes ?>"><input type="hidden" name="ano" value="<?= $ano ?>"><input type="hidden" name="id" value="<?= e($compraEditar["id"]) ?>">
            <div class="col-md-3"><label class="form-label">Descrição</label><input name="descricao" class="form-control" required value="<?= e($compraEditar["descricao"]) ?>"></div>
            <div class="col-md-2"><label class="form-label">Categoria <span class="text-muted">(opcional)</span></label><select name="categoria" class="form-select"><option value="">Sem categoria</option><?php foreach ($categorias as $categoria): ?><option value="<?= e($categoria["nome"]) ?>" <?= $categoria["nome"] === $compraEditar["categoria"] ? "selected" : "" ?>><?= e($categoria["nome"]) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label">Pessoa</label><select name="pessoa" class="form-select" required><option value="">Selecione</option><?php foreach ($pessoas as $pessoa): ?><option value="<?= $pessoa["id"] ?>" <?= (string)$pessoa["id"] === (string)$compraEditar["pessoa_id"] ? "selected" : "" ?>><?= e($pessoa["nome"]) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label">Forma</label><select name="forma" class="form-select" required><option value="">Selecione</option><?php foreach ($formas as $forma): ?><option value="<?= $forma["id"] ?>" <?= (string)$forma["id"] === (string)$compraEditar["forma_pagamento_id"] ? "selected" : "" ?>><?= e($forma["nome"]) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label">Data <span class="text-muted">(opcional)</span></label><input type="date" name="data" value="<?= $editando ? e($compraEditar["data_compra"]) : "" ?>" class="form-control"></div>
            <div class="col-md-2"><label class="form-label" id="rotulo-valor">Valor</label><input type="number" step="0.01" min="0.01" name="valor" value="<?= $editando ? e(number_format((float)$compraEditar["valor"], 2, ".", "")) : "" ?>" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Parcelas</label><input type="number" min="1" max="360" name="total_parcelas" value="<?= e($compraEditar["total_parcelas"] ?: 1) ?>" class="form-control" <?= $editando ? "readonly" : "" ?>></div>
            <?php if (!$editando): ?><div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="valor_total" id="valor_total" checked><label id="label-valor-total" class="form-check-label" for="valor_total">Valor total da compra</label></div></div><?php endif; ?>
            <?php if (!$editando): ?><div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="conta_compartilhada" id="conta_compartilhada" onchange="alternarContaCompartilhada()"><label class="form-check-label" for="conta_compartilhada">Conta compartilhada</label></div></div><div id="campo-pessoa-compartilhada" class="col-md-3 d-none"><label class="form-label">Outra pessoa</label><select name="pessoa_compartilhada" class="form-select"><option value="">Selecione</option><?php foreach ($pessoas as $pessoa): ?><option value="<?= $pessoa["id"] ?>"><?= e($pessoa["nome"]) ?></option><?php endforeach; ?></select><div class="form-text">O valor informado será lançado para cada pessoa.</div></div><?php endif; ?>
            <div class="col-md-3 d-flex align-items-end"><button name="salvar_compra" class="btn btn-primary w-100"><?= $editando ? "Atualizar compra" : "Salvar compra" ?></button></div>
        </form>
    </div></section>
    <?php endif; ?>

    <?php if ($ehAdmin): ?>
    <section class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><div class="card app-card summary-card"><div class="card-body"><div class="summary-label">💵 Salário</div><div class="summary-value text-success mt-2 valor-financeiro"><?= valorBrasileiro($salario) ?></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card app-card summary-card"><div class="card-body"><div class="summary-label">💸 Total gasto</div><div class="summary-value text-danger mt-2 valor-financeiro"><?= valorBrasileiro($totalGasto) ?></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card app-card summary-card"><div class="card-body"><div class="summary-label">✅ Faturas pagas</div><div class="summary-value text-primary mt-2 valor-financeiro"><?= valorBrasileiro($totalFaturasPagas) ?></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card app-card summary-card"><div class="card-body"><div class="summary-label">🏦 Guardar / investir</div><div class="summary-value text-info mt-2 valor-financeiro"><?= valorBrasileiro($valorGuardar) ?></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card app-card summary-card"><div class="card-body"><div class="summary-label">💰 Saldo final</div><div class="summary-value <?= $saldo >= 0 ? "text-success" : "text-danger" ?> mt-2 valor-financeiro"><?= valorBrasileiro($saldo) ?></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card app-card summary-card"><div class="card-body"><div class="summary-label">📅 Média por dia do fim de semana</div><div class="summary-value <?= $mediaFimDeSemana >= 0 ? "text-success" : "text-danger" ?> mt-2 valor-financeiro"><?= valorBrasileiro($mediaFimDeSemana) ?></div><div class="small text-muted mt-1">Saldo final ÷ <?= $diasFimDeSemana ?> dia(s) restante(s)</div></div></div></div>
    </section>
    <?php else: ?>
    <section class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><div class="card app-card summary-card"><div class="card-body"><div class="summary-label">💸 Total gasto</div><div class="summary-value text-danger mt-2 valor-financeiro"><?= valorBrasileiro($totalGasto) ?></div></div></div></div>
    </section>
    <?php endif; ?>

    <?php if ($ehAdmin && !$configuracoesTemPessoaId): ?>
    <section class="card app-card mb-4"><div class="card-body p-4">
        <h2 class="h5 mb-3">Salário do mês</h2>
        <form method="post" class="row g-2 align-items-end"><input type="hidden" name="mes" value="<?= $mes ?>"><input type="hidden" name="ano" value="<?= $ano ?>">
            <div class="col-sm-4 col-md-3"><label class="form-label">Valor</label><input type="number" step="0.01" min="0" name="salario" value="<?= e(number_format($salario, 2, ".", "")) ?>" class="form-control" required></div>
            <div class="col-sm-auto"><button name="salvar_salario" class="btn btn-success">Salvar salário</button></div>
        </form>
    </div></section>
    <?php endif; ?>

    <?php if ($ehAdmin && $temReservasMensais): ?>
    <section class="card app-card mb-4"><div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div><h2 class="h5 mb-1">🏦 Guardar / investir</h2><p class="text-muted small mb-0">Ajuste o valor guardado e os dias de fim de semana restantes quando quiser.</p></div>
            <span class="text-muted small"><?= e($meses[$mes]) ?>/<?= $ano ?></span>
        </div>
        <form method="post" class="row g-2 align-items-end">
            <div class="col-sm-4 col-md-3"><label class="form-label">Valor do mês</label><input type="number" step="0.01" min="0" name="valor_guardar" value="<?= e(number_format($valorGuardar, 2, ".", "")) ?>" class="form-control" required></div>
            <div class="col-sm-4 col-md-3"><label class="form-label">Dias de fim de semana restantes</label><input type="number" min="0" max="8" name="dias_fim_semana" value="<?= $diasFimDeSemana ?>" class="form-control" required></div>
            <div class="col-sm-auto"><button name="salvar_reserva" class="btn btn-info">Salvar</button></div>
        </form>
    </div></section>
    <?php endif; ?>

    <?php if ($ehAdmin && $temSolicitacoesAjuste): ?>
    <section class="card app-card mb-4"><div class="card-body p-4">
        <h2 class="h5 mb-3">🔔 Solicitações de correção de valor</h2>
        <?php if ($solicitacoesPendentes): ?>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th>Pessoa</th><th>Compra</th><th>Atual</th><th>Solicitado</th><th>Motivo</th><th></th></tr></thead>
                <tbody><?php foreach ($solicitacoesPendentes as $solicitacao): ?><tr>
                    <td><?= e($solicitacao["pessoa"]) ?></td>
                    <td><?= e($solicitacao["descricao"]) ?></td>
                    <td><?= valorBrasileiro($solicitacao["valor_atual"]) ?></td>
                    <td class="fw-bold"><?= valorBrasileiro($solicitacao["valor_solicitado"]) ?></td>
                    <td><?= e($solicitacao["observacao"] ?: "—") ?></td>
                    <td><form method="post" class="d-flex gap-1"><input type="hidden" name="solicitacao_id" value="<?= $solicitacao["id"] ?>"><button name="analisar_ajuste" value="1" type="submit" class="btn btn-sm btn-success" onclick="this.form.decisao.value='aprovada'">Aprovar</button><button name="analisar_ajuste" value="1" type="submit" class="btn btn-sm btn-outline-danger" onclick="this.form.decisao.value='recusada'">Recusar</button><input type="hidden" name="decisao" value=""></form></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php else: ?><p class="text-muted mb-0">Nenhuma solicitação pendente.</p><?php endif; ?>
    </div></section>
    <?php endif; ?>

    <?php if (false): ?>
    <section class="card app-card mb-4"><div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0"><?= $editando ? "✏️ Editar compra" : "🛒 Nova compra" ?></h2><?php if ($editando): ?><a href="dashboard.php?mes=<?= $mes ?>&ano=<?= $ano ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a><?php endif; ?></div>
        <form method="post" class="row g-3"><input type="hidden" name="mes" value="<?= $mes ?>"><input type="hidden" name="ano" value="<?= $ano ?>"><input type="hidden" name="id" value="<?= e($compraEditar["id"]) ?>">
            <div class="col-md-4"><label class="form-label">Descrição</label><input name="descricao" class="form-control" required value="<?= e($compraEditar["descricao"]) ?>"></div>
            <div class="col-md-2"><label class="form-label">Categoria</label><select name="categoria" class="form-select" required><option value="">Selecione</option><?php foreach ($categorias as $categoria): ?><option value="<?= e($categoria["nome"]) ?>" <?= $categoria["nome"] === $compraEditar["categoria"] ? "selected" : "" ?>><?= e($categoria["nome"]) ?></option><?php endforeach; ?></select></div>
            <?php if ($ehAdmin): ?><div class="col-md-2"><label class="form-label">Pessoa</label><select name="pessoa" class="form-select" required><option value="">Selecione</option><?php foreach ($pessoas as $pessoa): ?><option value="<?= $pessoa["id"] ?>" <?= (string)$pessoa["id"] === (string)$compraEditar["pessoa_id"] ? "selected" : "" ?>><?= e($pessoa["nome"]) ?></option><?php endforeach; ?></select></div><?php endif; ?>
            <div class="col-md-2"><label class="form-label">Forma</label><select name="forma" class="form-select" required><option value="">Selecione</option><?php foreach ($formas as $forma): ?><option value="<?= $forma["id"] ?>" <?= (string)$forma["id"] === (string)$compraEditar["forma_pagamento_id"] ? "selected" : "" ?>><?= e($forma["nome"]) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label">Data</label><input type="date" name="data" value="<?= e($compraEditar["data_compra"]) ?>" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Valor</label><input type="number" step="0.01" min="0.01" name="valor" value="<?= $editando ? e(number_format((float)$compraEditar["valor"], 2, ".", "")) : "" ?>" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Parcelas</label><input type="number" min="1" max="360" name="total_parcelas" value="<?= e($compraEditar["total_parcelas"] ?: 1) ?>" class="form-control" <?= $editando ? "readonly" : "" ?>></div>
            <?php if (!$editando): ?><div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="valor_total" id="valor_total" checked><label class="form-check-label" for="valor_total">Valor total da compra</label></div></div><?php endif; ?>
            <div class="col-md-3 d-flex align-items-end"><button name="salvar_compra" class="btn btn-primary w-100"><?= $editando ? "Atualizar compra" : "Salvar compra" ?></button></div>
        </form>
    </div></section>
    <?php endif; ?>

    <?php if ($ehAdmin): ?>
    <section><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h4 mb-0">💳 Compras por forma de pagamento</h2><span class="text-muted small"><?= count($compras) ?> lançamento(s)</span></div><div class="row g-4">
        <?php foreach ($formas as $forma): $formaId = (int)$forma["id"]; $lista = $comprasPorForma[$formaId]; $totalForma = array_sum(array_column($lista, "valor")); $fatura = $faturas[$formaId] ?? null; $faturaPaga = !empty($fatura["pago"]); ?>
        <div class="col-12 col-xl-6"><article class="card app-card overflow-hidden h-100">
            <div class="invoice-header p-4 d-flex justify-content-between"><div><h3 class="h5 mb-1">💳 <?= e($forma["nome"]) ?></h3><span class="small opacity-75"><?= count($lista) ?> compra(s)</span></div><div class="text-end"><div class="invoice-total"><?= valorBrasileiro($totalForma) ?></div><small class="opacity-75">Total da fatura</small></div></div>
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center"><span class="badge text-bg-<?= $faturaPaga ? "success" : "warning" ?>"><?= $faturaPaga ? "✓ Pago" : "Pendente" ?></span><?php if ($ehAdmin): ?><form method="post"><input type="hidden" name="mes" value="<?= $mes ?>"><input type="hidden" name="ano" value="<?= $ano ?>"><input type="hidden" name="forma_pagamento_id" value="<?= $formaId ?>"><input type="hidden" name="novo_status" value="<?= $faturaPaga ? 0 : 1 ?>"><button name="alterar_fatura" class="btn btn-sm btn-outline-<?= $faturaPaga ? "secondary" : "success" ?>"><?= $faturaPaga ? "Desfazer" : "Marcar como paga" ?></button></form><?php endif; ?></div>
            <div class="list-group list-group-flush"><?php foreach ($lista as $compra): ?><div class="list-group-item d-flex justify-content-between gap-3"><div><strong><?= e($compra["descricao"]) ?></strong><div class="small text-muted"><?= e($compra["pessoa"]) ?> · <?= date("d/m/Y", strtotime($compra["data_compra"])) ?><?= $compra["total_parcelas"] ? " · " . (int)$compra["parcela_atual"] . "/" . (int)$compra["total_parcelas"] : "" ?></div></div><div class="text-end"><div class="fw-bold"><?= valorBrasileiro($compra["valor"]) ?></div><a class="small" href="?mes=<?= $mes ?>&ano=<?= $ano ?>&editar=<?= $compra["id"] ?>">Editar</a> · <a class="small text-danger" onclick="return confirm('Excluir esta compra?')" href="?mes=<?= $mes ?>&ano=<?= $ano ?>&excluir=<?= $compra["id"] ?>">Excluir</a></div></div><?php endforeach; if (!$lista): ?><div class="list-group-item text-muted">Nenhuma compra neste mês.</div><?php endif; ?></div>
        </article></div>
        <?php endforeach; ?>
    </div></section>
    <?php else: ?>
    <section class="card app-card">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">📋 Meus lançamentos</h2>
                <span class="text-muted small"><?= count($compras) ?> lançamento(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Data</th>
                            <th>Parcela</th>
                            <th class="text-end">Valor</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compras as $compra): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($compra["descricao"]) ?></td>
                                <td><?= e($compra["categoria"]) ?></td>
                                <td><?= date("d/m/Y", strtotime($compra["data_compra"])) ?></td>
                                <td><?= $compra["total_parcelas"] ? (int)$compra["parcela_atual"] . "/" . (int)$compra["total_parcelas"] : "À vista" ?></td>
                                <td class="text-end fw-bold"><?= valorBrasileiro($compra["valor"]) ?></td>
                                <td class="text-end">
                                    <?php if ($temSolicitacoesAjuste && !isset($comprasComSolicitacaoPendente[(int)$compra["id"]])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('ajuste-<?= $compra["id"] ?>').classList.toggle('d-none')">Corrigir valor</button>
                                    <?php elseif ($temSolicitacoesAjuste): ?>
                                        <span class="badge text-bg-warning">Em análise</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($temSolicitacoesAjuste && !isset($comprasComSolicitacaoPendente[(int)$compra["id"]])): ?>
                                <tr id="ajuste-<?= $compra["id"] ?>" class="d-none"><td colspan="6" class="bg-light">
                                    <form method="post" class="row g-2 align-items-end"><input type="hidden" name="compra_id" value="<?= $compra["id"] ?>">
                                        <div class="col-md-3"><label class="form-label small">Valor correto</label><input type="number" step="0.01" min="0.01" name="valor_solicitado" value="<?= e(number_format((float)$compra["valor"], 2, ".", "")) ?>" class="form-control" required></div>
                                        <div class="col-md-6"><label class="form-label small">Motivo (opcional)</label><input name="observacao" maxlength="500" class="form-control" placeholder="Ex.: o valor correto é o da nota fiscal"></div>
                                        <div class="col-md-3"><button name="solicitar_ajuste" class="btn btn-primary w-100">Enviar solicitação</button></div>
                                    </form>
                                </td></tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (!$compras): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhum lançamento neste mês.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>
<script>
    const chaveOcultarValores = "financeiro_ocultar_valores";

    function alternarContaCompartilhada() {
        const marcada = document.getElementById("conta_compartilhada").checked;
        const campoPessoa = document.getElementById("campo-pessoa-compartilhada");
        const rotuloValor = document.getElementById("rotulo-valor");
        campoPessoa.classList.toggle("d-none", !marcada);
        rotuloValor.textContent = marcada ? "Valor para cada pessoa" : "Valor";
        document.getElementById("label-valor-total").textContent = marcada
            ? "Valor total por pessoa (se houver parcelas)"
            : "Valor total da compra";
    }

    function aplicarOcultacao(ocultar) {
        document.querySelectorAll(".valor-financeiro").forEach(function (campo) {
            if (!campo.dataset.valorOriginal) campo.dataset.valorOriginal = campo.textContent;
            campo.textContent = ocultar ? "••••••••" : campo.dataset.valorOriginal;
        });
        const botao = document.getElementById("btnOcultar");
        botao.textContent = ocultar ? "🙈" : "👁️";
        botao.title = ocultar ? "Mostrar valores" : "Ocultar valores";
        botao.setAttribute("aria-label", botao.title);
    }

    function alternarValores() {
        const ocultar = localStorage.getItem(chaveOcultarValores) !== "1";
        localStorage.setItem(chaveOcultarValores, ocultar ? "1" : "0");
        aplicarOcultacao(ocultar);
    }

    document.addEventListener("DOMContentLoaded", function () {
        aplicarOcultacao(localStorage.getItem(chaveOcultarValores) === "1");
    });
</script>
</body>
</html>
