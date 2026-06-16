<?php
/**
 * API para Listar Entidades
 */
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

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

if (!isset($_SESSION['usuario_id'])) {
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
        exit;
    }
}

try {
    $sql = "SELECT entidade_id as id, entidade_nome as nome FROM entidade ORDER BY entidade_nome";
    $result = $conn->query($sql);
    
    $entidades = [];
    while ($row = $result->fetch_assoc()) {
        $entidades[] = [
            'id' => intval($row['id']),
            'nome' => $row['nome']
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'entidades' => $entidades
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao listar entidades: ' . $e->getMessage()
    ]);
}
?>



