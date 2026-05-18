<?php
/**
 * API para excluir mensagem padrão
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../conexao.php';

$id = intval($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID não informado']);
    exit;
}

// Excluir mensagem
$stmt = $conn->prepare("DELETE FROM mensagens_padrao WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Mensagem excluída com sucesso!'
    ]);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao excluir mensagem: ' . $conn->error]);
}

$stmt->close();

