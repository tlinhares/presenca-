<?php
declare(strict_types=1);

/**
 * IntelbrasDriver — CRUD mínimo no dispositivo facial Intelbras.
 *
 * Suporta:
 *  - Linha SS (SS3530, SS3540, …) via CGI `AccessUser.cgi` com Digest auth.
 *  - Linha XPE (XPE3200, …) via API REST `/api/user/del` com Basic auth.
 *
 * Estratégia herdada do projeto `acesso` (/var/www/html/acesso/api/facial/
 * ss3530_driver.php) — esse projeto já roda em produção há tempos com os
 * mesmos dispositivos físicos. Para CADA endpoint tenta múltiplos métodos
 * (GET com querystring, POST JSON, POST form) e múltiplos modos de auth
 * (Digest, Basic, none) — firmwares Intelbras divergem entre versões.
 *
 * Antes desse driver, o cron de remoção do presenca usava
 * `recordUpdater.cgi?action=delete`, que NÃO existe nos firmwares e
 * sempre devolvia HTTP 501.
 */
class IntelbrasDriver
{
    private string $base;
    private string $user;
    private string $pass;
    private array  $authModes;
    private bool   $isXpe;
    private int    $timeout;

    public function __construct(string $ip, $port, string $user, string $pass, string $modelo = '', int $timeout = 15)
    {
        $port = (int) $port > 0 ? (int) $port : 80;
        $this->base = "http://{$ip}:{$port}";
        $this->user = $user;
        $this->pass = $pass;
        $this->timeout = $timeout;

        $modelo = strtoupper(trim($modelo));
        $this->isXpe = ($modelo !== '' && strpos($modelo, 'XPE') !== false);

        // XPE prefere Basic, SS prefere Digest. Em ambos, fallback automático.
        $this->authModes = $this->isXpe
            ? ['basic', 'digest', 'none']
            : ['digest', 'basic', 'none'];
    }

    /**
     * Remove um usuário do dispositivo facial.
     *
     * @return array{ok:bool, http:int, raw:string, err:string, auth:string,
     *                url:string, path:string, mode:string}
     */
    public function deleteUser(int $id): array
    {
        $uid = (string) $id;

        // ---------- Caminho XPE (REST /api/user/del) ----------
        if ($this->isXpe) {
            $payloads = [
                ['target' => 'user', 'action' => 'del',    'data' => ['item' => [['UserID' => $uid]]]],
                ['target' => 'user', 'action' => 'delete', 'data' => ['item' => [['UserID' => $uid]]]],
                ['target' => 'user', 'action' => 'remove', 'data' => ['item' => [['UserID' => $uid]]]],
            ];
            foreach (['/api/user/del', '/api/user/delete'] as $path) {
                foreach ($payloads as $payload) {
                    $r = $this->req('POST', $path, ['json' => $payload]);
                    if ($r['ok']) {
                        // XPE retorna retcode=0 em sucesso
                        $json = json_decode($r['raw'], true);
                        if (is_array($json) && (int) ($json['retcode'] ?? -1) === 0) {
                            return $r + ['path' => $path, 'mode' => 'xpe-json'];
                        }
                    }
                }
            }
            // Se XPE específico falhou, deixa cair no fallback CGI SS abaixo —
            // alguns firmwares XPE também aceitam o caminho CGI.
        }

        // ---------- Caminho SS (CGI AccessUser.cgi) ----------
        $paths = [
            '/cgi-bin/AccessUser.cgi?action=removeMulti',
            '/cgi-bin/AccessUser.cgi?action=remove',
            '/cgi-bin/AccessControl.cgi?action=removeUser',
        ];
        $jsonPayloads = [
            ['UserIDList' => [$uid]],
            ['UserID' => $uid],
            ['Users' => [['UserID' => $uid]]],
        ];
        $formPayloads = [
            ['UserIDList[0]' => $uid],
            ['UserID' => $uid],
            ['Users[0].UserID' => $uid],
        ];

        $last = ['ok' => false, 'http' => 0, 'raw' => '', 'err' => '', 'auth' => '', 'url' => ''];

        foreach ($paths as $path) {
            // 1) GET com querystring — firmwares SS3530 frequentemente só aceitam assim.
            $qsParam = (stripos($path, 'removeMulti') !== false)
                ? 'UserIDList[0]=' . rawurlencode($uid)
                : 'UserID=' . rawurlencode($uid);
            $sep = (strpos($path, '?') === false) ? '?' : '&';
            $qsPath = $path . $sep . $qsParam;
            $r = $this->req('GET', $qsPath);
            if ($r['ok']) return $r + ['path' => $qsPath, 'mode' => 'get'];
            $last = $r;

            // 2) POST JSON, 3 variantes de payload.
            foreach ($jsonPayloads as $i => $p) {
                $r = $this->req('POST', $path, ['json' => $p]);
                if ($r['ok']) return $r + ['path' => $path, 'mode' => 'json'];
                $last = $r;

                // 3) POST form, mesmo índice.
                $r2 = $this->req('POST', $path, ['form' => $formPayloads[$i] ?? $formPayloads[0]]);
                if ($r2['ok']) return $r2 + ['path' => $path, 'mode' => 'form'];
                $last = $r2;
            }
        }

        return $last + ['path' => $paths[0], 'mode' => 'fail'];
    }

