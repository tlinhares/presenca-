<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
if (isset($_SESSION['usuario_id'])) {
  // Todos os usuários (admin e funcionário) vão para resumo.php
  header('Location: resumo.php');
  exit;
}
?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Presença AOM</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">
  <div class="container" style="max-width: 400px;">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="text-center mb-3">
          <img src="img/logo-intranet-aom.png" alt="Intranet AOM" style="max-width: 220px; width: 100%; height: auto;">
        </div>
        
        <h4 class="card-title text-center mb-4">Login</h4>

        <div id="mensagemLogin" class="alert d-none" role="alert"></div>

        <form id="formLogin">
          <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" class="form-control" id="email" name="email" required autofocus>
          </div>
          <div class="mb-3">
            <label for="senha" class="form-label">Senha</label>
            <input type="password" class="form-control" id="senha" name="senha" required>
          </div>
          <button type="submit" class="btn btn-primary w-100">Entrar</button>
          
          <div class="text-center mt-3">
            <a href="recuperar_senha.php" class="text-decoration-none">
              Esqueceu sua senha?
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$('#formLogin').submit(function(e) {
  e.preventDefault();
  var dados = $(this).serialize();

  $.ajax({
    url: 'api/auth/login.php',
    type: 'POST',
    data: dados,
    dataType: 'json',
    success: function(res) {
      if (res.status === 'ok') {
        // Todos os usuários (admin e funcionário) vão para resumo.php
        window.location.href = 'resumo.php';
      } else {
        $('#mensagemLogin')
          .removeClass('d-none alert-success')
          .addClass('alert-danger')
          .text(res.mensagem);
      }
    },
    error: function () {
      $('#mensagemLogin')
        .removeClass('d-none alert-success')
        .addClass('alert-danger')
        .text('Erro ao tentar logar.');
    }
  });
});
</script>
</body>
</html>
