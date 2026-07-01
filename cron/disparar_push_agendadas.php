<?php
/**
 * Cron — dispara notificações push agendadas cujo horário já chegou.
 *
 * Roda a cada 1 minuto via crontab. Para cada linha em
 * notificacoes_push_agendadas com status='pendente' e agendado_para <= NOW():
 *   1. marca como 'processando' (evita duplo envio se cron pisar por cima)
 *   2. resolve destinatários (usuario/varios = ids; todos = todos com dispositivo)
 *   3. dispara via PushNotificationService::enviarParaUsuario()
 *   4. marca como 'concluido' com resumo no resultado_json
 *
 * Silencioso quando push não está configurado (evita marcar como falha em
 * ambiente sem Firebase — o admin pode configurar depois e re-agendar).
 */

date_default_timezone_set('America/Cuiaba');

require_once __DIR__ . '/../utils/logger.php';

function logDisparoPush(string $msg): void
{
    Logger::emergencial('push_agendadas', $msg);
    file_put_contents(__DIR__ . '/../logs/disparar_push_agendadas.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

try {
    require_once __DIR__ . '/../api/conexao.php';
    require_once __DIR__ . '/../core/services/PushNotificationService.php';

    if (!PushNotificationService::estaConfigurado($conn)) {
        // Silencioso — deixa os agendados esperando; se admin ativar depois, sai.
        exit(0);
    }

    // Lock atômico: pega agendados vencidos e marca como processando de uma vez.
    // Compara como STRING para ser independente do timezone do MySQL (que aqui
    // é SYSTEM/UTC enquanto o PHP roda em America/Cuiaba). O `agendado_para` é
    // gravado em horário local (via `date('Y-m-d H:i:s')` do PHP) e a comparação
    // com $agora_str (também horário local) funciona porque ambos estão no mesmo
    // formato e no mesmo referencial.
    $agora_str = date('Y-m-d H:i:s');
    $stmt_lock = $conn->prepare(
        "UPDATE notificacoes_push_agendadas
            SET status = 'processando'
          WHERE status = 'pendente' AND agendado_para <= ?"
    );
    $stmt_lock->bind_param('s', $agora_str);
    $stmt_lock->execute();
    $stmt_lock->close();

    $rs = $conn->query("SELECT id, titulo, corpo, dados_json, destinatarios_tipo, destinatarios_ids
                         FROM notificacoes_push_agendadas
                        WHERE status = 'processando'
                        ORDER BY agendado_para ASC");

    if (!$rs || $rs->num_rows === 0) {
        exit(0);
    }

    $itens = [];
    while ($x = $rs->fetch_assoc()) $itens[] = $x;

    foreach ($itens as $it) {
        $id      = (int) $it['id'];
        $titulo  = (string) $it['titulo'];
        $corpo   = (string) $it['corpo'];
        $dados   = $it['dados_json'] ? (json_decode($it['dados_json'], true) ?: []) : [];
        $tipo    = (string) $it['destinatarios_tipo'];
        $ids_raw = $it['destinatarios_ids'] ? (json_decode($it['destinatarios_ids'], true) ?: []) : [];

        // Resolver destinatários
        $usuarios_target = [];
        if ($tipo === 'todos') {
            $r = $conn->query("SELECT DISTINCT id_usuario FROM notificacoes_push_dispositivos WHERE ativo = 1");
            while ($x = $r->fetch_assoc()) $usuarios_target[] = (int) $x['id_usuario'];
        } else {
            $usuarios_target = array_values(array_unique(array_filter(array_map('intval', $ids_raw), fn($v) => $v > 0)));
        }

        $enviados = 0;
        $falhas   = 0;
        $sem_dispositivo = 0;
        foreach ($usuarios_target as $uid) {
            $r = PushNotificationService::enviarParaUsuario($conn, $uid, $titulo, $corpo, $dados);
            if (($r['dispositivos'] ?? 0) === 0) {
                $sem_dispositivo++;
            } else {
                $enviados += (int) ($r['enviados'] ?? 0);
                $falhas   += (int) ($r['falhas']   ?? 0);
            }
        }

        $resultado = [
            'usuarios_alvo'   => count($usuarios_target),
            'enviados'        => $enviados,
            'falhas'          => $falhas,
            'sem_dispositivo' => $sem_dispositivo,
        ];
        $status_final = ($enviados === 0 && $falhas > 0) ? 'falha' : 'concluido';
        $res_json = json_encode($resultado, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare("UPDATE notificacoes_push_agendadas
                                   SET status = ?, executado_em = NOW(), resultado_json = ?
                                 WHERE id = ?");
        $stmt->bind_param('ssi', $status_final, $res_json, $id);
        $stmt->execute();
        $stmt->close();

        logDisparoPush(sprintf(
            'id=%d titulo="%s" alvos=%d enviados=%d falhas=%d sem_dispositivo=%d status=%s',
            $id, mb_substr($titulo, 0, 60), count($usuarios_target),
            $enviados, $falhas, $sem_dispositivo, $status_final
        ));
    }

} catch (Throwable $e) {
    logDisparoPush('ERRO FATAL: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    exit(1);
}
