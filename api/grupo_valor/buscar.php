<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('gerenciar_valores_refeicoes');

include_once(__DIR__ . '/../conexao.php');

if (!isset($conn) || !$conn) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro de conexão com o banco de dados']);
    exit;
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM grupo_valor WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Registro não encontrado']);
        exit;
    }

    $item = $result->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'status' => 'ok',
        'item' => $item
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar valor de refeição: ' . $e->getMessage()
    ]);
}
?>
