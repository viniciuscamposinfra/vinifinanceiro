<?php

require "config.php";

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: login.php");
    exit;
}

$usuario = trim($_POST["usuario"]);
$senha = trim($_POST["senha"]);

$sql = "SELECT * FROM usuarios WHERE usuario = :usuario LIMIT 1";

$stmt = $pdo->prepare($sql);

$stmt->bindValue(":usuario", $usuario);

$stmt->execute();

if ($stmt->rowCount() == 1) {

    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

    if (password_verify($senha, $dados["senha"])) {

        $_SESSION["id"] = $dados["id"];
        $_SESSION["nome"] = $dados["nome"];
        $_SESSION["usuario"] = $dados["usuario"];
        $_SESSION["tipo"] = $dados["tipo"];

        header("Location: dashboard.php");
        exit;

    } else {

        header("Location: login.php?erro=1");
        exit;

    }

} else {

    header("Location: login.php?erro=1");
    exit;

}