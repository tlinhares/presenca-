<?php
/**
 * WhatsappSessionService — operações de sessão no wppconnect-server v2.
 *
 * Camada que centraliza HTTP contra o wppconnect (status, QR, start,
 * logout, close, clear-session-data). Os endpoints PHP do projeto
 * apenas autenticam o usuário e chamam estes métodos — nenhum token
 * chega ao browser do operador.
 *
 * Pegadinhas tratadas aqui:
 *  - status-session pode mentir (cacheia QRCODE depois de conectado);
 *    consultamos check-connection-session antes.
 *  - qrcode-session retorna ora image/png cru, ora JSON com data URL.
 *  - generate-token devolve string bcrypt-like ($2b$10$...): tratamos como Bearer cru.
 *  - close/logout/start/status/qr exigem Bearer; generate-token e
 *    clear-session-data dispensam (usam secret_key na URL).
 */

class WhatsappSessionService
{
    private const CONNECT_TIMEOUT = 5;
    private const TOTAL_TIMEOUT   = 15;

    /** Concatena {base_url}/api/{session}/{op} de forma segura. */
    private static function url(array $api, string $op): string
    {
        $base = rtrim((string)$api['base_url'], '/');
        return $base . '/api/' . $api['session_name'] . '/' . ltrim($op, '/');
    }

