<?php

session_start();

$host = "localhost";
$banco = "financeiro_campos";
$usuario = "root";
$senha = "";

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$banco;charset=utf8",
        $usuario,
        $senha
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e){

    die("Erro ao conectar: " . $e->getMessage());

}