<?php
/**
 * API - Excluir/Desativar Categoria de Estoque
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';

// Verificar permissão usando o sistema de menus (mesmo da página)
$isAdmin = MenuPermissaoService::isAdmin();
$temAcesso = MenuPermissaoService::podeAcessar('estoque_config_categorias');

if (!$isAdmin && !$temAcesso) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado. Você não tem permissão para gerenciar categorias.']);
    exit;
}

try {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        throw new Exception('ID da categoria inválido');
    }
    
    // Verificar se existe
    $sql = "SELECT nome FROM estoque_categorias WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Categoria não encontrada');
    }
    
    $nome = $result->fetch_assoc()['nome'];
    
    // Verificar produtos vinculados
    $sql = "SELECT COUNT(*) as total FROM estoque_produtos WHERE id_categoria = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $total_produtos = $stmt->get_result()->fetch_assoc()['total'];
    
    // Verificar subcategorias
    $sql = "SELECT COUNT(*) as total FROM estoque_categorias WHERE id_categoria_pai = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $total_subcategorias = $stmt->get_result()->fetch_assoc()['total'];
    
    if ($total_produtos > 0 || $total_subcategorias > 0) {
        // Soft delete
        $sql = "UPDATE estoque_categorias SET ativo = 0, atualizado_em = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $motivo = [];
        if ($total_produtos > 0) $motivo[] = "$total_produtos produto(s)";
        if ($total_subcategorias > 0) $motivo[] = "$total_subcategorias subcategoria(s)";
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => "Categoria '$nome' desativada. Possui " . implode(' e ', $motivo) . " vinculado(s).",
            'tipo' => 'desativado'
        ]);
    } else {
        // Excluir
        $sql = "DELETE FROM estoque_categorias WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => "Categoria '$nome' excluída com sucesso",
            'tipo' => 'excluido'
        ]);
    }

} catch (Exception $e) {
    error_log("Erro em categorias/excluir.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();

