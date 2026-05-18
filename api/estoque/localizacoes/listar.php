<?php
/**
 * API - Listar Localizações de Estoque
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $departamento = isset($_GET['departamento']) ? intval($_GET['departamento']) : 0;
    $apenas_ativos = !isset($_GET['todos']) || $_GET['todos'] !== 'true';
    
    $sql = "SELECT 
                l.id,
                l.id_departamento,
                l.nome,
                l.codigo,
                l.descricao,
                l.ativo,
                l.criado_em,
                d.nome as departamento_nome
            FROM estoque_localizacoes l
            JOIN estoque_departamentos d ON l.id_departamento = d.id
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if ($apenas_ativos) {
        $sql .= " AND l.ativo = 1";
    }
    
    if ($departamento > 0) {
        $sql .= " AND l.id_departamento = ?";
        $params[] = $departamento;
        $types .= "i";
    }
    
    $sql .= " ORDER BY d.nome, l.nome";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $localizacoes = [];
    while ($row = $result->fetch_assoc()) {
        $localizacoes[] = [
            'id' => intval($row['id']),
            'id_departamento' => intval($row['id_departamento']),
            'nome' => $row['nome'],
            'codigo' => $row['codigo'],
            'descricao' => $row['descricao'],
            'ativo' => (bool)$row['ativo'],
            'departamento_nome' => $row['departamento_nome'],
            'criado_em' => $row['criado_em']
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'localizacoes' => $localizacoes,
        'total' => count($localizacoes)
    ]);

} catch (Exception $e) {
    error_log("Erro em localizacoes/listar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();



