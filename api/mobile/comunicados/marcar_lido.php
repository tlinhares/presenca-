<?php
/**
 * API Mobile — Marcar comunicado como lido
 *
 * Endpoint: POST /api/mobile/comunicados/marcar_lido.php
 * Header:   Authorization: Bearer <access_token>
 *
 * Body JSON (um dos dois):
 *   { "id": 5 }        — marca um comunicado como lido
 *   { "todos": true }  — marca todos os publicados como lidos (botão "marcar todas")
 *
 * Idempotente: marcar de novo não dá erro.
 * Response: { success: true, data: { nao_lidos: <restantes> } }
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
require_once __DIR__ . '/../utils/response.php';

if (!isset($_SESSION['usuario_id'])) {
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode(MobileResponse::unauthorized('Usuário não autenticado'));
        exit;
    }
}

date_default_timezone_set('America/Cuiaba');

try {
    $id_usuario = (int) $_SESSION['usuario_id'];
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $id    = (int) ($input['id'] ?? 0);
    $todos = !empty($input['todos']);

    if ($id <= 0 && !$todos) {
        echo json_encode(MobileResponse::error('Informe "id" ou "todos": true', 400));
        exit;
    }

    $agora = date('Y-m-d H:i:s');

    if ($todos) {
        $stmt = $conn->prepare(
            "INSERT IGNORE INTO comunicados_leituras (id_comunicado, id_usuario, lido_em)
             SELECT c.id, ?, ?
               FROM comunicados c
              WHERE c.status = 'publicado'
                AND (c.expira_em IS NULL OR c.expira_em > ?)"
        );
        $stmt->bind_param('iss', $id_usuario, $agora, $agora);
        $stmt->execute();
        $stmt->close();
    } else {
        // Valida que o comunicado existe e está visível
        $stmt = $conn->prepare(
            "SELECT id FROM comunicados
              WHERE id = ? AND status = 'publicado'
                AND (expira_em IS NULL OR expira_em > ?)"
        );
        $stmt->bind_param('is', $id, $agora);
        $stmt->execute();
        $ok = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$ok) {
            echo json_encode(MobileResponse::notFound('Comunicado não encontrado ou não está mais disponível'));
            exit;
        }

        $stmt = $conn->prepare(
            "INSERT IGNORE INTO comunicados_leituras (id_comunicado, id_usuario, lido_em) VALUES (?, ?, ?)"
        );
        $stmt->bind_param('iis', $id, $id_usuario, $agora);
        $stmt->execute();
        $stmt->close();
    }

    // Recalcula badge
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS n
           FROM comunicados c
      LEFT JOIN comunicados_leituras l
             ON l.id_comunicado = c.id AND l.id_usuario = ?
          WHERE c.status = 'publicado'
            AND (c.expira_em IS NULL OR c.expira_em > ?)
            AND l.id IS NULL"
    );
    $stmt->bind_param('is', $id_usuario, $agora);
    $stmt->execute();
    $nao_lidos = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    echo json_encode(MobileResponse::success(['nao_lidos' => $nao_lidos], 'Marcado como lido'));

} catch (Throwable $e) {
    error_log('Erro em mobile/comunicados/marcar_lido.php: ' . $e->getMessage());
    echo json_encode(MobileResponse::serverError('Erro ao marcar como lido'));
}

$conn->close();
