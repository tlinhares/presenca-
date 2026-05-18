<?php
/**
 * Script para Limpar Logs Antigos de Automação
 * Deve ser executado diariamente via cron
 * 0 0 * * * /usr/bin/php /var/www/html/presenca/limpar_logs_automacao.php
 */

$logs_dir = __DIR__ . '/logs';
$dias_manter = 30; // Manter logs dos últimos 30 dias

echo "=== LIMPEZA DE LOGS DE AUTOMAÇÃO ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n";
echo "Diretório: $logs_dir\n";
echo "Manter logs dos últimos $dias_manter dias\n\n";

if (!is_dir($logs_dir)) {
    echo "Diretório de logs não existe.\n";
    exit(0);
}

$arquivos_removidos = 0;
$espaco_liberado = 0;

// Buscar arquivos de log antigos
$arquivos = glob($logs_dir . '/automacao_cron_*.log');

foreach ($arquivos as $arquivo) {
    $data_arquivo = filemtime($arquivo);
    $dias_diferenca = (time() - $data_arquivo) / (24 * 60 * 60);
    
    if ($dias_diferenca > $dias_manter) {
        $tamanho = filesize($arquivo);
        if (unlink($arquivo)) {
            $arquivos_removidos++;
            $espaco_liberado += $tamanho;
            echo "Removido: " . basename($arquivo) . " (" . number_format($tamanho) . " bytes)\n";
        } else {
            echo "Erro ao remover: " . basename($arquivo) . "\n";
        }
    }
}

echo "\n=== RESUMO ===\n";
echo "Arquivos removidos: $arquivos_removidos\n";
echo "Espaço liberado: " . number_format($espaco_liberado) . " bytes (" . number_format($espaco_liberado / 1024, 2) . " KB)\n";
echo "=== FIM ===\n";
?>
