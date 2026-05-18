<?php
/**
 * API - Listar Departamentos de Estoque
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $usuario_id = $_SESSION['usuario_id'] ?? 0;
    $is_admin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
    $apenas_meus = isset($_GET['meus']) && $_GET['meus'] === 'true';
    $apenas_ativos = !isset($_GET['todos']) || $_GET['todos'] !== 'true';
    
    $sql = "SELECT 
                d.id,
                d.nome,
                d.descricao,
                d.codigo,
                d.cor,
                d.icone,
                d.ativo,
                d.criado_em,
                (SELECT COUNT(*) FROM estoque_produtos p WHERE p.id_departamento = d.id AND p.ativo = 1) as total_produtos,
                (SELECT COUNT(*) FROM estoque_responsaveis r WHERE r.id_departamento = d.id AND r.ativo = 1) as total_responsaveis
            FROM estoque_departamentos d";
    
    $where = [];
    $params = [];
    $types = "";
    
    if ($apenas_ativos) {
        $where[] = "d.ativo = 1";
    }
    
    // Se não é admin e quer apenas seus departamentos, filtrar
    if ($apenas_meus && !$is_admin) {
        $where[] = "d.id IN (SELECT id_departamento FROM estoque_responsaveis WHERE id_usuario = ? AND ativo = 1)";
        $params[] = $usuario_id;
        $types .= "i";
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY d.nome ASC";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $departamentos = [];
    while ($row = $result->fetch_assoc()) {
        $departamentos[] = [
            'id' => intval($row['id']),
            'nome' => $row['nome'],
            'descricao' => $row['descricao'],
            'codigo' => $row['codigo'],
            'cor' => $row['cor'] ?: '#667eea',
            'icone' => $row['icone'] ?: 'bi-box',
            'ativo' => (bool)$row['ativo'],
            'total_produtos' => intval($row['total_produtos']),
            'total_responsaveis' => intval($row['total_responsaveis']),
            'criado_em' => $row['criado_em']
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'departamentos' => $departamentos,
        'total' => count($departamentos)
    ]);

} catch (Exception $e) {
    error_log("Erro em departamentos/listar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();


