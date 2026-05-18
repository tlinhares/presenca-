<?php
/**
 * API - Listar Produtos para Inventário
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $inventario_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($inventario_id <= 0) {
        throw new Exception('ID do inventário inválido');
    }
    
    // Buscar departamento do inventário
    $sql_dept = "SELECT id_departamento FROM estoque_inventarios WHERE id = ?";
    $stmt_dept = $conn->prepare($sql_dept);
    if (!$stmt_dept) {
        throw new Exception('Erro ao preparar query: ' . $conn->error);
    }
    
    $stmt_dept->bind_param("i", $inventario_id);
    $stmt_dept->execute();
    $result_dept = $stmt_dept->get_result();
    
    if ($result_dept->num_rows === 0) {
        throw new Exception('Inventário não encontrado');
    }
    
    $inventario = $result_dept->fetch_assoc();
    $departamento_id = $inventario['id_departamento'];
    $stmt_dept->close();
    
    // Buscar produtos do departamento
    $sql = "SELECT 
                p.id,
                p.codigo,
                p.nome,
                p.quantidade_atual,
                u.sigla as unidade_sigla,
                u.nome as unidade_nome
            FROM estoque_produtos p
            JOIN estoque_unidades u ON p.id_unidade = u.id
            WHERE p.id_departamento = ? AND p.ativo = 1
            ORDER BY p.nome ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $departamento_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $produtos = [];
    while ($row = $result->fetch_assoc()) {
        $produtos[] = [
            'id' => intval($row['id']),
            'codigo' => $row['codigo'],
            'nome' => $row['nome'],
            'quantidade_atual' => floatval($row['quantidade_atual']),
            'unidade_sigla' => $row['unidade_sigla'],
            'unidade_nome' => $row['unidade_nome']
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'produtos' => $produtos,
        'total' => count($produtos)
    ]);
    
    $stmt->close();

} catch (Exception $e) {
    error_log("Erro em inventarios/produtos.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();

