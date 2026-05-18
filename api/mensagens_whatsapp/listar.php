<?php
/**
 * API para listar mensagens padrão do WhatsApp
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcessoAPI('gerenciar_mensagens_whatsapp');

require_once __DIR__ . '/../conexao.php';

$tipo = $_GET['tipo'] ?? '';

$sql = "SELECT * FROM mensagens_padrao";
if ($tipo) {
    $stmt = $conn->prepare("SELECT * FROM mensagens_padrao WHERE tipo = ? ORDER BY tipo, id");
    $stmt->bind_param("s", $tipo);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM mensagens_padrao ORDER BY tipo, id");
}

$mensagens = [];
while ($row = $result->fetch_assoc()) {
    $mensagens[] = $row;
}

if (isset($stmt)) {
    $stmt->close();
}

echo json_encode([
    'status' => 'sucesso',
    'mensagens' => $mensagens,
    'total' => count($mensagens)
]);

