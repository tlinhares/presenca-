<?php
/**
 * Cron — processa a fila de divulgação do app (divulgacao_fila).
 *
 * Roda a cada 1 minuto. Por execução:
 *   - até 15 e-mails (SMTP aguenta)
 *   - até 4 WhatsApps, com delay humanizado de 6–12s entre eles
 *     (WhatsAppService já rotaciona APIs; o delay evita bloqueio do número)
 *
 * flock impede execuções sobrepostas (o lote de WhatsApp pode passar de 1
 * minuto por causa dos delays). Com o lock garantido, linhas presas em
 * 'processando' (crash de execução anterior) são resgatadas pra 'pendente'.
 * Falha tenta até 3 vezes; na 3ª vira 'falha' definitiva.
 */

date_default_timezone_set('America/Cuiaba');

const LOTE_EMAIL = 15;
const LOTE_WHATS = 4;
const MAX_TENTATIVAS = 3;

function logDivulgacao(string $msg): void
{
    file_put_contents(__DIR__ . '/../logs/processar_divulgacao.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// Uma execução por vez
$lock = fopen(__DIR__ . '/../logs/processar_divulgacao.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // execução anterior ainda rodando — sai silencioso
}

try {
    require_once __DIR__ . '/../api/conexao.php';
    require_once __DIR__ . '/../core/services/DivulgacaoService.php';

    // Resgata presos de execução que morreu no meio (seguro: temos o flock)
    $conn->query("UPDATE divulgacao_fila SET status = 'pendente' WHERE status = 'processando'");

    // Nada na fila? Sai silencioso.
    $tem = (int) $conn->query("SELECT COUNT(*) n FROM divulgacao_fila WHERE status = 'pendente'")->fetch_assoc()['n'];
    if ($tem === 0) exit(0);

    foreach ([['email', LOTE_EMAIL], ['whatsapp', LOTE_WHATS]] as [$canal, $limite]) {
        // Reivindica um lote deste canal
        $stmt = $conn->prepare(
            "UPDATE divulgacao_fila
                SET status = 'processando'
              WHERE status = 'pendente' AND canal = ?
              ORDER BY id ASC
              LIMIT ?"
        );
        $stmt->bind_param('si', $canal, $limite);
        $stmt->execute();
        $claimed = $stmt->affected_rows;
        $stmt->close();
        if ($claimed === 0) continue;

        $stmt = $conn->prepare(
            "SELECT id, id_usuario, nome, destino, mensagem, assunto, tentativas
               FROM divulgacao_fila
              WHERE status = 'processando' AND canal = ?
              ORDER BY id ASC"
        );
        $stmt->bind_param('s', $canal);
        $stmt->execute();
        $rs = $stmt->get_result();
        $itens = [];
        while ($x = $rs->fetch_assoc()) $itens[] = $x;
        $stmt->close();

        foreach ($itens as $i => $item) {
            $id = (int) $item['id'];

            if ($canal === 'whatsapp') {
                $r = DivulgacaoService::enviarWhatsapp($conn, $item['destino'], $item['mensagem'], (int) $item['id_usuario'], $item['nome']);
            } else {
                $r = DivulgacaoService::enviarEmail($conn, $item['destino'], (string) $item['assunto'], $item['mensagem'], (int) $item['id_usuario'], $item['nome']);
            }

            if (!empty($r['sucesso'])) {
                $agora = date('Y-m-d H:i:s');
                $stmt = $conn->prepare("UPDATE divulgacao_fila SET status = 'enviado', erro = NULL, tentativas = tentativas + 1, enviado_em = ? WHERE id = ?");
                $stmt->bind_param('si', $agora, $id);
            } else {
                $tent = (int) $item['tentativas'] + 1;
                $novo_status = ($tent >= MAX_TENTATIVAS) ? 'falha' : 'pendente';
                $erro = mb_substr((string) ($r['mensagem'] ?? 'erro'), 0, 500);
                $stmt = $conn->prepare("UPDATE divulgacao_fila SET status = ?, erro = ?, tentativas = ? WHERE id = ?");
                $stmt->bind_param('ssii', $novo_status, $erro, $tent, $id);
                logDivulgacao("FALHA id=$id canal=$canal destino={$item['destino']} tent=$tent: $erro");
            }
            $stmt->execute();
            $stmt->close();

            // Fallback: WhatsApp esgotou as tentativas → enfileira E-MAIL do
            // mesmo usuário no mesmo lote (se já não houver um item de e-mail
            // pra ele — quando o admin marcou os dois canais, já existe).
            if ($canal === 'whatsapp' && isset($novo_status) && $novo_status === 'falha' && empty($r['sucesso'])) {
                $uid_f = (int) $item['id_usuario'];
                $stmt = $conn->prepare(
                    "SELECT u.email, f.id_lote, f.mensagem, f.criado_por,
                            (SELECT COUNT(*) FROM divulgacao_fila WHERE id_lote = f.id_lote AND id_usuario = f.id_usuario AND canal = 'email') AS ja_tem_email
                       FROM divulgacao_fila f
                       JOIN usuarios u ON u.id = f.id_usuario
                      WHERE f.id = ?"
                );
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $fb = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($fb && (int) $fb['ja_tem_email'] === 0
                    && !empty($fb['email']) && strpos($fb['email'], '@') !== false) {
                    $assunto_fb = 'Aplicativo Intranet AOM disponível!';
                    $cfg_a = $conn->query("SELECT assunto FROM divulgacao_config WHERE id = 1")->fetch_assoc();
                    if ($cfg_a && trim($cfg_a['assunto']) !== '') $assunto_fb = $cfg_a['assunto'];

                    $stmt = $conn->prepare(
                        "INSERT INTO divulgacao_fila (id_lote, id_usuario, nome, canal, destino, mensagem, assunto, criado_por)
                         VALUES (?, ?, ?, 'email', ?, ?, ?, ?)"
                    );
                    $stmt->bind_param('sissssi',
                        $fb['id_lote'], $uid_f, $item['nome'], $fb['email'], $fb['mensagem'], $assunto_fb, $fb['criado_por']);
                    if ($stmt->execute()) {
                        logDivulgacao("FALLBACK: whatsapp esgotado p/ {$item['nome']} — e-mail enfileirado ({$fb['email']})");
                    }
                    $stmt->close();
                }
            }

            // Delay humanizado entre WhatsApps (não precisa após o último)
            if ($canal === 'whatsapp' && $i < count($itens) - 1) {
                sleep(random_int(6, 12));
            }
        }

        logDivulgacao(sprintf('%s: processados %d', $canal, count($itens)));
    }

} catch (Throwable $e) {
    logDivulgacao('ERRO FATAL: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
