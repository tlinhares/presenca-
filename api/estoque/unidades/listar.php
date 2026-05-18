<?php
/**
 * API - Listar Unidades de Medida
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $apenas_ativos = !isset($_GET['todos']) || $_GET['todos'] !== 'true';
    
    $sql = "SELECT 
                u.id,
                u.nome,
                u.sigla,
                u.descricao,
                u.ativo,
                u.criado_em,
                (SELECT COUNT(*) FROM estoque_produtos p WHERE p.id_unidade = u.id AND p.ativo = 1) as total_produtos
            FROM estoque_unidades u";
    
    if ($apenas_ativos) {
        $sql .= " WHERE u.ativo = 1";
    }
    
    $sql .= " ORDER BY u.nome ASC";
    
    $result = $conn->query($sql);
    
    $unidades = [];
    while ($row = $result->fetch_assoc()) {
        $unidades[] = [
            'id' => intval($row['id']),
            'nome' => $row['nome'],
            'sigla' => $row['sigla'],
            'descricao' => $row['descricao'],
            'ativo' => (bool)$row['ativo'],
            'total_produtos' => intval($row['total_produtos']),
            'criado_em' => $row['criado_em']
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'unidades' => $unidades,
        'total' => count($unidades)
    ]);

} catch (Exception $e) {
    error_log("Erro em unidades/listar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();



