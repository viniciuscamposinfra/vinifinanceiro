<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro Campos</title>

    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="login-box">

    <h1>Financeiro Campos</h1>

    <?php if(isset($_GET["erro"])): ?>

        <p style="color:red; text-align:center; margin-bottom:20px;">
            Usuário ou senha inválidos.
        </p>

    <?php endif; ?>

    <form action="processa_login.php" method="POST">

        <label>Usuário</label>

        <input
            type="text"
            name="usuario"
            placeholder="Digite seu usuário"
            required
        >

        <label>Senha</label>

        <input
            type="password"
            name="senha"
            placeholder="Digite sua senha"
            required
        >

        <button type="submit">
            Entrar
        </button>

    </form>

</div>

</body>
</html>