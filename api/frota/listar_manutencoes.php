<?php
/**
 * API para Listar Manutenções
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../conexao.php';

if (!MenuPermissaoService::podeAcessar('frota_admin_manutencoes')) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado']);
    exit;
}

$veiculo_id = isset($_GET['veiculo_id']) && !empty($_GET['veiculo_id']) ? intval($_GET['veiculo_id']) : null;
$tipo = isset($_GET['tipo']) && !empty($_GET['tipo']) ? $_GET['tipo'] : null;
$status = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : null;

try {
    $sql = "SELECT m.*, v.placa, v.modelo, v.marca,
                   DATE_FORMAT(m.data_manutencao, '%d/%m/%Y') as data_formatada
            FROM frota_manutencoes m
            JOIN frota_veiculos v ON m.id_veiculo = v.id
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if ($veiculo_id) {
        $sql .= " AND m.id_veiculo = ?";
        $params[] = $veiculo_id;
        $types .= "i";
    }
    if ($tipo) {
        $sql .= " AND m.tipo = ?";
        $params[] = $tipo;
        $types .= "s";
    }
    if ($status) {
        $sql .= " AND m.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $sql .= " ORDER BY m.data_manutencao DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $manutencoes = [];
    while ($row = $result->fetch_assoc()) {
        $manutencoes[] = [
            'id' => intval($row['id']),
            'id_veiculo' => intval($row['id_veiculo']),
            'placa' => $row['placa'],
            'modelo' => $row['modelo'],
            'tipo' => $row['tipo'],
            'descricao' => $row['descricao'],
            'data_manutencao' => $row['data_manutencao'],
            'data_formatada' => $row['data_formatada'],
            'km_manutencao' => $row['km_manutencao'] ? intval($row['km_manutencao']) : null,
            'valor' => $row['valor'],
            'status' => $row['status']
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'manutencoes' => $manutencoes
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao listar manutenções: ' . $e->getMessage()
    ]);
}
?>



