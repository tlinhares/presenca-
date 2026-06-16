<?php
/**
 * POST /api/mobile/notifications/register.php
 * Body JSON: { fcm_token, plataforma, modelo_dispositivo?, versao_app? }
 *
 * Registra/atualiza um token FCM associado ao usuário autenticado. Idempotente:
 * se o token já existe, atualiza id_usuario, plataforma, modelo, versao,
 * ativo=1 e ultimo_uso.
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
    $plataforma = strtolower(trim($input['plataforma'] ?? 'android'));
    $modelo = trim($input['modelo_dispositivo'] ?? '');
    $versao = trim($input['versao_app'] ?? '');

    if ($fcm_token === '' || strlen($fcm_token) < 20) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'fcm_token inválido']);
        exit;
    }
    if (!in_array($plataforma, ['android', 'ios', 'web'], true)) {
        $plataforma = 'android';
    }
    if ($modelo === '') $modelo = null;
    if ($versao === '') $versao = null;

    $id_usuario = (int) $_SESSION['usuario_id'];

    $stmt = $conn->prepare(
        "INSERT INTO notificacoes_push_dispositivos (id_usuario, fcm_token, plataforma, modelo_dispositivo, versao_app, ativo, ultimo_uso)
         VALUES (?, ?, ?, ?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE
            id_usuario = VALUES(id_usuario),
            plataforma = VALUES(plataforma),
            modelo_dispositivo = VALUES(modelo_dispositivo),
            versao_app = VALUES(versao_app),
            ativo = 1,
            ultimo_uso = NOW()"
    );
    $stmt->bind_param('issss', $id_usuario, $fcm_token, $plataforma, $modelo, $versao);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Erro ao registrar token: ' . $conn->error);
    }
    $stmt->close();

    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Dispositivo registrado',
    ]);
} catch (Throwable $e) {
    error_log('Erro em mobile/notifications/register.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
