<?php
/**
 * API para criar nova mensagem padrão
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../conexao.php';

$tipo = trim($_POST['tipo'] ?? '');
$mensagem = trim($_POST['mensagem'] ?? '');
$ativo = intval($_POST['ativo'] ?? 1);

// Validações
if (empty($tipo) || empty($mensagem)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Tipo e mensagem são obrigatórios']);
    exit;
}

// Inserir mensagem
$stmt = $conn->prepare("
    INSERT INTO mensagens_padrao (tipo, mensagem, ativo) 
    VALUES (?, ?, ?)
");
$stmt->bind_param("ssi", $tipo, $mensagem, $ativo);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'sucesso',
        'id' => $conn->insert_id,
        'mensagem' => 'Mensagem criada com sucesso!'
    ]);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao criar mensagem: ' . $conn->error]);
}

$stmt->close();

