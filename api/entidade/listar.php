<?php
/**
 * API para listar entidades
 */
header('Content-Type: application/json; charset=UTF-8');
include_once __DIR__ . '/../conexao.php';

try {
    $sql = "SELECT entidade_id as id, entidade_nome as nome FROM entidade ORDER BY entidade_nome";
    $result = $conn->query($sql);
    
    $entidades = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $entidades[] = [
                'id' => $row['id'],
                'nome' => $row['nome']
            ];
        }
    }
    
    echo json_encode([
        'status' => 'sucesso',
        'data' => $entidades
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao listar entidades: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro interno do servidor'
    ]);
}

$conn->close();
?>
