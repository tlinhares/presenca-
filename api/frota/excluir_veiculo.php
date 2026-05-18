<?php
/**
 * API para Excluir/Desativar Veículo
 * 
 * REGRAS DE INTEGRIDADE:
 * - Se o veículo está em uso: NÃO permite excluir nem desativar
 * - Se o veículo tem histórico (utilizações, manutenções, abastecimentos, checklist): 
 *   apenas DESATIVA (ativo = 0, status = 'inativo')
 * - Se o veículo NÃO tem nenhum histórico: permite exclusão permanente
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
    $stmt_veiculo = $conn->prepare("SELECT placa, modelo FROM frota_veiculos WHERE id = ?");
    $stmt_veiculo->bind_param("i", $id);
    $stmt_veiculo->execute();
    $veiculo = $stmt_veiculo->get_result()->fetch_assoc();
    $stmt_veiculo->close();
    
    if (!$veiculo) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Veículo não encontrado']);
        exit;
    }
    
    $placa = $veiculo['placa'];
    $modelo = $veiculo['modelo'];
    
    // ========================================
    // 1. Verificar se há utilizações ATIVAS (em_andamento)
    // ========================================
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM frota_utilizacoes WHERE id_veiculo = ? AND status = 'em_andamento'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['total'] > 0) {
        echo json_encode([
            'status' => 'erro', 
            'mensagem' => "O veículo {$placa} está em uso e não pode ser excluído. Aguarde a devolução."
        ]);
        exit;
    }
    
    // ========================================
    // 2. Verificar histórico em TODAS as tabelas relacionadas
    // ========================================
    $historico = [];
    
    // Utilizações
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM frota_utilizacoes WHERE id_veiculo = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $qtd_utilizacoes = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    if ($qtd_utilizacoes > 0) {
        $historico[] = "{$qtd_utilizacoes} utilização(ões)";
    }
    
    // Manutenções
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM frota_manutencoes WHERE id_veiculo = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $qtd_manutencoes = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    if ($qtd_manutencoes > 0) {
        $historico[] = "{$qtd_manutencoes} manutenção(ões)";
    }
    
    // Abastecimentos
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM frota_abastecimentos WHERE id_veiculo = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $qtd_abastecimentos = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    if ($qtd_abastecimentos > 0) {
        $historico[] = "{$qtd_abastecimentos} abastecimento(s)";
    }
    
    // Checklists (vinculados via utilização)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM frota_checklist c 
        INNER JOIN frota_utilizacoes u ON c.id_utilizacao = u.id 
        WHERE u.id_veiculo = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $qtd_checklists = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    if ($qtd_checklists > 0) {
        $historico[] = "{$qtd_checklists} checklist(s)";
    }
    
    // ========================================
    // 3. Decidir ação: Desativar ou Excluir
    // ========================================
    $tem_historico = count($historico) > 0;
    
    if ($tem_historico) {
        // TEM HISTÓRICO: Apenas desativar (soft delete)
        $sql = "UPDATE frota_veiculos SET ativo = 0, status = 'inativo', atualizado_em = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        $historico_texto = implode(', ', $historico);
        
        echo json_encode([
            'status' => 'ok',
            'acao' => 'desativado',
            'mensagem' => "Veículo {$placa} foi DESATIVADO (não pode ser excluído pois possui: {$historico_texto})",
            'detalhes' => [
                'utilizacoes' => $qtd_utilizacoes,
                'manutencoes' => $qtd_manutencoes,
                'abastecimentos' => $qtd_abastecimentos,
                'checklists' => $qtd_checklists
            ]
        ]);
    } else {
        // NÃO TEM HISTÓRICO: Pode excluir permanentemente
        $sql = "DELETE FROM frota_veiculos WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode([
            'status' => 'ok',
            'acao' => 'excluido',
            'mensagem' => "Veículo {$placa} - {$modelo} foi excluído permanentemente"
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erro ao excluir veículo ID {$id}: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao processar exclusão do veículo: ' . $e->getMessage()
    ]);
}
?>



