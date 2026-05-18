<?php
/**
 * Script para Executar Automação Manualmente (Teste)
 * Acesse: https://presenca.aom.org.br/executar_automacao_manual.php
 */

echo "<h2>Execução Manual de Automações</h2>";
echo "<p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// Executar o script de cron
$script_path = __DIR__ . '/executar_automacoes_cron.php';

if (!file_exists($script_path)) {
    echo "<p style='color: red;'><strong>Erro:</strong> Script não encontrado: $script_path</p>";
    exit;
}

echo "<h3>Executando Script de Automação...</h3>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; white-space: pre-wrap;'>";

// Capturar output
ob_start();
include $script_path;
$output = ob_get_clean();

echo htmlspecialchars($output);
echo "</div>";

echo "<hr>";
echo "<h3>Logs Gerados</h3>";
$log_file = __DIR__ . '/logs/automacao_cron_' . date('Y-m-d') . '.log';

if (file_exists($log_file)) {
    echo "<p><strong>Arquivo de log:</strong> $log_file</p>";
    echo "<p><strong>Tamanho:</strong> " . filesize($log_file) . " bytes</p>";
    echo "<p><strong>Última modificação:</strong> " . date('Y-m-d H:i:s', filemtime($log_file)) . "</p>";
    
    echo "<h4>Últimas 20 linhas do log:</h4>";
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; white-space: pre-wrap; max-height: 300px; overflow-y: auto;'>";
    $log_content = file_get_contents($log_file);
    $log_lines = explode("\n", $log_content);
    $last_lines = array_slice($log_lines, -20);
    echo htmlspecialchars(implode("\n", $last_lines));
    echo "</div>";
} else {
    echo "<p style='color: orange;'>Nenhum log encontrado ainda.</p>";
}

echo "<hr>";
echo "<p><a href='teste_automacao_cron.php'>🧪 Testar Automações</a> | <a href='configurar_cron.php'>⚙️ Configurar Cron</a> | <a href='painel/automacao_relatorios.php'>📊 Gerenciar Automações</a></p>";
?>
