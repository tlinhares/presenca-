<?php
/**
 * Logger emergencial — registra apenas mensagens com indicadores de erro/falha.
 * Mensagens informativas (progresso, happy path) são descartadas.
 *
 * Os arquivos de log antigos (notificacoes.log, cron_remocoes.log, etc) seguem
 * referenciados por compatibilidade nas funções locais dos crons, mas viraram
 * no-op para tudo que não é erro.
 */

class Logger
{
    private const LOG_FILE_REL = '/../logs/erros.log';
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB
    private const KEEP_LINES_ON_TRUNCATE = 1000;

    private const ERROR_KEYWORDS = [
        'ERRO', 'Erro', 'erro',
        'ERROR', 'Error', 'error',
        'EXCEPTION', 'Exception', 'exception',
        'FALHA', 'Falha', 'falha',
        'FAIL', 'fail',
        'CRITICAL', 'critical',
        '✗',
    ];

    public static function erro(string $origem, string $mensagem, array $contexto = []): void
    {
        $logFile = __DIR__ . self::LOG_FILE_REL;
        $linha = sprintf('[%s] [%s] %s', date('Y-m-d H:i:s'), $origem, $mensagem);
        if (!empty($contexto)) {
            $linha .= ' | ' . json_encode($contexto, JSON_UNESCAPED_UNICODE);
        }
        $linha .= PHP_EOL;

        @file_put_contents($logFile, $linha, FILE_APPEND);

        $size = @filesize($logFile);
        if ($size !== false && $size > self::MAX_SIZE_BYTES) {
            self::truncar($logFile);
        }
    }

    public static function isErroMensagem(string $mensagem): bool
    {
        foreach (self::ERROR_KEYWORDS as $kw) {
            if (strpos($mensagem, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Helper para wrappers das funções locais de log: persiste apenas se a
     * mensagem casar com um dos indicadores de erro. Caso contrário, no-op.
     */
    public static function emergencial(string $origem, string $mensagem): void
    {
        if (self::isErroMensagem($mensagem)) {
            self::erro($origem, $mensagem);
        }
    }

    private static function truncar(string $logFile): void
    {
        $linhas = @file($logFile);
        if (!$linhas) {
            return;
        }
        $manter = array_slice($linhas, -self::KEEP_LINES_ON_TRUNCATE);
        @file_put_contents($logFile, implode('', $manter));
    }
}
