<?php
/**
 * API para listar dias fechados do refeitório
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

try {
    $sql = "SELECT * FROM dias_fechado ORDER BY data DESC";
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Erro na consulta: ' . $conn->error);
    }
    
    $dias = [];
    while ($row = $result->fetch_assoc()) {
        $dias[] = $row;
    }
    
    echo json_encode([
        'status' => 'ok',
        'dias' => $dias
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao listar dias fechados: ' . $e->getMessage()
    ]);
}
?>

