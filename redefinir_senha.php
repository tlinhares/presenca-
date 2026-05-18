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

require_once 'api/conexao.php';

$token = $_GET['token'] ?? '';
$erro = '';
$usuario = null;

if ($token) {
    // Verificar se o token é válido
    $stmt = $conn->prepare("SELECT ts.id_usuario, ts.token, ts.expiracao, u.nome, u.email 
                           FROM tokens_senha ts 
                           INNER JOIN usuarios u ON ts.id_usuario = u.id 
                           WHERE ts.token = ? AND ts.expiracao > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $erro = 'Token inválido ou expirado. Solicite uma nova recuperação de senha.';
    } else {
        $usuario = $result->fetch_assoc();
    }
    $stmt->close();
} else {
    $erro = 'Token não fornecido.';
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Redefinir Senha - Presença AOM</title>
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
        
        <h4 class="card-title text-center mb-4">Redefinir Senha</h4>
        
        <?php if ($erro): ?>
          <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($erro); ?>
            <div class="mt-3">
              <a href="recuperar_senha.php" class="btn btn-primary btn-sm">Nova Recuperação</a>
            </div>
          </div>
        <?php else: ?>
          <div class="text-center mb-4">
            <p class="text-muted">Olá, <strong><?php echo htmlspecialchars($usuario['nome']); ?></strong></p>
            <p class="text-muted">Digite sua nova senha abaixo:</p>
          </div>
          
          <div id="mensagemRedefinicao" class="alert d-none" role="alert"></div>
          
          <form id="formRedefinicao">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="mb-3">
              <label for="nova_senha" class="form-label">Nova Senha</label>
              <input type="password" class="form-control" id="nova_senha" name="nova_senha" required minlength="6">
              <div class="form-text">Mínimo 6 caracteres</div>
            </div>
            
            <div class="mb-3">
              <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
              <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required minlength="6">
            </div>
            
            <button type="submit" class="btn btn-primary w-100 mb-3">Redefinir Senha</button>
            
            <div class="text-center">
              <a href="index.php" class="text-decoration-none">
                <i class="bi bi-arrow-left"></i> Voltar ao Login
              </a>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$('#formRedefinicao').submit(function(e) {
  e.preventDefault();
  
  var novaSenha = $('#nova_senha').val();
  var confirmarSenha = $('#confirmar_senha').val();
  
  if (novaSenha !== confirmarSenha) {
    $('#mensagemRedefinicao')
      .removeClass('d-none alert-success')
      .addClass('alert-danger')
      .text('As senhas não coincidem.');
    return;
  }
  
  if (novaSenha.length < 6) {
    $('#mensagemRedefinicao')
      .removeClass('d-none alert-success')
      .addClass('alert-danger')
      .text('A senha deve ter pelo menos 6 caracteres.');
    return;
  }
  
  var dados = $(this).serialize();
  
  $.ajax({
    url: 'api/auth/redefinir_senha.php',
    type: 'POST',
    data: dados,
    dataType: 'json',
    success: function(res) {
      if (res.status === 'ok') {
        $('#mensagemRedefinicao')
          .removeClass('d-none alert-danger')
          .addClass('alert-success')
          .text(res.mensagem);
        
        setTimeout(function() {
          window.location.href = 'index.php';
        }, 3000);
      } else {
        $('#mensagemRedefinicao')
          .removeClass('d-none alert-success')
          .addClass('alert-danger')
          .text(res.mensagem);
      }
    },
    error: function () {
      $('#mensagemRedefinicao')
        .removeClass('d-none alert-success')
        .addClass('alert-danger')
        .text('Erro ao processar solicitação.');
    }
  });
});
</script>
</body>
</html>