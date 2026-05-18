<?php
session_start();
if (isset($_SESSION['usuario_id'])) {
  if ($_SESSION['usuario_categoria'] === 'admin') {
    header('Location: painel/dashboard.php');
  } else {
    header('Location: reservas/almoco.php');
  }
  exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperar Senha - Presença AOM</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">
  <div class="container" style="max-width: 400px;">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="text-center mb-3">
          <img src="img/logo-intranet-aom.png" alt="Intranet AOM" style="max-width: 220px; width: 100%; height: auto;">
        </div>
        
        <h4 class="card-title text-center mb-4">Recuperar Senha</h4>
        
        <div class="text-center mb-4">
          <p class="text-muted">Digite seu e-mail para receber um link de recuperação de senha.</p>
        </div>
        
        <div id="mensagemRecuperacao" class="alert d-none" role="alert"></div>
        
        <form id="formRecuperacao">
          <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" class="form-control" id="email" name="email" required autofocus>
          </div>
          
          <button type="submit" class="btn btn-primary w-100 mb-3">Enviar Link de Recuperação</button>
          
          <div class="text-center">
            <a href="index.php" class="text-decoration-none">
              <i class="bi bi-arrow-left"></i> Voltar ao Login
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$('#formRecuperacao').submit(function(e) {
  e.preventDefault();
  
  var dados = $(this).serialize();
  
  $.ajax({
    url: 'api/auth/recuperar_senha.php',
    type: 'POST',
    data: dados,
    dataType: 'json',
    success: function(res) {
      if (res.status === 'ok') {
        $('#mensagemRecuperacao')
          .removeClass('d-none alert-danger')
          .addClass('alert-success')
          .text(res.mensagem);
        $('#formRecuperacao')[0].reset();
      } else {
        $('#mensagemRecuperacao')
          .removeClass('d-none alert-success')
          .addClass('alert-danger')
          .text(res.mensagem);
      }
    },
    error: function () {
      $('#mensagemRecuperacao')
        .removeClass('d-none alert-success')
        .addClass('alert-danger')
        .text('Erro ao processar solicitação.');
    }
  });
});
</script>
</body>
</html>