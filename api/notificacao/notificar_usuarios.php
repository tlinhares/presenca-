<?php
include_once('/../conexao.php');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Notificar Usuários via WhatsApp</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 20px; }
    .table th, .table td { vertical-align: middle; }
  </style>
</head>
<body>
  <div class="container">
    <h3 class="mb-4">Notificar Usuários para Teste do Sistema</h3>

    <div class="mb-3">
      <label class="form-label">Mensagem:</label>
      <textarea class="form-control" id="mensagem" rows="4" placeholder="Digite a mensagem a ser enviada via WhatsApp..."></textarea>
    </div>

    <button class="btn btn-success mb-3" id="btnEnviar">Enviar Notificações</button>

    <table class="table table-bordered table-striped" id="tabelaUsuarios">
      <thead class="table-light">
        <tr>
          <th><input type="checkbox" id="selecionarTodos"></th>
          <th>Nome</th>
          <th>Telefone</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $res = $conn->query("SELECT id, nome, telefone FROM usuarios WHERE telefone IS NOT NULL AND telefone != '' ORDER BY nome");
        while ($row = $res->fetch_assoc()) {
          echo '<tr>
            <td><input type="checkbox" class="selecionar" data-id="' . $row['id'] . '" data-nome="' . htmlspecialchars($row['nome']) . '" data-telefone="' . htmlspecialchars($row['telefone']) . '"></td>
            <td>' . htmlspecialchars($row['nome']) . '</td>
            <td>' . htmlspecialchars($row['telefone']) . '</td>
          </tr>';
        }
        ?>
      </tbody>
    </table>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
    $('#selecionarTodos').on('change', function() {
      $('.selecionar').prop('checked', this.checked);
    });

    $('#btnEnviar').on('click', function () {
      const mensagem = $('#mensagem').val().trim();
      if (!mensagem) {
        alert('Digite a mensagem antes de enviar.');
        return;
      }

      const usuarios = [];
      $('.selecionar:checked').each(function () {
        usuarios.push({
          id: $(this).data('id'),
          nome: $(this).data('nome'),
          telefone: $(this).data('telefone')
        });
      });

      if (usuarios.length === 0) {
        alert('Selecione pelo menos um usuário.');
        return;
      }

      if (!confirm('Tem certeza que deseja enviar esta notificação para ' + usuarios.length + ' usuário(s)?')) return;

      $.ajax({
        url: '../api/notificar.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ usuarios: usuarios, mensagem: mensagem }),
        success: function (res) {
          alert(res.mensagem);
        },
        error: function () {
          alert('Erro ao enviar notificações.');
        }
      });
    });
  </script>
</body>
</html>
