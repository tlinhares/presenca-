<?php
/**
 * API para Buscar Manutenção por ID
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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID não informado']);
    exit;
}

try {
    $sql = "SELECT m.*, v.placa, v.modelo
            FROM frota_manutencoes m
            JOIN frota_veiculos v ON m.id_veiculo = v.id
            WHERE m.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $m = $result->fetch_assoc();
        echo json_encode([
            'status' => 'ok',
            'manutencao' => [
                'id' => intval($m['id']),
                'id_veiculo' => intval($m['id_veiculo']),
                'tipo' => $m['tipo'],
                'descricao' => $m['descricao'],
                'data_manutencao' => $m['data_manutencao'],
                'km_manutencao' => $m['km_manutencao'] ? intval($m['km_manutencao']) : null,
                'valor' => $m['valor'],
                'data_proxima_revisao' => $m['data_proxima_revisao'],
                'km_proxima_revisao' => $m['km_proxima_revisao'] ? intval($m['km_proxima_revisao']) : null,
                'status' => $m['status']
            ]
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Manutenção não encontrada']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar manutenção: ' . $e->getMessage()
    ]);
}
?>



