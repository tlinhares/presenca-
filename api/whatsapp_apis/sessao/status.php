<?php
/**
 * GET /api/whatsapp_apis/sessao/status.php?id=<id_api>
 * Resposta JSON: { ok, state, http_code, source, raw, error? }
 *
 * Chamado pelo polling da tela /painel/whatsapp_sessoes.php a cada 3s.
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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'id inválido']);
    exit;
}

$stmt = $conn->prepare("SELECT id, nome, base_url, session_name, secret_key, token, ativo
                          FROM whatsapp_apis WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$api = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$api) {
    echo json_encode(['ok' => false, 'error' => 'API não encontrada']);
    exit;
}

$res = WhatsappSessionService::status($conn, $api);
echo json_encode([
    'ok'        => (bool)($res['ok'] ?? false),
    'state'     => $res['state'] ?? 'UNKNOWN',
    'source'    => $res['source'] ?? null,
    'http_code' => $res['http_code'] ?? 0,
    'raw'       => $res['raw'] ?? null,
    'error'     => $res['error'] ?? null,
]);
