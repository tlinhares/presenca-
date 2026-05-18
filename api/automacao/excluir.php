<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();


include_once(__DIR__ . '/../conexao.php');

// Verificar se a conexão foi estabelecida
if (!isset($conn) || !$conn) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro de conexão com o banco de dados']);
    exit;
}

// Verificar se a sessão está ativa
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

// Verificar se é admin


// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;
    
    if (!$id) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID da automação não fornecido']);
        exit;
    }
    
    // Verificar se a automação existe
    $stmt = $conn->prepare("SELECT id FROM automacoes_relatorios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Automação não encontrada']);
        exit;
    }
    
    $stmt->close();
    
    // Excluir automação
    $stmt = $conn->prepare("DELETE FROM automacoes_relatorios WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => 'Automação excluída com sucesso!'
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao excluir automação: ' . $conn->error]);
    }
    
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}
?>
