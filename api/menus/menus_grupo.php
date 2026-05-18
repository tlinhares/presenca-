<?php
/**
 * API para listar menus de um grupo
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();




require_once __DIR__ . '/../conexao.php';

$grupo_id = intval($_GET['grupo_id'] ?? 0);

if (!$grupo_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Grupo não informado']);
    exit;
}

$menus = [];
$result = $conn->query("SELECT menu_id FROM grupo_menus WHERE grupo_id = $grupo_id");
while ($row = $result->fetch_assoc()) {
    $menus[] = intval($row['menu_id']);
}

echo json_encode(['status' => 'sucesso', 'menus' => $menus]);

