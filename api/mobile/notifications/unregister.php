<?php
/**
 * POST /api/mobile/notifications/unregister.php
 * Body JSON: { fcm_token }
 *
 * Desativa um token FCM. Usado no logout do app pra parar de receber push.
 * Por segurança, só desativa se o token pertence ao usuário autenticado.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/middleware/mobile_auth.php';

if (!isset($_SESSION['usuario_id'])) {
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
        exit;
    }
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $fcm_token = trim($input['fcm_token'] ?? '');

    if ($fcm_token === '') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'fcm_token é obrigatório']);
        exit;
    }

    $id_usuario = (int) $_SESSION['usuario_id'];

    $stmt = $conn->prepare("UPDATE notificacoes_push_dispositivos SET ativo = 0 WHERE fcm_token = ? AND id_usuario = ?");
    $stmt->bind_param('si', $fcm_token, $id_usuario);
    $stmt->execute();
    $afetados = $stmt->affected_rows;
    $stmt->close();

    echo json_encode([
        'status' => 'ok',
        'mensagem' => $afetados > 0 ? 'Dispositivo desregistrado' : 'Nenhum dispositivo encontrado com esse token',
    ]);
} catch (Throwable $e) {
    error_log('Erro em mobile/notifications/unregister.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
