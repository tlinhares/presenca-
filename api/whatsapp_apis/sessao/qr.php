<?php
/**
 * GET /api/whatsapp_apis/sessao/qr.php?id=<id_api>[&ts=...]
 *
 * Proxy do QR code: o backend faz a chamada autenticada ao wppconnect
 * e serve a imagem PNG. Assim o token Bearer nunca vai pro browser.
 *
 * O parâmetro 'ts' é usado pelo front pra cache-bust (importante: o
 * QR expira em ~30s, então sem cache-bust o <img> mostra o QR antigo).
 */
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';

if (!MenuPermissaoService::podeAcessar('gerenciar_whatsapp_sessoes')) {
    http_response_code(403);
    echo 'Sem permissão';
    exit;
}

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/WhatsappSessionService.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'id inválido';
    exit;
}

$stmt = $conn->prepare("SELECT id, nome, base_url, session_name, secret_key, token
                          FROM whatsapp_apis WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$api = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$api) {
    http_response_code(404);
    echo 'API não encontrada';
    exit;
}

$res = WhatsappSessionService::qrCode($conn, $api);
if (!$res['ok'] || empty($res['body'])) {
    http_response_code(404);
    echo $res['error'] ?? 'QR indisponível';
    exit;
}

header('Content-Type: ' . ($res['content_type'] ?: 'image/png'));
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
echo $res['body'];
