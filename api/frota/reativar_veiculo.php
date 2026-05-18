<?php
/**
 * API para Reativar Veículo
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../conexao.php';

// Verificar permissão
if (!MenuPermissaoService::podeAcessar('frota_admin_veiculos')) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID do veículo não informado']);
    exit;
}

try {
    // Buscar informações do veículo
    $stmt = $conn->prepare("SELECT placa, modelo, ativo FROM frota_veiculos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $veiculo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$veiculo) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Veículo não encontrado']);
        exit;
    }
    
    if ($veiculo['ativo'] == 1) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Este veículo já está ativo']);
        exit;
    }
    
    // Reativar veículo (status volta para disponível)
    $sql = "UPDATE frota_veiculos SET ativo = 1, status = 'disponivel', atualizado_em = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => "Veículo {$veiculo['placa']} - {$veiculo['modelo']} foi reativado com sucesso!"
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao reativar veículo ID {$id}: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao reativar veículo: ' . $e->getMessage()
    ]);
}
?>



