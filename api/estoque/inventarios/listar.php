<?php
/**
 * API - Listar Inventários
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $departamento = isset($_GET['departamento']) ? intval($_GET['departamento']) : 0;
    $status = $_GET['status'] ?? '';
    
    $sql = "SELECT 
                i.id,
                i.data_inicio,
                DATE_FORMAT(i.data_inicio, '%d/%m/%Y') as data_formatada,
                i.data_fim,
                DATE_FORMAT(i.data_fim, '%d/%m/%Y') as data_fim_formatada,
                i.status,
                i.observacoes,
                d.nome as departamento_nome,
                u.nome as responsavel,
                (SELECT COUNT(*) FROM estoque_inventarios_itens WHERE id_inventario = i.id) as total_itens
            FROM estoque_inventarios i
            JOIN estoque_departamentos d ON i.id_departamento = d.id
            LEFT JOIN usuarios u ON i.id_usuario_inicio = u.id
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if ($departamento > 0) {
        $sql .= " AND i.id_departamento = ?";
        $params[] = $departamento;
        $types .= "i";
    }
    
    if (!empty($status)) {
        $sql .= " AND i.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $sql .= " ORDER BY i.data_inicio DESC, i.id DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar query: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    
    $inventarios = [];
    while ($row = $result->fetch_assoc()) {
        $inventarios[] = $row;
    }
    
    echo json_encode([
        'status' => 'ok',
        'inventarios' => $inventarios,
        'total' => count($inventarios)
    ]);

} catch (Exception $e) {
    error_log("Erro em inventarios/listar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();



