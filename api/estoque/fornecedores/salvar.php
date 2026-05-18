<?php
/**
 * API - Salvar/Atualizar Fornecedor de Estoque
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
    $nome = trim($_POST['nome'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $cnpj = empty($cnpj) ? null : $cnpj;
    $contato = trim($_POST['contato'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $uf = trim($_POST['uf'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    $ativo = isset($_POST['ativo']) ? intval($_POST['ativo']) : 1;
    
    // Validações
    if (empty($nome)) {
        throw new Exception('Nome do fornecedor é obrigatório');
    }
    
    if (strlen($nome) > 200) {
        throw new Exception('Nome deve ter no máximo 200 caracteres');
    }
    
    // Verificar CNPJ único (se informado)
    if (!empty($cnpj)) {
        $sql_check = "SELECT id FROM estoque_fornecedores WHERE cnpj = ? AND id != ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("si", $cnpj, $id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            throw new Exception('Já existe um fornecedor com este CNPJ');
        }
    }
    
    if ($id > 0) {
        // Atualizar
        $sql = "UPDATE estoque_fornecedores SET 
                    razao_social = ?,
                    nome_fantasia = ?,
                    cnpj = ?,
                    contato = ?,
                    telefone = ?,
                    email = ?,
                    endereco = ?,
                    cidade = ?,
                    uf = ?,
                    observacoes = ?,
                    ativo = ?,
                    atualizado_em = NOW()
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssssii", $nome, $nome, $cnpj, $contato, $telefone, $email, $endereco, $cidade, $uf, $observacoes, $ativo, $id);
        $stmt->execute();
        
        $mensagem = 'Fornecedor atualizado com sucesso';
    } else {
        // Inserir
        $sql = "INSERT INTO estoque_fornecedores (razao_social, nome_fantasia, cnpj, contato, telefone, email, endereco, cidade, uf, observacoes, ativo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssssi", $nome, $nome, $cnpj, $contato, $telefone, $email, $endereco, $cidade, $uf, $observacoes, $ativo);
        $stmt->execute();
        
        $id = $conn->insert_id;
        $mensagem = 'Fornecedor cadastrado com sucesso';
    }
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => $mensagem,
        'id' => $id
    ]);

} catch (Exception $e) {
    error_log("Erro em fornecedores/salvar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();



