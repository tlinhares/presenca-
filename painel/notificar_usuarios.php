<?php 
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');
include_once(__DIR__ . '/../auth/verifica_permissao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: notificar_usuarios                                     ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('notificar_usuarios');

include_once('../api/conexao.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Notificar Usuários por E-mail</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 20px; }
    .table th, .table td { vertical-align: middle; }
  </style>
</head>
<body>
  <div class="container">
    <div class="mb-4">
      <a href="/painel/dashboard.php" class="btn btn-outline-secondary">← Voltar para o Painel</a>
    </div>
    <h3 class="mb-4">Notificar Usuários sobre Cadastro no Sistema</h3>

    <p class="alert alert-info">A mensagem enviada será padronizada e incluirá um link para o usuário definir a senha.</p>

    <button class="btn btn-success mb-3" id="btnEnviar">Enviar Notificações</button>

    <div class="table-responsive">
      <table class="table table-bordered table-striped" id="tabelaUsuarios">
        <thead class="table-light">
          <tr>
            <th><input type="checkbox" id="selecionarTodos"></th>
            <th>Nome</th>
            <th>E-mail</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $res = $conn->query("SELECT id, nome, email FROM usuarios WHERE email IS NOT NULL AND email != '' and ativo=1 ORDER BY nome");
          while ($row = $res->fetch_assoc()) {
            echo '<tr>
              <td><input type="checkbox" class="selecionar" data-id="' . $row['id'] . '" data-nome="' . htmlspecialchars($row['nome']) . '" data-email="' . htmlspecialchars($row['email']) . '"></td>
              <td>' . htmlspecialchars($row['nome']) . '</td>
              <td>' . htmlspecialchars($row['email']) . '</td>
            </tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
    $('#selecionarTodos').on('change', function() {
      $('.selecionar').prop('checked', this.checked);
    });

    $('#btnEnviar').on('click', function () {
      const usuarios = [];
      $('.selecionar:checked').each(function () {
        usuarios.push({
          id: $(this).data('id'),
          nome: $(this).data('nome'),
          email: $(this).data('email')
        });
      });

      if (usuarios.length === 0) {
        alert('Selecione pelo menos um usuário.');
        return;
      }

      if (!confirm('Tem certeza que deseja enviar a notificação para ' + usuarios.length + ' usuário(s)?')) return;

      $.ajax({
        url: '../api/notificacao/notificar_email.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ usuarios: usuarios }),
        success: function (res) {
          alert(res.mensagem);
          if (res.falhas.length > 0) {
            console.warn('Falhas:', res.falhas);
          }
        },
        error: function () {
          alert('Erro ao enviar notificações.');
        }
      });
    });
  </script>
</body>
</html>
