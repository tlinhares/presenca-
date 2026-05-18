<?php
/**
 * API para buscar uma mensagem específica
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcessoAPI('gerenciar_mensagens_whatsapp');

require_once __DIR__ . '/../conexao.php';

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID não informado']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM mensagens_padrao WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Mensagem não encontrada']);
    $stmt->close();
    exit;
}

$mensagem = $result->fetch_assoc();
$stmt->close();

echo json_encode([
    'status' => 'sucesso',
    'mensagem' => $mensagem
]);

