<?php
/**
 * API para listar grupos que têm acesso a um menu
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();




require_once __DIR__ . '/../conexao.php';

$menu_id = intval($_GET['menu_id'] ?? 0);

if (!$menu_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Menu não informado']);
    exit;
}

$grupos = [];
$result = $conn->query("SELECT grupo_id FROM grupo_menus WHERE menu_id = $menu_id");
while ($row = $result->fetch_assoc()) {
    $grupos[] = intval($row['grupo_id']);
}

echo json_encode(['status' => 'sucesso', 'grupos' => $grupos]);

