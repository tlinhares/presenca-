<?php
/**
 * API - Listar Movimentações de Estoque
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
    $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
    $tipo = $_GET['tipo'] ?? '';
    $departamento = isset($_GET['departamento']) ? intval($_GET['departamento']) : 0;
    $produto = isset($_GET['produto']) ? intval($_GET['produto']) : 0;
    
    $sql = "SELECT 
                m.id,
                m.tipo,
                m.quantidade,
                m.valor_unitario,
                m.observacoes,
                m.data_movimentacao,
                DATE_FORMAT(m.data_movimentacao, '%d/%m/%Y %H:%i') as data_formatada,
                p.nome as produto_nome,
                p.codigo as produto_codigo,
                u.sigla as unidade_sigla,
                d.nome as departamento_nome,
                us.nome as usuario_nome
            FROM estoque_movimentacoes m
            JOIN estoque_produtos p ON m.id_produto = p.id
            JOIN estoque_unidades u ON p.id_unidade = u.id
            LEFT JOIN estoque_departamentos d ON m.id_departamento = d.id
            JOIN usuarios us ON m.id_usuario = us.id
            WHERE DATE(m.data_movimentacao) BETWEEN ? AND ?";
    
    $params = [$data_inicio, $data_fim];
    $types = "ss";
    
    if (!empty($tipo)) {
        $sql .= " AND m.tipo = ?";
        $params[] = $tipo;
        $types .= "s";
    }
    
    if ($departamento > 0) {
        $sql .= " AND m.id_departamento = ?";
        $params[] = $departamento;
        $types .= "i";
    }
    
    if ($produto > 0) {
        $sql .= " AND m.id_produto = ?";
        $params[] = $produto;
        $types .= "i";
    }
    
    $sql .= " ORDER BY m.data_movimentacao DESC LIMIT 500";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta: ' . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $movimentacoes = [];
    while ($row = $result->fetch_assoc()) {
        $movimentacoes[] = $row;
    }
    
    echo json_encode([
        'status' => 'ok',
        'movimentacoes' => $movimentacoes,
        'total' => count($movimentacoes)
    ]);

} catch (Exception $e) {
    error_log("Erro em movimentacoes/listar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();

