<?php
/**
 * API para excluir dia fechado
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('gerenciamento_dias_fechado');

include_once(__DIR__ . '/../conexao.php');

// Verificar se a conexão foi estabelecida
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
    $stmt = $conn->prepare("DELETE FROM dias_fechado WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Dia fechado excluído com sucesso!'
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao excluir: ' . $conn->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao excluir dia fechado: ' . $e->getMessage()
    ]);
}
?>

