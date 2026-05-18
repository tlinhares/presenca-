<?php
/**
 * Script para testar notificação diária manualmente
 */

echo "<h2>Teste de Notificação Diária</h2>";

// Simular execução do script
echo "<h3>Executando script de notificação...</h3>";

// Capturar output do script
ob_start();
include 'cron/notificacao_diaria.php';
$output = ob_get_clean();

echo "<h3>Output do Script:</h3>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Verificar se o log foi criado
$logFile = __DIR__ . '/logs/notificacoes.log';
if (file_exists($logFile)) {
    echo "<h3>Conteúdo do Log:</h3>";
    $logContent = file_get_contents($logFile);
    echo "<pre>" . htmlspecialchars($logContent) . "</pre>";
} else {
    echo "<p><strong>❌ Arquivo de log não encontrado:</strong> $logFile</p>";
}

// Verificar se a pasta de logs existe
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    echo "<p><strong>⚠️ Pasta de logs não existe:</strong> $logsDir</p>";
    echo "<p>Tentando criar pasta...</p>";
    if (mkdir($logsDir, 0755, true)) {
        echo "<p><strong>✅ Pasta de logs criada com sucesso!</strong></p>";
    } else {
        echo "<p><strong>❌ Erro ao criar pasta de logs</strong></p>";
    }
}
?>
