<?php
/**
 * API para Buscar Veículo por ID
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../conexao.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID do veículo não informado']);
    exit;
}

// Admin pode ver veículos inativos
$isAdmin = MenuPermissaoService::podeAcessar('frota_admin_veiculos');

try {
    // Admin pode ver todos, usuário comum só vê ativos
    $sql = "SELECT * FROM frota_veiculos WHERE id = ?";
    if (!$isAdmin) {
        $sql .= " AND ativo = 1";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $veiculo = $result->fetch_assoc();
        
        echo json_encode([
            'status' => 'ok',
            'veiculo' => [
                'id' => intval($veiculo['id']),
                'placa' => $veiculo['placa'],
                'modelo' => $veiculo['modelo'],
                'marca' => $veiculo['marca'],
                'ano' => $veiculo['ano'] ? intval($veiculo['ano']) : null,
                'cor' => $veiculo['cor'],
                'km_atual' => intval($veiculo['km_atual']),
                'status' => $veiculo['status'],
                'ativo' => intval($veiculo['ativo']),
                'foto_veiculo' => $veiculo['foto_veiculo'],
                'observacoes' => $veiculo['observacoes']
            ]
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Veículo não encontrado']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar veículo: ' . $e->getMessage()
    ]);
}
?>




