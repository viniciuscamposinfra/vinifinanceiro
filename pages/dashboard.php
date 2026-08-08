<?php

require "../config.php";

if (!isset($_SESSION["id"])) {

    header("Location: ../login.php");
    exit;

}

/*
|--------------------------------------------------------------------------
| MÊS ATUAL
|--------------------------------------------------------------------------
*/

$mes = isset($_GET["mes"]) ? (int)$_GET["mes"] : date("n");
$ano = isset($_GET["ano"]) ? (int)$_GET["ano"] : date("Y");

/*
|--------------------------------------------------------------------------
| SALVAR SALÁRIO
|--------------------------------------------------------------------------
*/

if(isset($_POST["salvar_salario"])){

    $salario = str_replace(",",".",$_POST["salario"]);

    $sql = $pdo->prepare("SELECT id FROM configuracoes WHERE mes=? AND ano=?");
    $sql->execute([$mes,$ano]);

    if($sql->rowCount()>0){

        $pdo->prepare("
            UPDATE configuracoes
            SET salario=?
            WHERE mes=? AND ano=?
        ")->execute([$salario,$mes,$ano]);

    }else{

        $pdo->prepare("
            INSERT INTO configuracoes
            (mes,ano,salario)
            VALUES
            (?,?,?)
        ")->execute([$mes,$ano,$salario]);

    }

    header("Location: dashboard.php?mes=".$mes."&ano=".$ano);
    exit;

}

/*
|--------------------------------------------------------------------------
| SALVAR COMPRA
|--------------------------------------------------------------------------
*/

if(isset($_POST["salvar_compra"])){

    $descricao = trim($_POST["descricao"]);
    $categoria = trim($_POST["categoria"]);
    $valor = str_replace(",",".",$_POST["valor"]);
    $pessoa = (int)$_POST["pessoa"];
    $forma = (int)$_POST["forma"];
    $data = $_POST["data"];
    $pago = isset($_POST["pago"]) ? 1 : 0;

    $sql = $pdo->prepare("
        INSERT INTO compras
        (
            descricao,
            categoria,
            valor,
            data_compra,
            pessoa_id,
            forma_pagamento_id,
            pago
        )
        VALUES
        (
            :descricao,
            :categoria,
            :valor,
            :data,
            :pessoa,
            :forma,
            :pago
        )
    ");

    $sql->execute([
        ":descricao"=>$descricao,
        ":categoria"=>$categoria,
        ":valor"=>$valor,
        ":data"=>$data,
        ":pessoa"=>$pessoa,
        ":forma"=>$forma,
        ":pago"=>$pago
    ]);

    header("Location: dashboard.php?mes=".$mes."&ano=".$ano);
    exit;

}

/*
|--------------------------------------------------------------------------
| CARREGAR DADOS
|--------------------------------------------------------------------------
*/

$sql=$pdo->prepare("
SELECT salario
FROM configuracoes
WHERE mes=?
AND ano=?
LIMIT 1
");

$sql->execute([$mes,$ano]);

$salario=0;

if($sql->rowCount()){

    $salario=$sql->fetch()["salario"];

}

$pessoas=$pdo->query("
SELECT *
FROM pessoas
ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

$formas=$pdo->query("
SELECT *
FROM formas_pagamento
ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

$categorias=$pdo->query("
SELECT *
FROM categorias
ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

$total=$pdo->prepare("
SELECT
IFNULL(SUM(valor),0)
FROM compras
WHERE MONTH(data_compra)=?
AND YEAR(data_compra)=?
");

$total->execute([$mes,$ano]);

$totalGasto=$total->fetchColumn();

$pago=$pdo->prepare("
SELECT
IFNULL(SUM(valor),0)
FROM compras
WHERE pago=1
AND MONTH(data_compra)=?
AND YEAR(data_compra)=?
");

$pago->execute([$mes,$ano]);

$totalPago=$pago->fetchColumn();

$pendente=$pdo->prepare("
SELECT
IFNULL(SUM(valor),0)
FROM compras
WHERE pago=0
AND MONTH(data_compra)=?
AND YEAR(data_compra)=?
");

$pendente->execute([$mes,$ano]);

$totalPendente=$pendente->fetchColumn();

$saldo=$salario-$totalGasto;

$compras=$pdo->prepare("
SELECT

compras.*,

pessoas.nome pessoa,

formas_pagamento.nome forma

FROM compras

INNER JOIN pessoas
ON pessoas.id=compras.pessoa_id

INNER JOIN formas_pagamento
ON formas_pagamento.id=compras.forma_pagamento_id

WHERE MONTH(data_compra)=?
AND YEAR(data_compra)=?

ORDER BY data_compra DESC,id DESC

");

$compras->execute([$mes,$ano]);

$compras=$compras->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>

<html lang="pt-br">

<head>

<meta charset="utf-8">

<title>Financeiro Campos</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{

background:#eef2f7;

}

.card{

border:none;
border-radius:15px;

}

</style>

</head>

<body>

<div class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="fw-bold mb-0">
            💰 Financeiro Campos
        </h2>

        <small class="text-muted">
            Bem-vindo,
            <strong><?= $_SESSION["nome"] ?></strong>
        </small>

    </div>

    <div class="d-flex align-items-center">

        <form method="GET" class="d-flex me-3">

            <select
                name="mes"
                class="form-select me-2">

                <?php

                $meses = [

                    1=>"Janeiro",
                    2=>"Fevereiro",
                    3=>"Março",
                    4=>"Abril",
                    5=>"Maio",
                    6=>"Junho",
                    7=>"Julho",
                    8=>"Agosto",
                    9=>"Setembro",
                    10=>"Outubro",
                    11=>"Novembro",
                    12=>"Dezembro"

                ];

                foreach($meses as $numero=>$nome){

                    $selected = ($numero==$mes) ? "selected" : "";

                    echo "<option value='$numero' $selected>$nome</option>";

                }

                ?>

            </select>

            <input
                type="number"
                name="ano"
                value="<?= $ano ?>"
                class="form-control me-2"
                style="width:100px;">

            <button
                class="btn btn-primary">

                Ver

            </button>

        </form>

        <a
            href="../logout.php"
            class="btn btn-danger">

            Sair

        </a>

    </div>

</div>

<div class="row mb-4">

<div class="col-md-3">

<div class="card shadow-sm">

<div class="card-body text-center">

<h6>💵 Salário</h6>

<h3 class="text-success">

R$ <?= number_format($salario,2,",",".") ?>

</h3>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card shadow-sm">

<div class="card-body text-center">

<h6>💸 Total Gasto</h6>

<h3 class="text-danger">

R$ <?= number_format($totalGasto,2,",",".") ?>

</h3>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card shadow-sm">

<div class="card-body text-center">

<h6>✅ Pago</h6>

<h3 class="text-primary">

R$ <?= number_format($totalPago,2,",",".") ?>

</h3>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card shadow-sm">

<div class="card-body text-center">

<h6>💰 Saldo</h6>

<h3 class="<?= ($saldo>=0)?'text-success':'text-danger' ?>">

R$ <?= number_format($saldo,2,",",".") ?>

</h3>

</div>

</div>

</div>

</div>

<div class="card shadow mb-4">

<div class="card-body">

<h4 class="mb-3">

Salário do Mês

</h4>

<form method="POST" class="row">

<div class="col-md-3">

<input

type="number"

step="0.01"

name="salario"

value="<?= $salario ?>"

class="form-control"

required>

</div>

<div class="col-md-2">

<button

name="salvar_salario"

class="btn btn-success w-100">

Salvar

</button>

</div>

</form>

</div>

</div>
<div class="card shadow mb-4">

<div class="card-body">

<h4 class="mb-4">

🛒 Nova Compra

</h4>

<form method="POST" action="">

<div class="row">

<div class="col-md-4 mb-3">

<label class="form-label">

Descrição

</label>

<input
type="text"
name="descricao"
class="form-control"
required>

</div>

<div class="col-md-2 mb-3">

<label class="form-label">

Categoria

</label>

<select
name="categoria"
class="form-select"
required>

<?php foreach($categorias as $categoria): ?>

<option
value="<?= $categoria["nome"] ?>">

<?= $categoria["nome"] ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-2 mb-3">

<label class="form-label">

Valor

</label>

<input
type="number"
step="0.01"
name="valor"
class="form-control"
required>

</div>

<div class="col-md-2 mb-3">

<label class="form-label">

Pessoa

</label>

<select
name="pessoa"
class="form-select">

<?php foreach($pessoas as $pessoa): ?>

<option
value="<?= $pessoa["id"] ?>">

<?= $pessoa["nome"] ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-2 mb-3">

<label class="form-label">

Forma

</label>

<select
name="forma"
class="form-select">

<?php foreach($formas as $forma): ?>

<option
value="<?= $forma["id"] ?>">

<?= $forma["nome"] ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-3 mb-3">

<label class="form-label">

Data

</label>

<input
type="date"
name="data"
class="form-control"
value="<?= date("Y-m-d") ?>">

</div>

<div class="col-md-3 mb-3 d-flex align-items-end">

<div class="form-check">

<input
class="form-check-input"
type="checkbox"
name="pago"
id="pago">

<label
class="form-check-label"
for="pago">

Compra já foi paga

</label>

</div>

</div>

<div class="col-md-3 mb-3 d-flex align-items-end">

<button

type="submit"

name="salvar_compra"

class="btn btn-success w-100">

💾 Salvar Compra

</button>

</div>

</div>

</form>

</div>

</div>

<div class="card shadow">

<div class="card-body">

<h4 class="mb-3">

📋 Compras do mês

</h4>
<?php if(count($compras)==0): ?>

<div class="alert alert-info">

Nenhuma compra cadastrada neste mês.

</div>

<?php else: ?>

<table class="table table-hover align-middle">

<thead class="table-dark">

<tr>

<th>Data</th>

<th>Descrição</th>

<th>Categoria</th>

<th>Pessoa</th>

<th>Forma</th>

<th class="text-end">Valor</th>

<th class="text-center">Status</th>

<th class="text-center">Ações</th>

</tr>

</thead>

<tbody>

<?php foreach($compras as $compra): ?>

<tr>

<td>

<?= date("d/m/Y",strtotime($compra["data_compra"])) ?>

</td>

<td>

<?= htmlspecialchars($compra["descricao"]) ?>

</td>

<td>

<?= htmlspecialchars($compra["categoria"]) ?>

</td>

<td>

<?= htmlspecialchars($compra["pessoa"]) ?>

</td>

<td>

<?= htmlspecialchars($compra["forma"]) ?>

</td>

<td class="text-end">

<strong>

R$ <?= number_format($compra["valor"],2,",",".") ?>

</strong>

</td>

<td class="text-center">

<?php if($compra["pago"]): ?>

<span class="badge bg-success">

Pago

</span>

<?php else: ?>

<span class="badge bg-warning text-dark">

Pendente

</span>

<?php endif; ?>

</td>

<td class="text-center">

<button
class="btn btn-sm btn-primary">

✏️

</button>

<button
class="btn btn-sm btn-danger">

🗑️

</button>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php endif; ?>

</div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>