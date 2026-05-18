<?php
/**
 * API para salvar grupos de um usuário
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();




require_once __DIR__ . '/../conexao.php';

$usuario_id = intval($_POST['usuario_id'] ?? 0);
$grupos = $_POST['grupos'] ?? [];

if (!$usuario_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não informado']);
    exit;
}

// Verificar se é admin (não permite alterar grupos de admin)
$result = $conn->query("SELECT categoria FROM usuarios WHERE id = $usuario_id");
$usuario = $result->fetch_assoc();
if ($usuario && $usuario['categoria'] === 'admin') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Não é possível alterar grupos de administradores']);
    exit;
}

// Remover todos os grupos do usuário
$conn->query("DELETE FROM usuario_grupos WHERE usuario_id = $usuario_id");

// Adicionar os grupos selecionados
if (!empty($grupos)) {
    $stmt = $conn->prepare("INSERT INTO usuario_grupos (usuario_id, grupo_id) VALUES (?, ?)");
    foreach ($grupos as $grupo_id) {
        $grupo_id = intval($grupo_id);
        $stmt->bind_param("ii", $usuario_id, $grupo_id);
        $stmt->execute();
    }
    $stmt->close();
}

echo json_encode(['status' => 'sucesso']);

