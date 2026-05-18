<?php
/**
 * API para listar grupos de um usuário
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();




require_once __DIR__ . '/../conexao.php';

$usuario_id = intval($_GET['usuario_id'] ?? 0);

if (!$usuario_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não informado']);
    exit;
}

// Verificar se é admin
$result = $conn->query("SELECT categoria FROM usuarios WHERE id = $usuario_id");
$usuario = $result->fetch_assoc();
$categoria = $usuario['categoria'] ?? '';

// Buscar grupos do usuário
$grupos = [];
$result = $conn->query("SELECT grupo_id FROM usuario_grupos WHERE usuario_id = $usuario_id");
while ($row = $result->fetch_assoc()) {
    $grupos[] = intval($row['grupo_id']);
}

echo json_encode([
    'status' => 'sucesso', 
    'grupos' => $grupos,
    'categoria' => $categoria
]);