    /** Garante que a linha tem um token Bearer válido; gera via generate-token se faltar. */
    public static function ensureToken(mysqli $conn, array &$api): array
    {
        $token = trim((string)($api['token'] ?? ''));
        if ($token !== '') {
            return ['ok' => true, 'token' => $token];
        }
        if (empty($api['secret_key']) || empty($api['base_url']) || empty($api['session_name'])) {
            return ['ok' => false, 'error' => 'Configuração incompleta (base_url/session_name/secret_key)'];
        }

        $url = rtrim((string)$api['base_url'], '/')
             . '/api/' . $api['session_name'] . '/' . $api['secret_key'] . '/generate-token';
        $res = self::httpRequest('POST', $url, null, null);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => 'generate-token falhou: ' . $res['error']];
        }
        $body  = json_decode($res['body'], true);
        $token = $body['token'] ?? $body['full'] ?? null;
        if (!is_string($token) || $token === '') {
            return ['ok' => false, 'error' => 'generate-token sem campo token'];
        }

        // Persistir para próximas chamadas (best effort)
        $stmt = $conn->prepare('UPDATE whatsapp_apis SET token = ? WHERE id = ?');
        if ($stmt) {
            $id = (int)$api['id'];
            $stmt->bind_param('si', $token, $id);
            @$stmt->execute();
            $stmt->close();
        }
        $api['token'] = $token;
        return ['ok' => true, 'token' => $token];
    }

    /** Consulta a fonte da verdade (check-connection-session) e, se não conectado, status-session. */
    public static function status(mysqli $conn, array $api): array
    {
        $t = self::ensureToken($conn, $api);
        if (!$t['ok']) {
            return ['ok' => false, 'error' => $t['error'], 'http_code' => 0];
        }
        $token = $t['token'];

        // 1) check-connection-session = verdade
        $check = self::httpRequest('GET', self::url($api, 'check-connection-session'), $token);
        if ($check['ok']) {
            $cb = json_decode($check['body'], true);
            $statusBool = $cb['status'] ?? null;
            $msg = strtolower((string)($cb['message'] ?? ''));
            $conectado =
                ($statusBool === true || $statusBool === 'true' || $statusBool === 'success')
                && ($msg === '' || strpos($msg, 'connected') !== false || strpos($msg, 'logged') !== false);
            if ($conectado) {
                return [
                    'ok'        => true,
                    'state'     => 'CONNECTED',
                    'source'    => 'check-connection',
                    'http_code' => $check['http_code'],
                    'raw'       => $cb,
                ];
            }
        }

        // 2) cai pra status-session
        $st = self::httpRequest('GET', self::url($api, 'status-session'), $token);
        $body = is_string($st['body'] ?? null) ? json_decode($st['body'], true) : null;
        $state = strtoupper((string)($body['status'] ?? $body['state'] ?? 'UNKNOWN'));
        return [
            'ok'        => (bool)$st['ok'],
            'state'     => $state,
            'source'    => 'status-session',
            'http_code' => $st['http_code'],
            'raw'       => $body,
            'error'     => $st['error'] ?? null,
        ];
    }

    public static function startSession(mysqli $conn, array $api): array
    {
        $t = self::ensureToken($conn, $api);
        if (!$t['ok']) {
            return ['ok' => false, 'error' => $t['error'], 'http_code' => 0];
        }
        return self::httpRequest('POST', self::url($api, 'start-session'), $t['token'],
            ['webhook' => null, 'waitQrCode' => false]);
    }

    /**
     * Retorna o QR. Pode vir como image/png cru ou JSON com data URL base64.
     * Retorno: ['ok'=>bool, 'content_type'=>string, 'body'=>bytes_image, 'error'=>?]
     */
    public static function qrCode(mysqli $conn, array $api): array
    {
        $t = self::ensureToken($conn, $api);
        if (!$t['ok']) {
            return ['ok' => false, 'error' => $t['error']];
        }
        $res = self::httpRequestRaw('GET', self::url($api, 'qrcode-session'), $t['token']);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'QR indisponível'];
        }

        $ct = (string)($res['content_type'] ?? '');
        if ($ct !== '' && strpos($ct, 'image/') === 0) {
            return ['ok' => true, 'content_type' => $ct, 'body' => $res['body']];
        }
        // JSON com data URL?
        $j = json_decode((string)$res['body'], true);
        $candidates = ['qrcode', 'qr', 'base64', 'data'];
        foreach ($candidates as $k) {
            if (isset($j[$k]) && is_string($j[$k]) && preg_match('#^data:(image/\w+);base64,(.+)$#', $j[$k], $m)) {
                return ['ok' => true, 'content_type' => $m[1], 'body' => base64_decode($m[2])];
            }
        }
        return ['ok' => false, 'error' => 'QR indisponível (resposta inesperada)'];
    }

    public static function logout(mysqli $conn, array $api): array
    {
        $t = self::ensureToken($conn, $api);
        if (!$t['ok']) {
            return ['ok' => false, 'error' => $t['error'], 'http_code' => 0];
        }
        return self::httpRequest('POST', self::url($api, 'logout-session'), $t['token']);
    }

    public static function close(mysqli $conn, array $api): array
    {
        $t = self::ensureToken($conn, $api);
        if (!$t['ok']) {
            return ['ok' => false, 'error' => $t['error'], 'http_code' => 0];
        }
        return self::httpRequest('POST', self::url($api, 'close-session'), $t['token']);
    }

    /** clear-session-data carrega o secret_key na URL — dispensa Bearer. */
    public static function clearSessionData(mysqli $conn, array $api): array
    {
        if (empty($api['base_url']) || empty($api['session_name']) || empty($api['secret_key'])) {
            return ['ok' => false, 'error' => 'base_url, session_name ou secret_key ausentes.', 'http_code' => 0];
        }
        $url = rtrim((string)$api['base_url'], '/')
             . '/api/' . $api['session_name'] . '/' . $api['secret_key'] . '/clear-session-data';
        return self::httpRequest('POST', $url, null, null);
    }

    /**
     * Fallback para bug do wppconnect-server v2: o controller
     * dist/controller/sessionController.js (logout) e
     * dist/controller/miscController.js (clearSessionData) usam
     *   __dirname + '../../../tokens/{session}.data.json'
     * com TRÊS níveis de '../', resolvendo para /opt/tokens/ (fora do
     * projeto) em vez de /opt/wppconnect-server/tokens/. Resultado: o
     * arquivo de token NÃO é apagado, e o celular continua mostrando
     * o aparelho como "Pendente".
     *
     * Este método tenta apagar o arquivo localmente como best-effort.
     * Só funciona quando o wppconnect roda na MESMA máquina do PHP.
     *
     * Path pode ser sobrescrito via env WPPCONNECT_TOKENS_DIR.
     */
    public static function limparTokenLocal(array $api): array
    {
        $session = trim((string)($api['session_name'] ?? ''));
        if ($session === '') {
            return ['ok' => true, 'http_code' => 0, 'mensagem' => 'sem session_name — nada a fazer'];
        }

        require_once __DIR__ . '/../../utils/env.php';
        $dir  = rtrim(env('WPPCONNECT_TOKENS_DIR', '/opt/wppconnect-server/tokens'), '/');
        $path = $dir . '/' . $session . '.data.json';

        if (!file_exists($path)) {
            // Já não existe — sucesso (cenário normal quando wppconnect funciona)
            return ['ok' => true, 'http_code' => 0, 'mensagem' => 'arquivo já não existia'];
        }
        if (!@unlink($path)) {
            return ['ok' => false, 'http_code' => 0, 'error' => "Sem permissão para apagar $path (verifique que /opt/wppconnect-server/tokens é root:www-data 770)"];
        }
        return ['ok' => true, 'http_code' => 0, 'mensagem' => 'token-file apagado'];
    }

    // ============================================================
    // HTTP helpers
    // ============================================================

    /** Para respostas JSON normais. */
    private static function httpRequest(string $method, string $url, ?string $token = null, $jsonBody = null): array
    {
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($token !== null && $token !== '') {
            $auth = stripos($token, 'bearer ') === 0 ? $token : 'Bearer ' . $token;
            $headers[] = 'Authorization: ' . $auth;
        }
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TOTAL_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CUSTOMREQUEST  => $method,
        ];
        if ($jsonBody !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($method === 'POST') {
            $opts[CURLOPT_POSTFIELDS] = '';
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $err !== '') {
            return ['ok' => false, 'http_code' => $code, 'error' => $err !== '' ? $err : 'falha desconhecida'];
        }
        return ['ok' => $code >= 200 && $code < 400, 'http_code' => $code, 'body' => (string)$body, 'error' => ($code >= 400 ? 'HTTP ' . $code : null)];
    }

    /** Para respostas binárias (QR image). */
    private static function httpRequestRaw(string $method, string $url, ?string $token = null): array
    {
        $ch = curl_init($url);
        $headers = ['Accept: image/*,application/json'];
        if ($token !== null && $token !== '') {
            $auth = stripos($token, 'bearer ') === 0 ? $token : 'Bearer ' . $token;
            $headers[] = 'Authorization: ' . $auth;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TOTAL_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ct   = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($body === false || $err !== '') {
            return ['ok' => false, 'http_code' => $code, 'error' => $err !== '' ? $err : 'falha'];
        }
        return ['ok' => $code >= 200 && $code < 400, 'http_code' => $code, 'body' => $body, 'content_type' => $ct];
    }
}
