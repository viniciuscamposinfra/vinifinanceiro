<?php

require "../config.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit;
}

if (strtolower($_SESSION["tipo"]) != "admin") {
    header("Location: dashboard.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CADASTRAR USUÁRIO
|--------------------------------------------------------------------------
*/

if(isset($_POST["salvar_usuario"])){

    $id = (int)($_POST["id"] ?? 0);

    $nome = trim($_POST["nome"]);
    $usuario = trim($_POST["usuario"]);
    $tipo = trim($_POST["tipo"]);
    $pessoa = (int)$_POST["pessoa"];

    if($id){

        if(!empty($_POST["senha"])){

            $senha = password_hash($_POST["senha"], PASSWORD_DEFAULT);

            $sql = $pdo->prepare("
                UPDATE usuarios
                SET
                    nome=?,
                    usuario=?,
                    senha=?,
                    tipo=?,
                    pessoa_id=?
                WHERE id=?
            ");

            $sql->execute([
                $nome,
                $usuario,
                $senha,
                $tipo,
                $pessoa,
                $id
            ]);

        }else{

            $sql = $pdo->prepare("
                UPDATE usuarios
                SET
                    nome=?,
                    usuario=?,
                    tipo=?,
                    pessoa_id=?
                WHERE id=?
            ");

            $sql->execute([
                $nome,
                $usuario,
                $tipo,
                $pessoa,
                $id
            ]);

        }

    }else{

        $senha = password_hash($_POST["senha"], PASSWORD_DEFAULT);

        $sql = $pdo->prepare("
            INSERT INTO usuarios
            (
                nome,
                usuario,
                senha,
                tipo,
                pessoa_id
            )
            VALUES
            (
                ?,?,?,?,?
            )
        ");

        $sql->execute([
            $nome,
            $usuario,
            $senha,
            $tipo,
            $pessoa
        ]);

    }

    header("Location: usuarios.php");

    exit;

}


/*
|--------------------------------------------------------------------------
| EXCLUIR USUÁRIO
|--------------------------------------------------------------------------
*/

if(isset($_GET["excluir"])){

    $id=(int)$_GET["excluir"];

    if($id!=$_SESSION["id"]){

        $sql=$pdo->prepare("
            DELETE FROM usuarios
            WHERE id=?
        ");

        $sql->execute([$id]);

    }

    header("Location: usuarios.php");
    exit;

}

/*
|--------------------------------------------------------------------------
| EDITAR USUÁRIO
|--------------------------------------------------------------------------
*/

$editando = false;

$usuarioEditar = [
    "id" => "",
    "nome" => "",
    "usuario" => "",
    "tipo" => "usuario",
    "pessoa_id" => ""
];

if(isset($_GET["editar"])){

    $editando = true;

    $id = (int)$_GET["editar"];

    $sql = $pdo->prepare("
        SELECT *
        FROM usuarios
        WHERE id=?
        LIMIT 1
    ");

    $sql->execute([$id]);

    if($sql->rowCount()){

        $usuarioEditar = $sql->fetch(PDO::FETCH_ASSOC);

    }

}

/*
|--------------------------------------------------------------------------
| CARREGAR PESSOAS
|--------------------------------------------------------------------------
*/

$pessoas=$pdo->query("
SELECT *
FROM pessoas
ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| CARREGAR USUÁRIOS
|--------------------------------------------------------------------------
*/

$sql=$pdo->query("
SELECT

usuarios.*,

pessoas.nome pessoa

FROM usuarios

LEFT JOIN pessoas
ON pessoas.id=usuarios.pessoa_id

ORDER BY usuarios.nome
");

$usuarios=$sql->fetchAll(PDO::FETCH_ASSOC);

?>

<!doctype html>

<html lang="pt-br">

<head>

<meta charset="utf-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Usuários</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{

    background:#eef2f7;

}

.card{

    border:none;
    border-radius:16px;

}

</style>

</head>

<body>

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="fw-bold">

            👥 Usuários do Sistema

        </h2>

        <small class="text-muted">

            Gerenciamento de acessos

        </small>

    </div>

    <a
    href="dashboard.php"
    class="btn btn-secondary">

        ← Voltar

    </a>

</div>

<div class="card shadow mb-4">

    <div class="card-body">

        <h4 class="mb-4">

<?php if($editando): ?>

✏️ Editar Usuário

<?php else: ?>

➕ Novo Usuário

<?php endif; ?>

</h4>

<?php if($editando): ?>

<a
href="usuarios.php"
class="btn btn-secondary btn-sm mb-3">

Cancelar edição

</a>

<?php endif; ?>

        <form method="POST">

        <input
type="hidden"
name="id"
value="<?= $usuarioEditar["id"] ?>">

            <div class="row g-3">

                <div class="col-md-3">

                    <label class="form-label">

                        Nome

                    </label>

                    <input
type="text"
name="nome"
value="<?= htmlspecialchars($usuarioEditar["nome"]) ?>"
class="form-control"
required>

                </div>

                <div class="col-md-2">

                    <label class="form-label">

                        Usuário

                    </label>

                    <input
type="text"
name="usuario"
value="<?= htmlspecialchars($usuarioEditar["usuario"]) ?>"
class="form-control"
required>

                </div>

                <div class="col-md-2">

                    <label class="form-label">

                        Senha

                    </label>

                    <input
type="password"
name="senha"
class="form-control"
<?= $editando ? "" : "required" ?>>

<?php if($editando): ?>

<small class="text-muted">

Deixe em branco para manter a senha.

</small>

<?php endif; ?>

                </div>

                <div class="col-md-2">

                    <label class="form-label">

                        Pessoa

                    </label>

                    <select
                    name="pessoa"
                    class="form-select">

                        <?php foreach($pessoas as $pessoa): ?>

                            <option
value="<?= $pessoa["id"] ?>"
<?= $usuarioEditar["pessoa_id"] == $pessoa["id"] ? "selected" : "" ?>>

<?= htmlspecialchars($pessoa["nome"]) ?>

</option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-2">

                    <label class="form-label">

                        Perfil

                    </label>

                    <select
name="tipo"
class="form-select">

<option
value="usuario"
<?= $usuarioEditar["tipo"]=="usuario" ? "selected" : "" ?>>

Usuário

</option>

<option
value="admin"
<?= $usuarioEditar["tipo"]=="admin" ? "selected" : "" ?>>

Administrador

</option>

</select>

                </div>

                <div class="col-md-1 d-flex align-items-end">

    <button
    type="submit"
    name="salvar_usuario"
    class="btn btn-success w-100">

        <?php if($editando): ?>

            💾 Atualizar

        <?php else: ?>

            ➕ Criar

        <?php endif; ?>

    </button>

</div>

            </div>

        </form>

    </div>

</div>

<div class="card shadow">

    <div class="card-body">

        <h4 class="mb-4">

            👥 Usuários Cadastrados

        </h4>

        <table class="table table-hover align-middle">

            <thead class="table-dark">

                <tr>

                    <th>Nome</th>

                    <th>Usuário</th>

                    <th>Pessoa</th>

                    <th>Perfil</th>

                    <th width="160">Ações</th>

                </tr>

            </thead>

            <tbody>

            <?php foreach($usuarios as $usuario): ?>

                <tr>

                    <td>

                        <?= htmlspecialchars($usuario["nome"]) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars($usuario["usuario"]) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars($usuario["pessoa"]) ?>

                    </td>

                    <td>

                        <?php if(strtolower($usuario["tipo"])=="admin"): ?>

                            <span class="badge bg-danger">

                                ADMIN

                            </span>

                        <?php else: ?>

                            <span class="badge bg-primary">

                                USUÁRIO

                            </span>

                        <?php endif; ?>

                    </td>

                    <td>

                        <a
                        href="usuarios.php?editar=<?= $usuario["id"] ?>"
                        class="btn btn-sm btn-warning">

                            ✏️ Editar

                        </a>

                        <?php if($usuario["id"]!=$_SESSION["id"]): ?>

                        <a
                        href="usuarios.php?excluir=<?= $usuario["id"] ?>"
                        class="btn btn-sm btn-danger"
                        onclick="return confirm('Deseja excluir este usuário?')">

                            🗑️

                        </a>

                        <?php else: ?>

                        <button
                        class="btn btn-sm btn-secondary"
                        disabled>

                            🔒

                        </button>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

</div>

</body>

</html>