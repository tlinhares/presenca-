<?php
/**
 * API - Excluir/Desativar Departamento de Estoque
 * Se houver dados vinculados, apenas desativa (soft delete)
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';

// Verificar permissão usando o sistema de menus (mesmo da página)
$isAdmin = MenuPermissaoService::isAdmin();
$temAcesso = MenuPermissaoService::podeAcessar('estoque_config_departamentos');

if (!$isAdmin && !$temAcesso) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado. Você não tem permissão para gerenciar departamentos.']);
    exit;
}

try {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        throw new Exception('ID do departamento inválido');
    }
    
    // Verificar se existe
    $sql_check = "SELECT nome FROM estoque_departamentos WHERE id = ?";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Departamento não encontrado');
    }
    
    $nome = $result->fetch_assoc()['nome'];
    
    // Verificar se há dados vinculados
    $tem_dados = false;
    $motivo = '';
    
    // Verificar produtos
    $sql = "SELECT COUNT(*) as total FROM estoque_produtos WHERE id_departamento = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    if ($total > 0) {
        $tem_dados = true;
        $motivo = "Possui $total produto(s) vinculado(s)";
    }
    
    // Verificar movimentações
    if (!$tem_dados) {
        $sql = "SELECT COUNT(*) as total FROM estoque_movimentacoes WHERE id_departamento = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        if ($total > 0) {
            $tem_dados = true;
            $motivo = "Possui $total movimentação(ões) vinculada(s)";
        }
    }
    
    // Verificar requisições
    if (!$tem_dados) {
        $sql = "SELECT COUNT(*) as total FROM estoque_requisicoes WHERE id_departamento_destino = ? OR id_departamento_origem = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $id);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        if ($total > 0) {
            $tem_dados = true;
            $motivo = "Possui $total requisição(ões) vinculada(s)";
        }
    }
    
    if ($tem_dados) {
        // Soft delete - apenas desativar
        $sql = "UPDATE estoque_departamentos SET ativo = 0, atualizado_em = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => "Departamento '$nome' desativado. $motivo",
            'tipo' => 'desativado'
        ]);
    } else {
        // Excluir responsáveis primeiro
        $sql = "DELETE FROM estoque_responsaveis WHERE id_departamento = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        // Excluir localizações
        $sql = "DELETE FROM estoque_localizacoes WHERE id_departamento = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        // Excluir departamento
        $sql = "DELETE FROM estoque_departamentos WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => "Departamento '$nome' excluído com sucesso",
            'tipo' => 'excluido'
        ]);
    }

} catch (Exception $e) {
    error_log("Erro em departamentos/excluir.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();

