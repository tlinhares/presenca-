<?php
header('Content-Type: application/json; charset=UTF-8');
require_once '../conexao.php';

$token = $_POST['token'] ?? '';
$novaSenha = $_POST['nova_senha'] ?? '';
$confirmarSenha = $_POST['confirmar_senha'] ?? '';

if ($token === '' || $novaSenha === '' || $confirmarSenha === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Preencha todos os campos.']);
    exit;
}

if ($novaSenha !== $confirmarSenha) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'As senhas não coincidem.']);
    exit;
}

if (strlen($novaSenha) < 6) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'A senha deve ter pelo menos 6 caracteres.']);
    exit;
}

// Verificar se o token é válido
$stmt = $conn->prepare("SELECT ts.id_usuario, ts.token, ts.expiracao, u.nome, u.email 
                       FROM tokens_senha ts 
                       INNER JOIN usuarios u ON ts.id_usuario = u.id 
                       WHERE ts.token = ? AND ts.expiracao > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Token inválido ou expirado.']);
    exit;
}

$usuario = $result->fetch_assoc();
$stmt->close();

// Atualizar senha
$senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
$stmt->bind_param("si", $senhaHash, $usuario['id_usuario']);

if (!$stmt->execute()) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar senha.']);
    exit;
}

$stmt->close();

// Deletar token usado
$stmt = $conn->prepare("DELETE FROM tokens_senha WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$stmt->close();

echo json_encode(['status' => 'ok', 'mensagem' => 'Senha redefinida com sucesso! Você será redirecionado para o login.']);

$conn->close();
?>