<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include_once(__DIR__ . '/api/conexao.php');

$token = isset($_GET['token']) ? $_GET['token'] : '';
$novaSenha = isset($_POST['nova_senha']) ? $_POST['nova_senha'] : '';

if (!$token) {
    echo 'Token inválido.';
    exit;
}

$stmt = $conn->prepare("
    SELECT u.id, u.nome, u.email, t.expiracao
    FROM tokens_senha t
    JOIN usuarios u ON u.id = t.id_usuario
    WHERE t.token = ? AND t.expiracao > NOW()
    LIMIT 1
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo 'Token expirado ou inválido.';
    exit;
}

$dados = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strlen($novaSenha) >= 6) {
    $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
    $stmtUpdate = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
    $stmtUpdate->bind_param("si", $senhaHash, $dados['id']);
    $stmtUpdate->execute();

    $conn->query("DELETE FROM tokens_senha WHERE token = '$token'");

    echo '<div style="padding:20px;text-align:center;font-family:sans-serif">
      <h3>Senha definida com sucesso!</h3>
      <p>Agora você pode acessar o sistema normalmente.</p>
      <a href="http://presenca.aom.org.br/" style="color:blue">Clique aqui para fazer login</a>
    </div>';

    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Definir Nova Senha</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 40px; background-color: #f9f9f9; font-family: sans-serif; }
    .card { max-width: 500px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
  </style>
</head>
<body>
  <div class="card">
    <div class="card-header bg-primary text-white">
      <h4 class="mb-0">Definir Nova Senha</h4>
    </div>
    <div class="card-body">
      <p><strong>Nome:</strong> <?= htmlspecialchars($dados['nome']) ?></p>
      <p><strong>E-mail:</strong> <?= htmlspecialchars($dados['email']) ?></p>
      <hr>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Digite sua nova senha:</label>
          <input type="password" name="nova_senha" class="form-control" required minlength="6">
        </div>
        <button class="btn btn-success">Definir Senha</button>
      </form>
      <p class="text-muted text-end mt-3">Este link expira em <?= date('d/m/Y H:i', strtotime($dados['expiracao'])) ?>.</p>
    </div>
  </div>
</body>
</html>
