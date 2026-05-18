<?php
include_once(__DIR__ . '/../conexao.php');
header('Content-Type: application/json');

// Coleta e validação dos dados
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$categoria = isset($_POST['categoria']) ? intval($_POST['categoria']) : 0;
$qrcode = isset($_POST['qrcode']) ? trim($_POST['qrcode']) : null;

if ($id <= 0 || empty($nome) || empty($email) || $categoria <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos']);
    exit;
}

// Atualizar dados no banco
$stmt = $conn->prepare("UPDATE usuarios SET nome = ?, email = ?, categoria_usuario = ?, qrcode = ? WHERE id = ?");
$stmt->bind_param("ssisi", $nome, $email, $categoria, $qrcode, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar usuário']);
}
?>
