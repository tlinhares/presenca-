<?php
/**
 * POST /api/whatsapp_apis/testar_envio.php
 * Body JSON: { id: <id_api>, numero: "<numero>", mensagem: "<texto>" }
 *
 * Envia uma mensagem de teste pela API ESPECÍFICA (não passa pelo sorteio
 * de WhatsappService::enviarMensagem). Útil pra validar manualmente se
 * uma sessão recém-conectada está enviando OK.
 *
 * Se a API não tiver token persistido mas tiver base_url+session_name+secret_key,
 * gera o token via WhatsappSessionService::ensureToken antes de enviar.
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../core/services/WhatsAppService.php';
require_once __DIR__ . '/../../core/services/WhatsappSessionService.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $id       = (int)($input['id']       ?? $_POST['id']       ?? 0);
    $numero   = trim((string)($input['numero']   ?? $_POST['numero']   ?? ''));
    $mensagem = trim((string)($input['mensagem'] ?? $_POST['mensagem'] ?? ''));

    if ($id <= 0)            { throw new Exception('id inválido'); }
    if ($numero === '')      { throw new Exception('Informe o número'); }
    if ($mensagem === '')    { throw new Exception('Informe a mensagem'); }
    if (mb_strlen($mensagem) > 1000) { throw new Exception('Mensagem muito longa (máx 1000 caracteres)'); }

    // Carrega a API
    $stmt = $conn->prepare("SELECT id, nome, url_mensagem, token,
                                   base_url, session_name, secret_key
                              FROM whatsapp_apis WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $api = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$api) {
        throw new Exception('API não encontrada');
    }

    // Garante token (gera via secret_key se necessário). Persiste no banco.
    $t = WhatsappSessionService::ensureToken($conn, $api);
    if (!$t['ok']) {
        throw new Exception('Falha ao obter token: ' . ($t['error'] ?? 'desconhecido'));
    }
    // ensureToken atualizou $api['token'] por referência

    // Envia via API específica
    $res = WhatsAppService::enviarTeste($api, $numero, $mensagem);

    echo json_encode([
        'status'    => $res['sucesso'] ? 'ok' : 'erro',
        'sucesso'   => (bool)$res['sucesso'],
        'mensagem'  => $res['mensagem'] ?? '',
        'api_id'    => $api['id'],
        'api_nome'  => $api['nome'],
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status'   => 'erro',
        'sucesso'  => false,
        'mensagem' => $e->getMessage(),
    ]);
}
