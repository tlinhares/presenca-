<?php
/**
 * API para criar novo grupo de acesso
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();




require_once __DIR__ . '/../conexao.php';

$nome = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$cor = $_POST['cor'] ?? '#6c757d';

if (empty($nome)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Nome é obrigatório']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO grupos_acesso (nome, descricao, cor) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $nome, $descricao, $cor);

if ($stmt->execute()) {
    echo json_encode(['status' => 'sucesso', 'id' => $conn->insert_id]);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => $conn->error]);
}

$stmt->close();

