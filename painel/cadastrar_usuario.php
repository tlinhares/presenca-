<?php
// painel/cadastrar_usuario.php
session_start();
include_once(__DIR__ . '/../auth/verifica_sessao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: cadastrar_usuario                                      ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('cadastrar_usuario');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Usuário - Presença AOM</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-header text-center">
                        <strong>Cadastrar Novo Usuário</strong>
                    </div>
                    <div class="card-body">
                        <form id="formCadastro">
                            <div class="form-group">
                                <label for="nome">Nome</label>
                                <input type="text" class="form-control" id="nome" required>
                            </div>
                            <div class="form-group">
                                <label for="email">E-mail</label>
                                <input type="email" class="form-control" id="email" required>
                            </div>
                            <div class="form-group">
                                <label for="senha">Senha</label>
                                <input type="text" class="form-control" id="senha" required>
                            </div>
                            <div class="form-group">
                                <label for="categoria">Categoria</label>
                                <select class="form-control" id="categoria">
                                    <option value="membro">Membro</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Cadastrar</button>
                            <a href="dashboard.php" class="btn btn-secondary ml-2">Voltar</a>
                        </form>
                        <div id="mensagem" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="../js/cadastrar_usuario.js"></script>
</body>
</html>
