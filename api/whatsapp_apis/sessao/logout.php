<?php
/**
 * POST /api/whatsapp_apis/sessao/logout.php
 * Body JSON: { id: <id_api> }
 * Resposta: { ok, http_code, error? }
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';

if (!MenuPermissaoService::podeAcessar('gerenciar_whatsapp_sessoes')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sem permissão']);
    exit;
}

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/WhatsappSessionService.php';

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'id inválido']);
    exit;
}

$stmt = $conn->prepare("SELECT id, nome, base_url, session_name, secret_key, token
                          FROM whatsapp_apis WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$api = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$api) {
    echo json_encode(['ok' => false, 'error' => 'API não encontrada']);
    exit;
}

$res = WhatsappSessionService::logout($conn, $api);
echo json_encode([
    'ok'        => (bool)$res['ok'],
    'http_code' => $res['http_code'] ?? 0,
    'error'     => $res['error'] ?? null,
]);
