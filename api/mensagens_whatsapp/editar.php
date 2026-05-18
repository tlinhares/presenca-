<?php
/**
 * API para editar mensagem padrão
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../conexao.php';

$id = intval($_POST['id'] ?? 0);
$tipo = trim($_POST['tipo'] ?? '');
$mensagem = trim($_POST['mensagem'] ?? '');
$ativo = intval($_POST['ativo'] ?? 1);

// Validações
if (!$id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID não informado']);
    exit;
}

if (empty($tipo) || empty($mensagem)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Tipo e mensagem são obrigatórios']);
    exit;
}

// Atualizar mensagem
$stmt = $conn->prepare("
    UPDATE mensagens_padrao 
    SET tipo = ?, mensagem = ?, ativo = ? 
    WHERE id = ?
");
$stmt->bind_param("ssii", $tipo, $mensagem, $ativo, $id);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Mensagem atualizada com sucesso!'
    ]);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar mensagem: ' . $conn->error]);
}

$stmt->close();

