<?php

require "../config.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Dashboard</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>

body{
    background:#f4f6fb;
    margin:0;
    font-family:Arial,Helvetica,sans-serif;
}

.topo{
    background:#163d77;
    color:white;
    padding:20px 40px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.menu{

    background:white;
    width:220px;
    min-height:100vh;
    box-shadow:0 0 15px rgba(0,0,0,.1);

}

.menu a{

    display:block;
    padding:15px 20px;
    color:#333;
    text-decoration:none;
    border-bottom:1px solid #eee;

}

.menu a:hover{

    background:#163d77;
    color:white;

}

.container{

    display:flex;

}

.conteudo{

    flex:1;
    padding:40px;

}

.card{

    background:white;
    border-radius:10px;
    padding:30px;
    box-shadow:0 0 15px rgba(0,0,0,.08);

}

</style>

</head>

<body>

<div class="topo">

<div>

<h2>Financeiro Campos</h2>

</div>

<div>

Olá,
<strong><?php echo $_SESSION["nome"]; ?></strong>

</div>

</div>

<div class="container">

<div class="menu">

<a href="dashboard.php">🏠 Dashboard</a>

<a href="compras.php">🛒 Compras</a>

<a href="cartoes.php">💳 Cartões</a>

<a href="pessoas.php">👥 Pessoas</a>

<a href="relatorios.php">📊 Relatórios</a>

<a href="configuracoes.php">⚙ Configurações</a>

<a href="../logout.php">🚪 Sair</a>

</div>

<div class="conteudo">

<div class="card">

<h2>Bem-vindo!</h2>

<p>Login realizado com sucesso.</p>

<p>Agora vamos começar a construir seu sistema financeiro.</p>

</div>

</div>

</div>

</body>

</html>