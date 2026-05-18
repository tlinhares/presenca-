<?php
/**
 * API - Salvar/Atualizar Departamento de Estoque
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';

// Limpar cache antes de verificar permissão para garantir dados atualizados
MenuPermissaoService::limparCache();

// Verificar permissão usando o sistema de menus (mesmo da página)
$isAdmin = MenuPermissaoService::isAdmin();
$temAcesso = MenuPermissaoService::podeAcessar('estoque_config_departamentos');

if (!$isAdmin && !$temAcesso) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado. Você não tem permissão para gerenciar departamentos.']);
    exit;
}

try {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '');
    $codigo = empty($codigo) ? null : $codigo; // Tratar código vazio como NULL para evitar conflito UNIQUE
    $cor = trim($_POST['cor'] ?? '#667eea');
    $icone = trim($_POST['icone'] ?? 'bi-box');
    $ativo = isset($_POST['ativo']) ? intval($_POST['ativo']) : 1;
    
    // Validações
    if (empty($nome)) {
        throw new Exception('Nome do departamento é obrigatório');
    }
    
    if (strlen($nome) > 100) {
        throw new Exception('Nome deve ter no máximo 100 caracteres');
    }
    
    // Verificar código único (se informado)
    if (!empty($codigo)) {
        $sql_check = "SELECT id FROM estoque_departamentos WHERE codigo = ? AND id != ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("si", $codigo, $id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            throw new Exception('Já existe um departamento com este código');
        }
    }
    
    if ($id > 0) {
        // Atualizar
        $sql = "UPDATE estoque_departamentos SET 
                    nome = ?, 
                    descricao = ?, 
                    codigo = ?, 
                    cor = ?, 
                    icone = ?,
                    ativo = ?,
                    atualizado_em = NOW()
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssii", $nome, $descricao, $codigo, $cor, $icone, $ativo, $id);
        $stmt->execute();
        
        $mensagem = 'Departamento atualizado com sucesso';
    } else {
        // Inserir
        $sql = "INSERT INTO estoque_departamentos (nome, descricao, codigo, cor, icone, ativo) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $nome, $descricao, $codigo, $cor, $icone, $ativo);
        $stmt->execute();
        
        $id = $conn->insert_id;
        $mensagem = 'Departamento cadastrado com sucesso';
    }
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => $mensagem,
        'id' => $id
    ]);

} catch (Exception $e) {
    error_log("Erro em departamentos/salvar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();

