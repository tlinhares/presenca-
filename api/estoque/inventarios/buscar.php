<?php
/**
 * API - Buscar Inventário por ID
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    $sql = "SELECT 
                i.id,
                i.id_departamento,
                i.data_inicio,
                DATE_FORMAT(i.data_inicio, '%d/%m/%Y %H:%i') as data_formatada,
                i.data_fim,
                DATE_FORMAT(i.data_fim, '%d/%m/%Y %H:%i') as data_fim_formatada,
                i.status,
                i.observacoes,
                d.nome as departamento_nome,
                u.nome as responsavel
            FROM estoque_inventarios i
            JOIN estoque_departamentos d ON i.id_departamento = d.id
            LEFT JOIN usuarios u ON i.id_usuario_inicio = u.id
            WHERE i.id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Inventário não encontrado');
    }
    
    $inventario = $result->fetch_assoc();
    
    // Buscar itens já contados
    $sql_itens = "SELECT 
                    id_produto,
                    quantidade_sistema,
                    quantidade_contada,
                    diferenca,
                    ajustado
                  FROM estoque_inventarios_itens
                  WHERE id_inventario = ?";
    
    $stmt_itens = $conn->prepare($sql_itens);
    if ($stmt_itens) {
        $stmt_itens->bind_param("i", $id);
        $stmt_itens->execute();
        $result_itens = $stmt_itens->get_result();
        
        $itens = [];
        while ($row = $result_itens->fetch_assoc()) {
            $itens[] = $row;
        }
        $inventario['itens'] = $itens;
        $stmt_itens->close();
    }
    
    echo json_encode([
        'status' => 'ok',
        'inventario' => $inventario
    ]);
    
    $stmt->close();

} catch (Exception $e) {
    error_log("Erro em inventarios/buscar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();

