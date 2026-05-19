<?php
/**
 * API - Excluir API de WhatsApp (chain completa)
 *
 * Executa em sequência:
 *   1) logout-session  (desvincula o aparelho no celular)
 *   2) close-session   (fecha em memória)
 *   3) clear-session-data (apaga arquivos persistentes do wppconnect)
 *   4) DELETE FROM whatsapp_apis
 *
 * Critério de aborto: a chain é interrompida APENAS se o wppconnect
 * estiver inalcançável (http_code = 0, ou seja, falha de rede/curl).
 * Respostas 4xx/5xx do servidor são consideradas "respondeu, segue"
 * — significam que ele conhece a sessão (ou que ela já não existe,
 * o que para nossos fins é equivalente).
 *
 * Quando o aborto acontece, a linha local NÃO é removida. O usuário
 * vê o erro no modal e tenta de novo.
 *
 * Resposta JSON:
 *   { ok, error?, aborted_at?, steps: [
 *       { key, label, ok, http_code, error? }, ...
 *   ] }
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../../api/conexao.php';
require_once __DIR__ . '/../../core/services/WhatsappSessionService.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int)($input['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Carregar API completa (precisamos de base_url, session_name, secret_key, token)
    $stmt = $conn->prepare(
        "SELECT id, nome, url_mensagem, url_arquivo, token,
                base_url, session_name, secret_key
           FROM whatsapp_apis
          WHERE id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $api = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$api) {
        throw new Exception('API não encontrada');
    }

    // Validação: não excluir se ainda for usada em alguma configuração de notificação
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total FROM whatsapp_config_notificacoes
           WHERE id_api_especifica = ?
              OR JSON_CONTAINS(ids_apis_sorteio, CAST(? AS JSON))"
    );
    $id_json = json_encode($id);
    $stmt->bind_param('is', $id, $id_json);
    $stmt->execute();
    $config = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($config['total'] > 0) {
        throw new Exception('Esta API está sendo usada em configurações de notificação. Remova as configurações antes de excluir.');
    }

    $steps = [];
    $hasRemote = !empty($api['base_url']) && !empty($api['session_name']);

    // Chain remota (logout → close → clear-session-data)
    if ($hasRemote) {
        $chain = [
            ['key' => 'logout', 'label' => 'Desconectar WhatsApp no celular',     'method' => 'logout'],
            ['key' => 'close',  'label' => 'Fechar sessão no wppconnect',         'method' => 'close'],
            ['key' => 'clear',  'label' => 'Apagar dados persistentes da sessão', 'method' => 'clearSessionData'],
        ];
        foreach ($chain as $s) {
            $res = WhatsappSessionService::{$s['method']}($conn, $api);
            $http = (int)($res['http_code'] ?? 0);
            // Considera "respondido" qualquer http_code > 0, mesmo que 4xx/5xx.
            // wppconnect 404 = sessão já não existe lá → para nossos fins é OK seguir.
            $respondeu = $http > 0;
            $passo_ok  = (bool)($res['ok'] ?? false) || $respondeu;
            $steps[]   = [
                'key'       => $s['key'],
                'label'     => $s['label'],
                'ok'        => $passo_ok,
                'http_code' => $http,
                'error'     => $res['error'] ?? null,
            ];
            // Aborta SÓ se rede inalcançável (http_code = 0).
            if (!$passo_ok && $http === 0) {
                echo json_encode([
                    'ok'         => false,
                    'error'      => "Falha em '{$s['label']}': " . ($res['error'] ?? 'sem resposta'),
                    'aborted_at' => $s['key'],
                    'steps'      => $steps,
                ]);
                exit;
            }
        }
    } else {
        $steps[] = [
            'key'       => 'skip',
            'label'     => 'Sem configuração wppconnect — pulando limpeza remota',
            'ok'        => true,
            'http_code' => 0,
        ];
    }

    // DELETE local
    $stmt = $conn->prepare("DELETE FROM whatsapp_apis WHERE id = ?");
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        $steps[] = ['key' => 'delete', 'label' => 'Remover do banco', 'ok' => false, 'http_code' => 0, 'error' => $stmt->error];
        echo json_encode(['ok' => false, 'error' => 'Falha ao remover do banco: ' . $stmt->error, 'steps' => $steps]);
        $stmt->close();
        exit;
    }
    $stmt->close();

    $steps[] = ['key' => 'delete', 'label' => 'Remover registro do banco', 'ok' => true, 'http_code' => 0];

    echo json_encode([
        'ok'    => true,
        'nome'  => $api['nome'],
        'steps' => $steps,
    ]);

} catch (Exception $e) {
    error_log('Erro em whatsapp_apis/excluir.php: ' . $e->getMessage());
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage(),
        'steps' => [],
    ]);
}
