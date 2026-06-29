<?php
/**
 * Health check periódico dos dispositivos faciais Intelbras.
 *
 * Roda a cada 5 minutos via crontab. Para cada dispositivo ATIVO:
 *  - Faz um GET rápido em /cgi-bin/global.cgi?action=getCurrentTime (linha SS)
 *    ou /api/login.cgi (linha XPE — só checa que o socket responde).
 *  - Atualiza dispositivos_faciais.status_conexao e ultima_verificacao_status.
 *
 * Códigos aceitos como "online": 200, 401, 400 (qualquer resposta significa
 * que o dispositivo está respondendo — mesmo que sejam credenciais inválidas).
 * Qualquer outro resultado (timeout, conexão recusada, 5xx) → "offline".
 *
 * NÃO mexe em facial_sync, NÃO sincroniza usuários — só health.
 */

date_default_timezone_set('America/Cuiaba');

$logFile = __DIR__ . '/../logs/health_check_dispositivos.log';

function logHealthCheck(string $msg): void
{
    global $logFile;
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

try {
    require_once __DIR__ . '/../api/conexao.php';

    // Buscar todos os dispositivos ATIVOS (independente do tipo — restaurante e culto).
    $rs = $conn->query("SELECT id, nome, ip, porta, usuario, senha, modelo, status_conexao
                        FROM dispositivos_faciais
                        WHERE ativo = 1
                        ORDER BY id");
    if (!$rs || $rs->num_rows === 0) {
        logHealthCheck('Nenhum dispositivo ativo — fim.');
        exit(0);
    }

    $dispositivos = [];
    while ($r = $rs->fetch_assoc()) $dispositivos[] = $r;

    $upd = $conn->prepare("UPDATE dispositivos_faciais
                              SET status_conexao = ?,
                                  ultima_verificacao_status = NOW()
                            WHERE id = ?");

    $online_count = 0;
    $offline_count = 0;

    foreach ($dispositivos as $disp) {
        $modelo = strtoupper(trim((string) ($disp['modelo'] ?? '')));
        $isXpe  = ($modelo !== '' && strpos($modelo, 'XPE') !== false);

        $ip   = (string) $disp['ip'];
        $port = (int) ($disp['porta'] ?? 80);
        if ($port <= 0) $port = 80;

        // Endpoint de health por linha. Não exige sucesso de auth — qualquer
        // resposta HTTP indica que o socket respondeu (dispositivo "online").
        $url = $isXpe
            ? "http://{$ip}:{$port}/api/login.cgi"
            : "http://{$ip}:{$port}/cgi-bin/global.cgi?action=getCurrentTime";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,           // HEAD-like, evita transferir body grande
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPAUTH       => $isXpe ? CURLAUTH_BASIC : CURLAUTH_DIGEST,
            CURLOPT_USERPWD        => ($disp['usuario'] ?? '') . ':' . ($disp['senha'] ?? ''),
        ]);
        curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = (string) curl_error($ch);
        curl_close($ch);

        // 200/400/401 = dispositivo respondendo (online). Tudo o mais (timeout,
        // conexão recusada, 5xx) = offline.
        $online = ($err === '' && in_array($http, [200, 400, 401], true)) || ($err === '' && $http >= 200 && $http < 500);
        $novo_status = $online ? 'online' : 'offline';

        $upd->bind_param('si', $novo_status, $disp['id']);
        $upd->execute();

        if ($online) $online_count++; else $offline_count++;

        logHealthCheck(sprintf(
            "id=%d %s (%s, %s) → %s [HTTP %d%s]",
            $disp['id'],
            $disp['nome'],
            $ip,
            $isXpe ? 'XPE' : 'SS',
            strtoupper($novo_status),
            $http,
            $err !== '' ? ' err=' . substr($err, 0, 80) : ''
        ));
    }

    $upd->close();
    logHealthCheck(sprintf('Fim: %d online / %d offline.', $online_count, $offline_count));
    exit(0);

} catch (Throwable $e) {
    logHealthCheck('ERRO FATAL: ' . $e->getMessage());
    exit(1);
}
