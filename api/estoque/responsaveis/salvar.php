<?php
/**
 * API - Salvar/Atualizar Responsável de Estoque
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';

// Verificar permissão usando o sistema de menus (mesmo da página)
$isAdmin = MenuPermissaoService::isAdmin();
$temAcesso = MenuPermissaoService::podeAcessar('estoque_config_responsaveis');

if (!$isAdmin && !$temAcesso) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado. Você não tem permissão para gerenciar responsáveis.']);
    exit;
}

try {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $id_departamento = isset($_POST['id_departamento']) ? intval($_POST['id_departamento']) : 0;
    $id_usuario = isset($_POST['id_usuario']) ? intval($_POST['id_usuario']) : 0;
    $tipo = in_array($_POST['tipo'] ?? '', ['responsavel', 'auxiliar']) ? $_POST['tipo'] : 'auxiliar';
    $ativo = isset($_POST['ativo']) ? intval($_POST['ativo']) : 1;
    
    if ($id_departamento <= 0 || $id_usuario <= 0) {
        throw new Exception('Departamento e Usuário são obrigatórios');
    }
    
    // Verificar duplicidade
    $sql_check = "SELECT id FROM estoque_responsaveis WHERE id_departamento = ? AND id_usuario = ? AND id != ?";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("iii", $id_departamento, $id_usuario, $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Este usuário já é responsável por este departamento');
    }
    
    if ($id > 0) {
        $sql = "UPDATE estoque_responsaveis SET id_departamento = ?, id_usuario = ?, tipo = ?, ativo = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisii", $id_departamento, $id_usuario, $tipo, $ativo, $id);
        $stmt->execute();
        $mensagem = 'Responsável atualizado com sucesso';
    } else {
        $sql = "INSERT INTO estoque_responsaveis (id_departamento, id_usuario, tipo, ativo) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisi", $id_departamento, $id_usuario, $tipo, $ativo);
        $stmt->execute();
        $id = $conn->insert_id;
        $mensagem = 'Responsável adicionado com sucesso';
    }
    
    echo json_encode(['status' => 'ok', 'mensagem' => $mensagem, 'id' => $id]);

} catch (Exception $e) {
    error_log("Erro em responsaveis/salvar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();



