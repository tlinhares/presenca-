<?php
/**
 * API para buscar um dia fechado específico
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

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM dias_fechado WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dia fechado não encontrado']);
        exit;
    }
    
    $dia = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'status' => 'ok',
        'dia' => $dia
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar dia fechado: ' . $e->getMessage()
    ]);
}
?>