    /**
     * Lista usuários cadastrados no dispositivo.
     *
     * Estratégia herdada do projeto `acesso` (wipe_and_resync.php):
     *  - SS: tenta `recordFinder.cgi?action=find&name=AccessUserInfo` (mais
     *    completo, retorna detalhes), depois `AccessUser.cgi?action=list`
     *    (firmwares antigos).
     *  - XPE: `POST /api/user/get` com 2 variantes de payload.
     *
     * O endpoint atual do presenca usava `AccessUser.cgi?action=getAll` —
     * action que NÃO existe nos firmwares (por isso o HTTP 501).
     *
     * @return array{ok:bool, http:int, raw:string, err:string, auth:string,
     *                url:string, path:string, mode:string, usuarios:array}
     */
    public function listUsers(): array
    {
        // ---------- XPE primeiro (REST) ----------
        if ($this->isXpe) {
            $payloads = [
                ['target' => 'user', 'action' => 'get', 'data' => new stdClass()],
                ['target' => 'user', 'action' => 'get'],
            ];
            foreach ($payloads as $payload) {
                $r = $this->req('POST', '/api/user/get', ['json' => $payload, 'timeout' => 30]);
                if (!$r['ok']) continue;
                $json = json_decode($r['raw'], true);
                if (!is_array($json) || (int) ($json['retcode'] ?? -1) !== 0) continue;
                $items = $json['data']['item'] ?? $json['data'] ?? [];
                if (!is_array($items)) continue;
                return $r + [
                    'path' => '/api/user/get',
                    'mode' => 'xpe-json',
                    'usuarios' => self::parseUserList($items, true),
                ];
            }
            // Se XPE não respondeu, cai no fallback CGI (alguns firmwares aceitam).
        }

        // ---------- SS (CGI) ----------
        // recordFinder.cgi retorna formato key=value detalhado; AccessUser.cgi?action=list
        // retorna o mesmo em firmwares mais novos. Tenta os dois.
        $paths = [
            '/cgi-bin/recordFinder.cgi?action=find&name=AccessUserInfo&condition.Type=0',
            '/cgi-bin/recordFinder.cgi?action=find&name=AccessUserInfo',
            '/cgi-bin/AccessUser.cgi?action=list',
        ];

        $last = ['ok' => false, 'http' => 0, 'raw' => '', 'err' => '', 'auth' => '', 'url' => ''];
        foreach ($paths as $path) {
            $r = $this->req('GET', $path, ['timeout' => 30]);
            if ($r['ok']) {
                return $r + [
                    'path' => $path,
                    'mode' => 'cgi-text',
                    'usuarios' => self::parseUserList($r['raw'], false),
                ];
            }
            $last = $r;
        }
        return $last + ['path' => $paths[0], 'mode' => 'fail', 'usuarios' => []];
    }

