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

$dados = json_decode(file_get_contents('php://input'), true);
$id = intval($dados['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM grupo_valor WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Valor de refeição excluído com sucesso!'
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao excluir: ' . $conn->error]);
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao excluir valor de refeição: ' . $e->getMessage()
    ]);
}
?>
