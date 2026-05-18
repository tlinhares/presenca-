<?php
/**
 * API - Excluir/Desativar Localização de Estoque
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';

// Verificar permissão usando o sistema de menus (mesmo da página)
$isAdmin = MenuPermissaoService::isAdmin();
$temAcesso = MenuPermissaoService::podeAcessar('estoque_config_localizacoes');

if (!$isAdmin && !$temAcesso) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado. Você não tem permissão para gerenciar localizações.']);
    exit;
}

try {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Verificar se existe
    $sql = "SELECT nome FROM estoque_localizacoes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Localização não encontrada');
    }
    
    $nome = $result->fetch_assoc()['nome'];
    
    // Verificar se há produtos vinculados
    $sql = "SELECT COUNT(*) as total FROM estoque_produtos WHERE id_localizacao = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        
        if ($total > 0) {
            // Soft delete
            $sql = "UPDATE estoque_localizacoes SET ativo = 0 WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            echo json_encode([
                'status' => 'ok',
                'mensagem' => "Localização '$nome' desativada. Possui $total produto(s) vinculado(s)."
            ]);
            exit;
        }
    }
    
    // Excluir permanentemente
    $sql = "DELETE FROM estoque_localizacoes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    echo json_encode(['status' => 'ok', 'mensagem' => "Localização '$nome' excluída com sucesso"]);

} catch (Exception $e) {
    error_log("Erro em localizacoes/excluir.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();



