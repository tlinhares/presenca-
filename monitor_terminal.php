<?php
/**
 * Script para monitorar leituras faciais em tempo real via terminal
 * Uso: php monitor_terminal.php
 */

require_once 'api/conexao.php';

echo "=== MONITOR DE LEITURA FACIAL - TEMPO REAL ===\n";
echo "Pressione Ctrl+C para sair\n\n";

$logFile = __DIR__ . '/logs/leitura_facial_culto_' . date('Y-m-d') . '.log';
$lastSize = 0;

if (file_exists($logFile)) {
    $lastSize = filesize($logFile);
}

echo "Monitorando arquivo: $logFile\n";
echo "Iniciado em: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("-", 80) . "\n";

while (true) {
    if (file_exists($logFile)) {
        $currentSize = filesize($logFile);
        
        if ($currentSize > $lastSize) {
            // Novas linhas foram adicionadas
            $handle = fopen($logFile, 'r');
            fseek($handle, $lastSize);
            
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (!empty($line)) {
                    $timestamp = date('H:i:s');
                    
                    // Colorir baseado no tipo de log
                    if (strpos($line, 'ERRO:') !== false) {
                        echo "\033[31m[$timestamp] $line\033[0m\n"; // Vermelho
                    } elseif (strpos($line, 'Presença') !== false || strpos($line, 'registrada') !== false) {
                        echo "\033[32m[$timestamp] $line\033[0m\n"; // Verde
                    } elseif (strpos($line, 'Recebida') !== false) {
                        echo "\033[33m[$timestamp] $line\033[0m\n"; // Amarelo
                    } else {
                        echo "\033[37m[$timestamp] $line\033[0m\n"; // Branco
                    }
                }
            }
            
            fclose($handle);
            $lastSize = $currentSize;
        }
    }
    
    sleep(1); // Verificar a cada segundo
}
?>
