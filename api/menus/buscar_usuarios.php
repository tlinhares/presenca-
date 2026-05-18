<?php
/**
 * API para buscar usuários
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();




require_once __DIR__ . '/../conexao.php';

$termo = trim($_GET['termo'] ?? '');

if (strlen($termo) < 2) {
    echo json_encode(['status' => 'sucesso', 'usuarios' => []]);
    exit;
}

$termo = "%$termo%";
$stmt = $conn->prepare("
    SELECT id, nome, email, categoria 
    FROM usuarios 
    WHERE ativo = 1 AND (nome LIKE ? OR email LIKE ?)
    ORDER BY nome
    LIMIT 20
");
$stmt->bind_param("ss", $termo, $termo);
$stmt->execute();
$result = $stmt->get_result();

$usuarios = [];
while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

$stmt->close();
echo json_encode(['status' => 'sucesso', 'usuarios' => $usuarios]);

