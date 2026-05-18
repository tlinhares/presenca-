<?php
/**
 * API - Salvar/Atualizar Unidade de Medida
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';

// Verificar permissão usando o sistema de menus (mesmo da página)
$isAdmin = MenuPermissaoService::isAdmin();
$temAcesso = MenuPermissaoService::podeAcessar('estoque_config_unidades');

if (!$isAdmin && !$temAcesso) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado. Você não tem permissão para gerenciar unidades.']);
    exit;
}

try {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nome = trim($_POST['nome'] ?? '');
    $sigla = strtoupper(trim($_POST['sigla'] ?? ''));
    $descricao = trim($_POST['descricao'] ?? '');
    $ativo = isset($_POST['ativo']) ? intval($_POST['ativo']) : 1;
    
    if (empty($nome)) {
        throw new Exception('Nome da unidade é obrigatório');
    }
    
    if (empty($sigla)) {
        throw new Exception('Sigla da unidade é obrigatória');
    }
    
    // Verificar sigla única
    $sql_check = "SELECT id FROM estoque_unidades WHERE sigla = ? AND id != ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("si", $sigla, $id);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        throw new Exception('Já existe uma unidade com esta sigla');
    }
    
    if ($id > 0) {
        $sql = "UPDATE estoque_unidades SET nome = ?, sigla = ?, descricao = ?, ativo = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssii", $nome, $sigla, $descricao, $ativo, $id);
        $stmt->execute();
        $mensagem = 'Unidade atualizada com sucesso';
    } else {
        $sql = "INSERT INTO estoque_unidades (nome, sigla, descricao, ativo) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $nome, $sigla, $descricao, $ativo);
        $stmt->execute();
        $id = $conn->insert_id;
        $mensagem = 'Unidade cadastrada com sucesso';
    }
    
    echo json_encode(['status' => 'ok', 'mensagem' => $mensagem, 'id' => $id]);

} catch (Exception $e) {
    error_log("Erro em unidades/salvar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();

