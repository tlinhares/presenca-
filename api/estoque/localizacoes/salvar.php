<?php
/**
 * API - Salvar/Atualizar Localização de Estoque
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
    $id_departamento = isset($_POST['id_departamento']) ? intval($_POST['id_departamento']) : 0;
    $nome = trim($_POST['nome'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '');
    $codigo = empty($codigo) ? null : $codigo;
    $descricao = trim($_POST['descricao'] ?? '');
    $ativo = isset($_POST['ativo']) ? intval($_POST['ativo']) : 1;
    
    if ($id_departamento <= 0) {
        throw new Exception('Departamento é obrigatório');
    }
    
    if (empty($nome)) {
        throw new Exception('Nome é obrigatório');
    }
    
    if ($id > 0) {
        $sql = "UPDATE estoque_localizacoes SET id_departamento = ?, nome = ?, codigo = ?, descricao = ?, ativo = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssii", $id_departamento, $nome, $codigo, $descricao, $ativo, $id);
        $stmt->execute();
        $mensagem = 'Localização atualizada com sucesso';
    } else {
        $sql = "INSERT INTO estoque_localizacoes (id_departamento, nome, codigo, descricao, ativo) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssi", $id_departamento, $nome, $codigo, $descricao, $ativo);
        $stmt->execute();
        $id = $conn->insert_id;
        $mensagem = 'Localização cadastrada com sucesso';
    }
    
    echo json_encode(['status' => 'ok', 'mensagem' => $mensagem, 'id' => $id]);

} catch (Exception $e) {
    error_log("Erro em localizacoes/salvar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();



