<?php
/**
 * POST /api/painel/comunicados/alterar_status.php
 * Body JSON: { id, acao: "publicar"|"arquivar"|"despublicar", enviar_push?: bool }
 *
 * - publicar:    rascunho|arquivado → publicado (seta publicado_em = agora).
 *                Com enviar_push=true, enfileira um push para TODOS na tabela
 *                notificacoes_push_agendadas com agendado_para = agora; o cron
 *                disparar_push_agendadas.php (roda a cada minuto) faz o envio
 *                sem travar esta requisição.
 * - despublicar: publicado → rascunho (some do app).
 * - arquivar:    qualquer → arquivado (some do app, preserva histórico).
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../auth/verifica_sessao_ajax.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAdmin();

date_default_timezone_set('America/Cuiaba');

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $id   = (int) ($input['id'] ?? 0);
    $acao = (string) ($input['acao'] ?? '');
    $enviar_push = !empty($input['enviar_push']);

    if ($id <= 0 || !in_array($acao, ['publicar', 'arquivar', 'despublicar'], true)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Parâmetros inválidos']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, titulo, corpo, status FROM comunicados WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $com = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$com) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Comunicado não encontrado']);
        exit;
    }

    if ($acao === 'publicar') {
        if ($com['status'] === 'publicado') {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Já está publicado']);
            exit;
        }
        $agora = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE comunicados SET status = 'publicado', publicado_em = ? WHERE id = ?");
        $stmt->bind_param('si', $agora, $id);
        $stmt->execute();
        $stmt->close();

        $push_info = null;
        if ($enviar_push) {
            // Reusa a fila de push agendado (cron roda a cada minuto)
            $titulo_push = '📢 ' . mb_substr($com['titulo'], 0, 90);
            $corpo_push  = mb_substr(trim(preg_replace('/\s+/', ' ', $com['corpo'])), 0, 140);
            $dados_json  = json_encode(['tipo' => 'comunicado', 'id_comunicado' => $id], JSON_UNESCAPED_UNICODE);
            $criado_por  = (int) $_SESSION['usuario_id'];
            $stmt = $conn->prepare(
                "INSERT INTO notificacoes_push_agendadas
                    (titulo, corpo, dados_json, destinatarios_tipo, destinatarios_ids, agendado_para, status, criado_por)
                 VALUES (?, ?, ?, 'todos', NULL, ?, 'pendente', ?)"
            );
            $stmt->bind_param('ssssi', $titulo_push, $corpo_push, $dados_json, $agora, $criado_por);
            $stmt->execute();
            $push_info = 'Push enfileirado — sai em até 1 minuto para todos os dispositivos';
            $stmt->close();
        }

        echo json_encode(['status' => 'ok', 'mensagem' => 'Comunicado publicado', 'push' => $push_info]);
        exit;
    }

    if ($acao === 'despublicar') {
        $stmt = $conn->prepare("UPDATE comunicados SET status = 'rascunho' WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'ok', 'mensagem' => 'Comunicado voltou para rascunho']);
        exit;
    }

    // arquivar
    $stmt = $conn->prepare("UPDATE comunicados SET status = 'arquivado' WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'ok', 'mensagem' => 'Comunicado arquivado']);

} catch (Throwable $e) {
    error_log('Erro em painel/comunicados/alterar_status.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
