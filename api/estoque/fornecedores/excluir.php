<?php
/**
 * API - Excluir/Desativar Fornecedor de Estoque
 * Se houver dados vinculados, apenas desativa (soft delete)
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';

// Verificar permissão usando o sistema de menus (mesmo da página)
$isAdmin = MenuPermissaoService::isAdmin();
$temAcesso = MenuPermissaoService::podeAcessar('estoque_config_fornecedores');

if (!$isAdmin && !$temAcesso) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado. Você não tem permissão para gerenciar fornecedores.']);
    exit;
}

try {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        throw new Exception('ID do fornecedor inválido');
    }
    
    // Verificar se existe
    $sql_check = "SELECT COALESCE(nome_fantasia, razao_social) as nome FROM estoque_fornecedores WHERE id = ?";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Fornecedor não encontrado');
    }
    
    $nome = $result->fetch_assoc()['nome'];
    
    // Verificar se há dados vinculados (notas fiscais)
    $tem_dados = false;
    $motivo = '';
    
    $sql = "SELECT COUNT(*) as total FROM estoque_notas_fiscais WHERE id_fornecedor = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    if ($total > 0) {
        $tem_dados = true;
        $motivo = "Possui $total nota(s) fiscal(is) vinculada(s)";
    }
    
    // Verificar movimentações
    if (!$tem_dados) {
        $sql = "SELECT COUNT(*) as total FROM estoque_movimentacoes WHERE id_fornecedor = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'];
            if ($total > 0) {
                $tem_dados = true;
                $motivo = "Possui $total movimentação(ões) vinculada(s)";
            }
        }
    }
    
    if ($tem_dados) {
        // Soft delete - apenas desativar
        $sql = "UPDATE estoque_fornecedores SET ativo = 0, atualizado_em = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => "Fornecedor '$nome' desativado. $motivo",
            'tipo' => 'desativado'
        ]);
    } else {
        // Excluir permanentemente
        $sql = "DELETE FROM estoque_fornecedores WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => "Fornecedor '$nome' excluído com sucesso",
            'tipo' => 'excluido'
        ]);
    }

} catch (Exception $e) {
    error_log("Erro em fornecedores/excluir.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();