    /**
     * Faz parse da listagem de usuários (JSON XPE ou texto key=value SS).
     */
    private static function parseUserList($raw, bool $isXpe): array
    {
        $usuarios = [];

        // XPE: $raw é já array decodificado
        if ($isXpe && is_array($raw)) {
            foreach ($raw as $item) {
                if (!is_array($item)) continue;
                $uid = trim((string) ($item['UserID'] ?? $item['userID'] ?? $item['userId'] ?? ''));
                if ($uid === '') continue;
                $usuarios[] = [
                    'user_id'    => $uid,
                    'user_name'  => (string) ($item['UserName'] ?? $item['userName'] ?? ''),
                    'user_type'  => (int)    ($item['UserType'] ?? 0),
                    'authority'  => (int)    ($item['Authority'] ?? 0),
                    'valid_from' => (string) ($item['ValidFrom'] ?? ''),
                    'valid_to'   => (string) ($item['ValidTo']   ?? ''),
                ];
            }
            return $usuarios;
        }

        // SS: texto key=value tipo "records[0].UserID=123\nrecords[0].UserName=...\n"
        $texto = (string) $raw;
        $idxMap = [];
        // Pega tanto "records[0]." quanto "UserList[0]." (varia por firmware).
        if (preg_match_all('/(?:records|UserList)\[(\d+)\]\.(\w+)=([^\r\n]*)/i', $texto, $m, PREG_SET_ORDER)) {
            foreach ($m as $line) {
                $idx = (int) $line[1];
                $key = $line[2];
                $val = trim($line[3]);
                if (!isset($idxMap[$idx])) {
                    $idxMap[$idx] = ['user_id' => '', 'user_name' => '', 'user_type' => 0,
                                     'authority' => 0, 'valid_from' => '', 'valid_to' => '', 'card_no' => ''];
                }
                switch (strtolower($key)) {
                    case 'userid':    $idxMap[$idx]['user_id']    = $val; break;
                    case 'username':  $idxMap[$idx]['user_name']  = $val; break;
                    case 'usertype':  $idxMap[$idx]['user_type']  = (int) $val; break;
                    case 'authority': $idxMap[$idx]['authority']  = (int) $val; break;
                    case 'validfrom': $idxMap[$idx]['valid_from'] = $val; break;
                    case 'validto':   $idxMap[$idx]['valid_to']   = $val; break;
                    case 'cardno':    $idxMap[$idx]['card_no']    = $val; break;
                }
            }
        }
        // remove entradas sem user_id (algumas vezes o firmware retorna "condition.X=...")
        foreach ($idxMap as $entry) {
            if ($entry['user_id'] !== '' && stripos($entry['user_id'], 'condition') === false) {
                $usuarios[] = $entry;
            }
        }
        return $usuarios;
    }

    /**
     * Executa uma requisição HTTP com fallback de autenticação automático.
     */
    private function req(string $method, string $path, array $opts = []): array
    {
        $url = $this->base . $path;
        $last = ['ok' => false, 'http' => 0, 'raw' => '', 'err' => '', 'auth' => '', 'url' => $url];

        foreach ($this->authModes as $mode) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER    => true,
                CURLOPT_CUSTOMREQUEST     => $method,
                CURLOPT_CONNECTTIMEOUT    => 8,
                CURLOPT_TIMEOUT           => (int) ($opts['timeout'] ?? $this->timeout),
                CURLOPT_SSL_VERIFYPEER    => false,
                CURLOPT_SSL_VERIFYHOST    => false,
                CURLOPT_FOLLOWLOCATION    => true,
                CURLOPT_MAXREDIRS         => 3,
                CURLOPT_UNRESTRICTED_AUTH => true,
                CURLOPT_POSTREDIR         => CURL_REDIR_POST_ALL,
            ]);

            if ($mode === 'digest' && $this->user !== '') {
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                curl_setopt($ch, CURLOPT_USERPWD, $this->user . ':' . $this->pass);
            } elseif ($mode === 'basic' && $this->user !== '') {
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_USERPWD, $this->user . ':' . $this->pass);
            }

            $headers = [];
            if (isset($opts['json'])) {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(
                    $opts['json'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ));
            } elseif (isset($opts['form'])) {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opts['form']));
            }
            if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $raw  = (string) curl_exec($ch);
            $err  = (string) curl_error($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $ok = ($err === '' && $http >= 200 && $http < 300);
            $last = ['ok' => $ok, 'http' => $http, 'raw' => $raw, 'err' => $err, 'auth' => $mode, 'url' => $url];

            if ($ok) return $last;
            // Só insiste trocando auth quando foi 401; outros códigos (404/501/500) → próxima combinação externa.
            if ($http !== 401) return $last;
        }

        return $last;
    }
}
