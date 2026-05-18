<?php
/**
 * API para salvar menus de um grupo
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();




require_once __DIR__ . '/../conexao.php';

$grupo_id = intval($_POST['grupo_id'] ?? 0);
$menus = $_POST['menus'] ?? [];

if (!$grupo_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Grupo não informado']);
    exit;
}

// Remover todos os menus do grupo
$conn->query("DELETE FROM grupo_menus WHERE grupo_id = $grupo_id");

// Adicionar os menus selecionados
if (!empty($menus)) {
    $stmt = $conn->prepare("INSERT INTO grupo_menus (grupo_id, menu_id) VALUES (?, ?)");
    foreach ($menus as $menu_id) {
        $menu_id = intval($menu_id);
        $stmt->bind_param("ii", $grupo_id, $menu_id);
        $stmt->execute();
    }
    $stmt->close();
}

echo json_encode(['status' => 'sucesso']);

