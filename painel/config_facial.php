<?php
// painel/config_facial.php
require_once __DIR__ . '/../api/conexao.php';

function obter_config($chave, $padrao = '') {
    global $conn;
    $stmt = $conn->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
    if ($stmt) {
        $stmt->bind_param("s", $chave);
        $stmt->execute();
        $stmt->bind_result($valor);
        if ($stmt->fetch()) {
            $stmt->close();
            return $valor;
        }
        $stmt->close();
    }
    return $padrao;
}

$ip_dispositivo = obter_config('ip_dispositivo_facial', '');
$porta_dispositivo = obter_config('porta_dispositivo_facial', '80');
$usuario_dispositivo = obter_config('usuario_dispositivo_facial', 'admin');
$senha_dispositivo = obter_config('senha_dispositivo_facial', '');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Configuração do Dispositivo Facial</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="mb-4">
    <a href="index.php" class="btn btn-outline-secondary">← Voltar para o Painel</a>
  </div>

  <h3 class="mb-4">Configuração do Dispositivo Facial</h3>

  <div class="card shadow-sm">
    <div class="card-header">
      <strong>Configurações</strong>
    </div>
    <div class="card-body">
      <form id="formConfig" class="row g-3">
        <div class="col-md-6">
          <label class="form-label">IP do Dispositivo</label>
          <input type="text" class="form-control" name="ip_dispositivo_facial" value="<?= htmlspecialchars($ip_dispositivo) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Porta</label>
          <input type="text" class="form-control" name="porta_dispositivo_facial" value="<?= htmlspecialchars($porta_dispositivo) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Usuário</label>
          <input type="text" class="form-control" name="usuario_dispositivo_facial" value="<?= htmlspecialchars($usuario_dispositivo) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Senha</label>
          <input type="text" class="form-control" name="senha_dispositivo_facial" value="<?= htmlspecialchars($senha_dispositivo) ?>" required>
        </div>

        <div class="col-12">
          <button type="button" id="btnSalvar" class="btn btn-primary">Salvar</button>
        </div>
      </form>
      <div id="mensagem" class="mt-3"></div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$('#btnSalvar').click(function () {
  $('#mensagem').removeClass().text('Salvando...');

  let configs = $('#formConfig').serializeArray();
  let total = configs.length;
  let ok = 0, erros = [];

  configs.forEach(function (item) {
    let payload = {
      chave: item.name,
      valor: item.value,
      descricao: 'Campo ' + item.name.replace('_', ' ')
    };

    $.ajax({
      url: '../api/config/salvar_config.php',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(payload),
      success: function (res) {
        ok++;
        if (res.status !== 'ok') {
          erros.push(res.mensagem);
        }
        if (ok === total) {
          if (erros.length > 0) {
            $('#mensagem').addClass('alert alert-danger').html('Erros:<br>' + erros.join('<br>'));
          } else {
            $('#mensagem').addClass('alert alert-success').text('Todas as configurações foram salvas com sucesso.');
          }
        }
      },
      error: function () {
        erros.push('Erro ao salvar ' + item.name);
        ok++;
        if (ok === total) {
          $('#mensagem').addClass('alert alert-danger').html('Erros:<br>' + erros.join('<br>'));
        }
      }
    });
  });
});
</script>
</body>
</html>
