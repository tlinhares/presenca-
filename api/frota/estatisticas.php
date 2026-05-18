<?php
/**
 * API de Estatísticas da Frota
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../conexao.php';

try {
    // Total de veículos ativos
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'disponivel' THEN 1 ELSE 0 END) as disponiveis,
                SUM(CASE WHEN status = 'em_uso' THEN 1 ELSE 0 END) as em_uso,
                SUM(CASE WHEN status = 'manutencao' THEN 1 ELSE 0 END) as manutencao,
                SUM(CASE WHEN status = 'inativo' THEN 1 ELSE 0 END) as inativos
            FROM frota_veiculos 
            WHERE ativo = 1";
    
    $result = $conn->query($sql);
    $stats = $result->fetch_assoc();
    
    echo json_encode([
        'status' => 'ok',
        'estatisticas' => [
            'total' => intval($stats['total']),
            'disponiveis' => intval($stats['disponiveis']),
            'em_uso' => intval($stats['em_uso']),
            'manutencao' => intval($stats['manutencao']),
            'inativos' => intval($stats['inativos'])
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar estatísticas: ' . $e->getMessage()
    ]);
}
?>




