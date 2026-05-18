<?php
/**
 * API para salvar grupos que têm acesso a um menu
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();




require_once __DIR__ . '/../conexao.php';

$menu_id = intval($_POST['menu_id'] ?? 0);
$grupos = $_POST['grupos'] ?? [];

if (!$menu_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Menu não informado']);
    exit;
}

// Remover todos os grupos do menu
$conn->query("DELETE FROM grupo_menus WHERE menu_id = $menu_id");

// Adicionar os grupos selecionados
if (!empty($grupos)) {
    $stmt = $conn->prepare("INSERT INTO grupo_menus (grupo_id, menu_id) VALUES (?, ?)");
    foreach ($grupos as $grupo_id) {
        $grupo_id = intval($grupo_id);
        $stmt->bind_param("ii", $grupo_id, $menu_id);
        $stmt->execute();
    }
    $stmt->close();
}

echo json_encode(['status' => 'sucesso']);

