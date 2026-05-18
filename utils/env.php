<?php
/**
 * Helper mínimo de variáveis de ambiente.
 *
 * Carrega /var/www/html/presenca/.env (no diretório raiz do projeto) e
 * expõe a função env() para obter valores. Sem dependência externa.
 *
 * Formato suportado em .env:
 *   CHAVE=valor
 *   CHAVE="valor com espaços"
 *   CHAVE='valor literal'
 *   # comentários iniciados com #
 *
 * Uso:
 *   require_once __DIR__ . '/utils/env.php';
 *   $senha = env('DB_PASS');
 *   $host  = env('DB_HOST', 'localhost'); // segundo arg = default
 */

if (!function_exists('env')) {

    /**
     * Carrega o .env uma única vez (cache em estática).
     * @internal
     */
    function _env_load(): array
    {
        static $loaded = null;
        if ($loaded !== null) {
            return $loaded;
        }
        $loaded = [];
        $path = dirname(__DIR__) . '/.env';
        if (!is_readable($path)) {
            return $loaded;
        }
        $linhas = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if ($linha === '' || $linha[0] === '#') {
                continue;
            }
            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $linha, $m)) {
                continue;
            }
            $chave = $m[1];
            $valor = trim($m[2]);
            // remove aspas envolventes
            if (strlen($valor) >= 2) {
                $primeiro = $valor[0];
                $ultimo   = $valor[-1];
                if (($primeiro === '"' && $ultimo === '"') || ($primeiro === "'" && $ultimo === "'")) {
                    $valor = substr($valor, 1, -1);
                }
            }
            $loaded[$chave] = $valor;
        }
        return $loaded;
    }

    /**
     * Retorna uma variável de ambiente do arquivo .env.
     * Se a chave não existir e $default for null, retorna null (e quem chamou
     * pode tratar). Para um erro mais explícito, passar $default = false e
     * verificar com identidade.
     */
    function env(string $chave, $default = null)
    {
        // Variáveis reais do ambiente do processo têm prioridade (útil em CI/cron)
        $val = getenv($chave);
        if ($val !== false && $val !== '') {
            return $val;
        }
        $loaded = _env_load();
        return array_key_exists($chave, $loaded) ? $loaded[$chave] : $default;
    }
}
