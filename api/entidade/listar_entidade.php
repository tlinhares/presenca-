<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../conexao.php');
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

if (!isset($_SESSION['usuario_id'])) {
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
        exit;
    }
}

$sql = "SELECT entidade_id, entidade_nome FROM entidade ORDER BY entidade_nome";
$result = $conn->query($sql);

$entidade = [];

while ($row = $result->fetch_assoc()) {
    $entidade[] = [
        'entidade_id' => $row['entidade_id'],
        'entidade_nome' => $row['entidade_nome']
    ];
}

echo json_encode($entidade);
$conn->close();
?>
