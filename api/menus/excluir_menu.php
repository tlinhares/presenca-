<?php
/**
 * API para excluir menu
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../conexao.php';

$menu_id = intval($_POST['menu_id'] ?? 0);

// Validações
if ($menu_id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID do menu inválido']);
    exit;
}

// Verificar se o menu existe
$stmt = $conn->prepare("SELECT id, nome FROM menus WHERE id = ?");
$stmt->bind_param("i", $menu_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Menu não encontrado']);
    exit;
}
$menu = $result->fetch_assoc();
$stmt->close();

// Primeiro, remover as associações do menu com grupos
$stmt = $conn->prepare("DELETE FROM grupo_menus WHERE menu_id = ?");
$stmt->bind_param("i", $menu_id);
$stmt->execute();
$stmt->close();

// Depois, excluir o menu
$stmt = $conn->prepare("DELETE FROM menus WHERE id = ?");
$stmt->bind_param("i", $menu_id);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'sucesso', 
        'mensagem' => "Menu '{$menu['nome']}' excluído com sucesso!"
    ]);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao excluir menu: ' . $conn->error]);
}

$stmt->close();





