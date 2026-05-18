<?php
/**
 * API - Salvar/Atualizar Categoria de Estoque
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
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $id_categoria_pai = !empty($_POST['id_categoria_pai']) ? intval($_POST['id_categoria_pai']) : null;
    $cor = trim($_POST['cor'] ?? '#6c757d');
    $icone = trim($_POST['icone'] ?? 'bi-tag');
    $ordem = isset($_POST['ordem']) ? intval($_POST['ordem']) : 0;
    $ativo = isset($_POST['ativo']) ? intval($_POST['ativo']) : 1;
    
    // Validações
    if (empty($nome)) {
        throw new Exception('Nome da categoria é obrigatório');
    }
    
    if (strlen($nome) > 100) {
        throw new Exception('Nome deve ter no máximo 100 caracteres');
    }
    
    // Evitar que a categoria seja pai dela mesma
    if ($id > 0 && $id_categoria_pai === $id) {
        throw new Exception('Uma categoria não pode ser pai de si mesma');
    }
    
    if ($id > 0) {
        // Atualizar
        $sql = "UPDATE estoque_categorias SET 
                    nome = ?, 
                    descricao = ?, 
                    id_categoria_pai = ?, 
                    cor = ?, 
                    icone = ?,
                    ordem = ?,
                    ativo = ?,
                    atualizado_em = NOW()
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssissiii", $nome, $descricao, $id_categoria_pai, $cor, $icone, $ordem, $ativo, $id);
        $stmt->execute();
        
        $mensagem = 'Categoria atualizada com sucesso';
    } else {
        // Inserir
        $sql = "INSERT INTO estoque_categorias (nome, descricao, id_categoria_pai, cor, icone, ordem, ativo) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssissii", $nome, $descricao, $id_categoria_pai, $cor, $icone, $ordem, $ativo);
        $stmt->execute();
        
        $id = $conn->insert_id;
        $mensagem = 'Categoria cadastrada com sucesso';
    }
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => $mensagem,
        'id' => $id
    ]);

} catch (Exception $e) {
    error_log("Erro em categorias/salvar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();

