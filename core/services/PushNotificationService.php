<?php
/**
 * PushNotificationService — envia push via Firebase Cloud Messaging HTTP v1.
 *
 * Lê credenciais de notificacoes_push_config (service_account_path aponta para
 * arquivo JSON da Service Account, guardado fora do webroot). Faz JWT bearer
 * flow para obter access token Google, cacheia o token (~55min) e envia para
 * o endpoint v1.
 *
 * Auto-desativa dispositivos cujo FCM retorna UNREGISTERED / INVALID_ARGUMENT
 * para o token.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;

class PushNotificationService {
    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const FCM_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const FCM_SEND_URL_TPL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    private const TOKEN_CACHE_PATH = '/var/backups/presenca/firebase/access_token_cache.json';
    private const TOKEN_TTL_BUFFER = 300;   // renova 5 min antes de expirar

    private static $config_cache = null;
    private static $access_token_cache = null;

    /**
     * Limpa caches estáticos (use após salvar nova config).
     */
    public static function limparCache(): void {
        self::$config_cache = null;
        self::$access_token_cache = null;
        if (is_file(self::TOKEN_CACHE_PATH)) {
            @unlink(self::TOKEN_CACHE_PATH);
        }
    }

    /**
     * Retorna se push está configurado e ativo no sistema.
     */
    public static function estaConfigurado(mysqli $conn): bool {
        $cfg = self::getConfig($conn);
        return $cfg
            && (int)$cfg['ativo'] === 1
            && !empty($cfg['project_id'])
            && !empty($cfg['service_account_path'])
            && is_readable($cfg['service_account_path']);
    }

    /**
     * Envia notificação para todos os dispositivos ativos de um usuário.
     *
     * @return array ['enviados' => int, 'falhas' => int, 'erros' => string[]]
     */
    public static function enviarParaUsuario(mysqli $conn, int $idUsuario, string $titulo, string $corpo, array $dados = []): array {
        $stmt = $conn->prepare("SELECT id, fcm_token, plataforma FROM notificacoes_push_dispositivos WHERE id_usuario = ? AND ativo = 1");
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $rs = $stmt->get_result();
        $dispositivos = [];
        while ($r = $rs->fetch_assoc()) $dispositivos[] = $r;
        $stmt->close();

        $enviados = 0;
        $falhas   = 0;
        $erros    = [];
        foreach ($dispositivos as $d) {
            $res = self::enviarParaDispositivo($conn, (int)$d['id'], $idUsuario, $d['fcm_token'], $d['plataforma'], $titulo, $corpo, $dados);
            if (!empty($res['sucesso'])) {
                $enviados++;
            } else {
                $falhas++;
                $erros[] = $res['mensagem'] ?? 'erro';
            }
        }
        return ['enviados' => $enviados, 'falhas' => $falhas, 'erros' => $erros, 'dispositivos' => count($dispositivos)];
    }

    /**
     * Envia para um token específico (não persistido). Útil pra teste rápido.
     */
    public static function enviarParaToken(mysqli $conn, string $fcmToken, string $titulo, string $corpo, array $dados = [], string $plataforma = 'android'): array {
        return self::enviarParaDispositivo($conn, 0, 0, $fcmToken, $plataforma, $titulo, $corpo, $dados);
    }

    /**
     * Envia para um dispositivo. Se id_dispositivo > 0, registra em
     * notificacoes_push_envios e desativa o dispositivo se o token for inválido.
     */
    private static function enviarParaDispositivo(mysqli $conn, int $idDispositivo, int $idUsuario, string $fcmToken, string $plataforma, string $titulo, string $corpo, array $dados): array {
        $cfg = self::getConfig($conn);
        if (!$cfg || (int)$cfg['ativo'] !== 1) {
            return ['sucesso' => false, 'mensagem' => 'Push notifications desativadas'];
        }
        if (empty($cfg['project_id']) || empty($cfg['service_account_path']) || !is_readable($cfg['service_account_path'])) {
            return ['sucesso' => false, 'mensagem' => 'Credenciais Firebase não configuradas'];
        }

        try {
            $accessToken = self::obterAccessToken($cfg['service_account_path']);
        } catch (Throwable $e) {
            return ['sucesso' => false, 'mensagem' => 'Falha ao obter access token: ' . $e->getMessage()];
        }

        // Garante valores de string em "data" (FCM exige string-only)
        $dataPayload = [];
        foreach ($dados as $k => $v) {
            $dataPayload[(string)$k] = is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE);
        }

        $message = [
            'token' => $fcmToken,
            'notification' => [
                'title' => $titulo,
                'body'  => $corpo,
            ],
            'android' => ['priority' => 'high', 'notification' => ['sound' => $cfg['som_padrao'] ?: 'default']],
            'apns'    => ['payload' => ['aps' => ['sound' => $cfg['som_padrao'] ?: 'default']]],
        ];
        if (!empty($dataPayload)) {
            $message['data'] = $dataPayload;
        }

        $url = sprintf(self::FCM_SEND_URL_TPL, rawurlencode($cfg['project_id']));
        $resp = self::httpJson($url, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=UTF-8',
        ], ['message' => $message]);

        $sucesso = ($resp['http_code'] >= 200 && $resp['http_code'] < 300);
        $messageId = null;
        $erroMsg = null;

        if ($sucesso) {
            $body = json_decode($resp['body'], true) ?: [];
            $messageId = $body['name'] ?? null;
        } else {
            $body = json_decode($resp['body'], true) ?: [];
            $erroMsg = $body['error']['message'] ?? ('HTTP ' . $resp['http_code']);
            $errorCode = $body['error']['details'][0]['errorCode'] ?? ($body['error']['status'] ?? '');

            // Token inválido → desativar dispositivo
            if ($idDispositivo > 0 && in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND', 'SENDER_ID_MISMATCH'], true)) {
                $stmt = $conn->prepare("UPDATE notificacoes_push_dispositivos SET ativo = 0 WHERE id = ?");
                $stmt->bind_param('i', $idDispositivo);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Registra envio (se for dispositivo real, não anônimo)
        if ($idDispositivo > 0 && $idUsuario > 0) {
            $payloadJson = json_encode(['titulo' => $titulo, 'corpo' => $corpo, 'data' => $dataPayload], JSON_UNESCAPED_UNICODE);
            $statusStr = $sucesso ? 'sucesso' : 'falha';
            $stmt = $conn->prepare("INSERT INTO notificacoes_push_envios (id_usuario, id_dispositivo, titulo, corpo, payload, status, erro, fcm_message_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iissssss', $idUsuario, $idDispositivo, $titulo, $corpo, $payloadJson, $statusStr, $erroMsg, $messageId);
            $stmt->execute();
            $stmt->close();
        }

        return [
            'sucesso' => $sucesso,
            'mensagem' => $sucesso ? 'Enviado' : $erroMsg,
            'fcm_message_id' => $messageId,
            'http_code' => $resp['http_code'],
        ];
    }

    /**
     * Lê config (cache estática).
     */
    private static function getConfig(mysqli $conn): ?array {
        if (self::$config_cache !== null) return self::$config_cache;
        $r = $conn->query("SELECT id, ativo, project_id, client_email, service_account_path, titulo_padrao, som_padrao FROM notificacoes_push_config WHERE id = 1");
        $cfg = $r ? $r->fetch_assoc() : null;
        self::$config_cache = $cfg ?: null;
        return self::$config_cache;
    }

    /**
     * Obtém access token Google. Usa cache em arquivo entre requests.
     */
    private static function obterAccessToken(string $serviceAccountPath): string {
        if (self::$access_token_cache && time() < (self::$access_token_cache['exp'] - self::TOKEN_TTL_BUFFER)) {
            return self::$access_token_cache['token'];
        }
        // Cache em arquivo
        if (is_file(self::TOKEN_CACHE_PATH)) {
            $cached = json_decode((string)@file_get_contents(self::TOKEN_CACHE_PATH), true);
            if (is_array($cached) && !empty($cached['token']) && !empty($cached['exp']) && time() < ($cached['exp'] - self::TOKEN_TTL_BUFFER)) {
                self::$access_token_cache = $cached;
                return $cached['token'];
            }
        }

        $sa = json_decode((string)file_get_contents($serviceAccountPath), true);
        if (!$sa || empty($sa['client_email']) || empty($sa['private_key'])) {
            throw new RuntimeException('Service Account JSON inválido (faltam client_email/private_key)');
        }

        $now = time();
        $payload = [
            'iss'   => $sa['client_email'],
            'scope' => self::FCM_SCOPE,
            'aud'   => self::FCM_TOKEN_URL,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];
        $jwt = JWT::encode($payload, $sa['private_key'], 'RS256');

        $resp = self::httpForm(self::FCM_TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]);
        if ($resp['http_code'] < 200 || $resp['http_code'] >= 300) {
            throw new RuntimeException('OAuth2 retornou HTTP ' . $resp['http_code'] . ': ' . substr($resp['body'], 0, 300));
        }
        $body = json_decode($resp['body'], true);
        if (empty($body['access_token']) || empty($body['expires_in'])) {
            throw new RuntimeException('Resposta OAuth2 sem access_token');
        }
        $cached = ['token' => $body['access_token'], 'exp' => $now + (int)$body['expires_in']];
        self::$access_token_cache = $cached;
        @file_put_contents(self::TOKEN_CACHE_PATH, json_encode($cached));
        @chmod(self::TOKEN_CACHE_PATH, 0640);
        return $body['access_token'];
    }

    private static function httpJson(string $url, array $headers, array $jsonBody): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($jsonBody, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            return ['http_code' => 0, 'body' => '{"error":{"message":"curl error: ' . addslashes($err) . '"}}'];
        }
        return ['http_code' => $code, 'body' => $body];
    }

    private static function httpForm(string $url, array $form): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($form),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['http_code' => $code, 'body' => $body === false ? '' : $body];
    }
}
