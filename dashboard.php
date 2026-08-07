<?php

require "config.php";

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Financeiro Campos</title>
</head>
<body>

<h1>Dashboard</h1>

<p>Bem-vindo,
<strong><?= $_SESSION['nome']; ?></strong>

</p>

<a href="logout.php">Sair</a>

</body>
</html>