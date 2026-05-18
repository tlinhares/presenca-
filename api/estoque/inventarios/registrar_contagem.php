<?php
/**
 * API - Registrar Contagem de Produto no Inventário
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $id_inventario = isset($_POST['id_inventario']) ? intval($_POST['id_inventario']) : 0;
    $id_produto = isset($_POST['id_produto']) ? intval($_POST['id_produto']) : 0;
    $quantidade_sistema = isset($_POST['quantidade_sistema']) ? floatval($_POST['quantidade_sistema']) : 0;
    $quantidade_contada = isset($_POST['quantidade_contada']) ? floatval($_POST['quantidade_contada']) : null;
    $diferenca = isset($_POST['diferenca']) ? floatval($_POST['diferenca']) : 0;
    
    if ($id_inventario <= 0 || $id_produto <= 0) {
        throw new Exception('Dados inválidos');
    }
    
    // Verificar se já existe registro
    $sql_check = "SELECT id FROM estoque_inventarios_itens 
                  WHERE id_inventario = ? AND id_produto = ?";
    $stmt_check = $conn->prepare($sql_check);
    if (!$stmt_check) {
        throw new Exception('Erro ao preparar query: ' . $conn->error);
    }
    
    $stmt_check->bind_param("ii", $id_inventario, $id_produto);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $stmt_check->close();
    
    if ($result_check->num_rows > 0) {
        // Atualizar registro existente
        $sql = "UPDATE estoque_inventarios_itens SET
                    quantidade_contada = ?,
                    diferenca = ?
                WHERE id_inventario = ? AND id_produto = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Erro ao preparar query: ' . $conn->error);
        }
        
        $stmt->bind_param("ddii", $quantidade_contada, $diferenca, $id_inventario, $id_produto);
    } else {
        // Inserir novo registro
        $sql = "INSERT INTO estoque_inventarios_itens 
                (id_inventario, id_produto, quantidade_sistema, quantidade_contada, diferenca)
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Erro ao preparar query: ' . $conn->error);
        }
        
        $stmt->bind_param("iiddd", $id_inventario, $id_produto, $quantidade_sistema, $quantidade_contada, $diferenca);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar query: ' . $stmt->error);
    }
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Contagem registrada com sucesso'
    ]);
    
    $stmt->close();

} catch (Exception $e) {
    error_log("Erro em inventarios/registrar_contagem.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();

