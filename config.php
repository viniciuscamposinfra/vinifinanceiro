<?php

session_start();

$host = "br1026.hostgator.com.br";
$porta = "3306";
$banco = "vin25708_financeiro";
$usuario = "vin25708_admin";
$senha = "Buhi#uhihfvi5445";

try {

    $pdo = new PDO(
        "mysql:host=$host;port=$porta;dbname=$banco;charset=utf8mb4",
        $usuario,
        $senha
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {

    die("Erro ao conectar com o banco: " . $e->getMessage());

}