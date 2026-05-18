<?php
/**
 * API - Alertas de Estoque Baixo
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $sql = "SELECT 
                p.id,
                p.codigo,
                p.nome,
                p.quantidade_atual,
                p.quantidade_minima,
                p.quantidade_ideal,
                u.sigla as unidade,
                d.nome as departamento,
                d.id as id_departamento
            FROM estoque_produtos p
            JOIN estoque_unidades u ON p.id_unidade = u.id
            JOIN estoque_departamentos d ON p.id_departamento = d.id
            WHERE p.ativo = 1 AND p.quantidade_atual <= p.quantidade_minima
            ORDER BY p.quantidade_atual ASC, p.nome ASC";
    
    $result = $conn->query($sql);
    
    $alertas = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $alertas[] = [
                'id' => intval($row['id']),
                'codigo' => $row['codigo'],
                'nome' => $row['nome'],
                'quantidade_atual' => floatval($row['quantidade_atual']),
                'quantidade_minima' => floatval($row['quantidade_minima']),
                'quantidade_ideal' => floatval($row['quantidade_ideal']),
                'unidade' => $row['unidade'],
                'departamento' => $row['departamento'],
                'id_departamento' => intval($row['id_departamento'])
            ];
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'alertas' => $alertas,
        'total' => count($alertas)
    ]);

} catch (Exception $e) {
    error_log("Erro em alertas.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();


