<?php
/**
 * API - Listar Responsáveis de Estoque
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $departamento = isset($_GET['departamento']) ? intval($_GET['departamento']) : 0;
    
    $sql = "SELECT 
                r.id,
                r.id_departamento,
                r.id_usuario,
                r.tipo,
                r.ativo,
                r.criado_em,
                d.nome as departamento_nome,
                u.nome as usuario_nome
            FROM estoque_responsaveis r
            JOIN estoque_departamentos d ON r.id_departamento = d.id
            JOIN usuarios u ON r.id_usuario = u.id
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if ($departamento > 0) {
        $sql .= " AND r.id_departamento = ?";
        $params[] = $departamento;
        $types .= "i";
    }
    
    $sql .= " ORDER BY d.nome, r.tipo DESC, u.nome";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $responsaveis = [];
    while ($row = $result->fetch_assoc()) {
        $responsaveis[] = [
            'id' => intval($row['id']),
            'id_departamento' => intval($row['id_departamento']),
            'id_usuario' => intval($row['id_usuario']),
            'tipo' => $row['tipo'],
            'ativo' => (bool)$row['ativo'],
            'departamento_nome' => $row['departamento_nome'],
            'usuario_nome' => $row['usuario_nome'],
            'criado_em' => $row['criado_em']
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'responsaveis' => $responsaveis,
        'total' => count($responsaveis)
    ]);

} catch (Exception $e) {
    error_log("Erro em responsaveis/listar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();



