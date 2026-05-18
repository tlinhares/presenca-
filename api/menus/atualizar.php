<?php
/**
 * API para atualizar campo de um menu
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();




require_once __DIR__ . '/../conexao.php';

$menu_id = intval($_POST['menu_id'] ?? 0);
$campo = $_POST['campo'] ?? '';
$valor = intval($_POST['valor'] ?? 0);

// Campos permitidos
$campos_permitidos = ['acesso_padrao', 'requer_culto', 'requer_admin', 'ativo'];

if (!$menu_id || !in_array($campo, $campos_permitidos)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Parâmetros inválidos']);
    exit;
}

$stmt = $conn->prepare("UPDATE menus SET $campo = ? WHERE id = ?");
$stmt->bind_param("ii", $valor, $menu_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'sucesso']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => $conn->error]);
}

$stmt->close();

