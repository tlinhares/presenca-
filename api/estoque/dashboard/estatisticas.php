<?php
/**
 * API - Estatísticas do Dashboard de Estoque
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $usuario_id = $_SESSION['usuario_id'] ?? 0;
    $is_admin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
    
    // Total de produtos
    $sql_produtos = "SELECT COUNT(*) as total FROM estoque_produtos WHERE ativo = 1";
    $result = $conn->query($sql_produtos);
    $total_produtos = $result->fetch_assoc()['total'] ?? 0;
    
    // Produtos com estoque baixo
    $sql_baixo = "SELECT COUNT(*) as total FROM estoque_produtos 
                  WHERE ativo = 1 AND quantidade_atual <= quantidade_minima";
    $result = $conn->query($sql_baixo);
    $estoque_baixo = $result->fetch_assoc()['total'] ?? 0;
    
    // Requisições pendentes
    $sql_req = "SELECT COUNT(*) as total FROM estoque_requisicoes 
                WHERE status IN ('pendente', 'aprovada', 'parcial')";
    $result = $conn->query($sql_req);
    $requisicoes_pendentes = $result->fetch_assoc()['total'] ?? 0;
    
    // Movimentações hoje
    $sql_mov = "SELECT COUNT(*) as total FROM estoque_movimentacoes 
                WHERE DATE(data_movimentacao) = CURDATE()";
    $result = $conn->query($sql_mov);
    $movimentacoes_hoje = $result->fetch_assoc()['total'] ?? 0;
    
    echo json_encode([
        'status' => 'ok',
        'estatisticas' => [
            'total_produtos' => intval($total_produtos),
            'estoque_baixo' => intval($estoque_baixo),
            'requisicoes_pendentes' => intval($requisicoes_pendentes),
            'movimentacoes_hoje' => intval($movimentacoes_hoje)
        ]
    ]);

} catch (Exception $e) {
    error_log("Erro em estatisticas.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();


