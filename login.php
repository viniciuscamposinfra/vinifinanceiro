<?php
session_start();

if(isset($_SESSION["id"])){
    header("Location: pages/dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Financeiro Campos</title>

<link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

<div class="login-box">

    <div style="text-align:center;margin-bottom:25px;">

        <div style="font-size:70px;">
            💰
        </div>

        <h1>
            Financeiro Campos
        </h1>

        <p>
            Controle Financeiro Pessoal
        </p>

    </div>

    <?php if(isset($_GET["erro"])): ?>

        <div style="background:#ffe5e5;color:#b30000;padding:12px;border-radius:10px;margin-bottom:20px;text-align:center;">

            Usuário ou senha inválidos.

        </div>

    <?php endif; ?>

    <form action="processa_login.php" method="POST">

        <label>👤 Usuário</label>

        <input
            type="text"
            name="usuario"
            placeholder="Digite seu usuário"
            required>

        <label>🔒 Senha</label>

        <div style="position:relative;">

            <input
                type="password"
                id="senha"
                name="senha"
                placeholder="Digite sua senha"
                required>

            <span
                onclick="mostrarSenha()"
                style="position:absolute;right:15px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:20px;">

                👁️

            </span>

        </div>

        <button type="submit">

            🚀 Entrar

        </button>

    </form>

</div>

<script>

function mostrarSenha(){

    let campo=document.getElementById("senha");

    campo.type=(campo.type=="password")?"text":"password";

}

</script>

</body>

</html>