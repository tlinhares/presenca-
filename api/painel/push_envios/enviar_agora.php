<?php
/**
 * POST /api/painel/push_envios/enviar_agora.php
 * Body JSON: {
 *   titulo, corpo, dados?: object,
 *   destinatarios_tipo: "usuario"|"varios"|"todos",
 *   ids?: number[]   // obrigatório se destinatarios_tipo != "todos"
 * }
 *
 * Envia push imediatamente para os destinatários.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../auth/verifica_sessao_ajax.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/PushNotificationService.php';

MenuPermissaoService::exigirAdmin();

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $titulo = trim((string) ($input['titulo'] ?? ''));
    $corpo  = trim((string) ($input['corpo']  ?? ''));
    $tipo   = (string) ($input['destinatarios_tipo'] ?? '');
    $ids    = is_array($input['ids'] ?? null) ? array_values(array_filter(array_map('intval', $input['ids']), fn($v) => $v > 0)) : [];
    $dados  = is_array($input['dados'] ?? null) ? $input['dados'] : [];

    if ($titulo === '' || $corpo === '')     throw new RuntimeException('Título e corpo são obrigatórios');
    if (!in_array($tipo, ['usuario', 'varios', 'todos'], true)) throw new RuntimeException('destinatarios_tipo inválido');
    if ($tipo !== 'todos' && empty($ids))   throw new RuntimeException('Selecione ao menos um usuário destinatário');

    if (!PushNotificationService::estaConfigurado($conn)) {
        throw new RuntimeException('Push notifications não estão configuradas (Service Account do Firebase pendente).');
    }

    // Resolve a lista final de usuários
    $usuarios_target = [];
    if ($tipo === 'todos') {
        $r = $conn->query("SELECT DISTINCT id_usuario FROM notificacoes_push_dispositivos WHERE ativo = 1");
        while ($x = $r->fetch_assoc()) $usuarios_target[] = (int) $x['id_usuario'];
    } else {
        $usuarios_target = $ids;
    }
    $usuarios_target = array_values(array_unique($usuarios_target));

    if (empty($usuarios_target)) {
        throw new RuntimeException('Nenhum destinatário elegível — nenhum usuário com dispositivo push registrado.');
    }

    $enviados = 0;
    $falhas   = 0;
    $sem_dispositivo = 0;
    $detalhes = [];

    foreach ($usuarios_target as $uid) {
        $r = PushNotificationService::enviarParaUsuario($conn, $uid, $titulo, $corpo, $dados);
        if (($r['dispositivos'] ?? 0) === 0) {
            $sem_dispositivo++;
        } else {
            $enviados += (int) ($r['enviados'] ?? 0);
            $falhas   += (int) ($r['falhas']   ?? 0);
        }
        if (($r['falhas'] ?? 0) > 0 && !empty($r['erros'])) {
            $detalhes[] = ['id_usuario' => $uid, 'erros' => $r['erros']];
        }
    }

    echo json_encode([
        'status' => 'ok',
        'mensagem' => sprintf(
            'Envio concluído: %d push(es) entregue(s), %d falha(s), %d usuário(s) sem dispositivo.',
            $enviados, $falhas, $sem_dispositivo
        ),
        'resumo' => [
            'usuarios_alvo'   => count($usuarios_target),
            'enviados'        => $enviados,
            'falhas'          => $falhas,
            'sem_dispositivo' => $sem_dispositivo,
        ],
        'detalhes_falhas' => $detalhes,
    ]);
} catch (Throwable $e) {
    error_log('Erro em push_envios/enviar_agora.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
