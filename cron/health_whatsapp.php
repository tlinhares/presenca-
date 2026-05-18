<?php
/**
 * Health Check das sessões WhatsApp (wppconnect-server).
 *
 * Para cada API ATIVA em whatsapp_apis, faz GET em
 *   {base}/check-connection-session
 * onde {base} é derivado de url_mensagem (substitui /send-message).
 * Registra resultado em whatsapp_apis_health (latência, http_code, mensagem).
 *
 * Se houver 3 falhas consecutivas para a mesma API, dispara Logger::erro
 * (que vai para logs/erros.log + sistema de alertas existente).
 *
 * Este script é READ-ONLY em whatsapp_apis: NÃO altera ativo, prioridade
 * ou contadores. A decisão de desativar uma API permanece manual via painel.
 *
 * Cron sugerido (a cada 10 minutos):
 *   *0,10,20,30,40,50 * * * * /usr/bin/php /var/www/html/presenca/cron/health_whatsapp.php
 */

date_default_timezone_set('America/Cuiaba');

require_once __DIR__ . '/../api/conexao.php';
require_once __DIR__ . '/../utils/logger.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    // Sem conexão: logar e sair (não trava cron)
    error_log('health_whatsapp: sem conexão com banco');
    exit(1);
}

// Verificar se a tabela existe (defensivo se ainda não tiver rodado a migração)
$check = $conn->query("SHOW TABLES LIKE 'whatsapp_apis_health'");
if (!$check || $check->num_rows === 0) {
    error_log('health_whatsapp: tabela whatsapp_apis_health não existe');
    exit(1);
}

$sql = "SELECT id, nome, url_mensagem, token
        FROM whatsapp_apis
        WHERE ativo = 1
        ORDER BY id";
$result = $conn->query($sql);
if (!$result) {
    error_log('health_whatsapp: erro ao listar APIs: ' . $conn->error);
    exit(1);
}

$stmt_insert = $conn->prepare(
    "INSERT INTO whatsapp_apis_health
       (id_api, status, latencia_ms, http_code, mensagem)
     VALUES (?, ?, ?, ?, ?)"
);
if (!$stmt_insert) {
    error_log('health_whatsapp: erro ao preparar insert: ' . $conn->error);
    exit(1);
}

$stmt_consecutivas = $conn->prepare(
    "SELECT status FROM whatsapp_apis_health
      WHERE id_api = ?
      ORDER BY criado_em DESC
      LIMIT 3"
);

while ($api = $result->fetch_assoc()) {
    $id_api = (int)$api['id'];
    $nome   = $api['nome'];
    $token  = trim((string)$api['token']);

    // Deriva URL de check a partir da url_mensagem
    $url_check = preg_replace('#/send-message/?$#', '/check-connection-session', $api['url_mensagem']);
    if ($url_check === $api['url_mensagem']) {
        // não bateu o padrão esperado — pula
        continue;
    }

    $headers = ['Accept: application/json'];
    if ($token !== '') {
        // Aceita token armazenado com ou sem prefixo "Bearer "
        $auth = stripos($token, 'bearer ') === 0 ? $token : 'Bearer ' . $token;
        $headers[] = 'Authorization: ' . $auth;
    }

    $ch = curl_init($url_check);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $t0 = microtime(true);
    $resp = curl_exec($ch);
    $latencia_ms = (int) round((microtime(true) - $t0) * 1000);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    // Determinar status
    if ($err !== '' && stripos($err, 'timed out') !== false) {
        $status = 'timeout';
        $mensagem = "timeout: $err";
    } elseif ($resp === false || $err !== '') {
        $status = 'falha';
        $mensagem = "curl error: $err";
    } else {
        // Resposta esperada: {"status":true,"message":"Connected"}
        $json = json_decode($resp, true);
        $conectado = is_array($json) && (
            (isset($json['status']) && ($json['status'] === true || $json['status'] === 'true' || $json['status'] === 'success'))
            && (!isset($json['message']) || stripos((string)$json['message'], 'connected') !== false || stripos((string)$json['message'], 'logged') !== false)
        );
        if ($http >= 200 && $http < 300 && $conectado) {
            $status = 'ok';
            $mensagem = isset($json['message']) ? (string)$json['message'] : 'connected';
        } else {
            $status = 'falha';
            $mensagem = substr(is_string($resp) ? $resp : json_encode($resp), 0, 500);
        }
    }

    // Gravar resultado
    $stmt_insert->bind_param('isiis', $id_api, $status, $latencia_ms, $http, $mensagem);
    @$stmt_insert->execute();

    // Checar 3 falhas consecutivas (após gravar este registro)
    if ($status !== 'ok') {
        $stmt_consecutivas->bind_param('i', $id_api);
        $stmt_consecutivas->execute();
        $rows = $stmt_consecutivas->get_result();
        $tres_falhas = 0;
        while ($r = $rows->fetch_assoc()) {
            if ($r['status'] !== 'ok') $tres_falhas++;
        }
        if ($tres_falhas >= 3) {
            Logger::erro(
                'health_whatsapp',
                "API '{$nome}' (id={$id_api}) com 3+ falhas consecutivas — última: status=$status, http=$http, msg=" . substr($mensagem, 0, 200)
            );
        }
    }
}

$stmt_insert->close();
$stmt_consecutivas->close();

// Limpeza: manter apenas últimos 30 dias de registros (evita inchaço da tabela)
$conn->query("DELETE FROM whatsapp_apis_health WHERE criado_em < NOW() - INTERVAL 30 DAY");
