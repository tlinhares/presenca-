<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

include_once(__DIR__ . '/../conexao.php');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado.']);
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$nome = isset($_POST['nome_dependente']) ? trim($_POST['nome_dependente']) : '';
$parentesco = isset($_POST['parentesco_dependente']) ? trim($_POST['parentesco_dependente']) : '';
$foto_base64 = '';

if (isset($_POST['foto_base64'])) {
    $base64 = $_POST['foto_base64'];
    $foto_base64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
}

if ($nome === '' || $parentesco === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Preencha todos os campos obrigatórios.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO dependentes (id_usuario, nome, parentesco, foto_base64, ativo) VALUES (?, ?, ?, ?, 1)");
$stmt->bind_param("isss", $id_usuario, $nome, $parentesco, $foto_base64);

if ($stmt->execute()) {
    echo json_encode(['status' => 'ok', 'id' => $stmt->insert_id]);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao salvar dependente.']);
}

$stmt->close();
$conn->close();
?>