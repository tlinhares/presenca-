<?php
/**
 * API para Listar Entidades
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../conexao.php';

try {
    $sql = "SELECT entidade_id as id, entidade_nome as nome FROM entidade ORDER BY entidade_nome";
    $result = $conn->query($sql);
    
    $entidades = [];
    while ($row = $result->fetch_assoc()) {
        $entidades[] = [
            'id' => intval($row['id']),
            'nome' => $row['nome']
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'entidades' => $entidades
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao listar entidades: ' . $e->getMessage()
    ]);
}
?>



