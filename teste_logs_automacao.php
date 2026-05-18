<?php
/**
 * Script para Testar Logs de Automação
 * Acesse: https://presenca.aom.org.br/teste_logs_automacao.php
 */

echo "<h2>Teste de Logs de Automação</h2>";
echo "<p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// Executar o script de automação
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
echo "<h3>Análise dos Logs</h3>";

$log_file = __DIR__ . '/logs/automacao_cron_' . date('Y-m-d') . '.log';

if (file_exists($log_file)) {
    echo "<p><strong>Arquivo de log:</strong> $log_file</p>";
    echo "<p><strong>Tamanho:</strong> " . number_format(filesize($log_file)) . " bytes</p>";
    echo "<p><strong>Última modificação:</strong> " . date('Y-m-d H:i:s', filemtime($log_file)) . "</p>";
    
    // Contar linhas
    $log_content = file_get_contents($log_file);
    $log_lines = explode("\n", $log_content);
    $total_lines = count($log_lines) - 1; // -1 porque a última linha é vazia
    
    echo "<p><strong>Total de linhas:</strong> $total_lines</p>";
    
    // Buscar execuções de hoje
    $execucoes_hoje = 0;
    $envios_hoje = 0;
    $erros_hoje = 0;
    
    foreach ($log_lines as $line) {
        if (strpos($line, '=== INICIANDO EXECUÇÃO DE AUTOMAÇÕES ===') !== false) {
            $execucoes_hoje++;
        }
        if (strpos($line, 'Relatórios enviados:') !== false) {
            preg_match('/Relatórios enviados: (\d+)/', $line, $matches);
            if (isset($matches[1])) {
                $envios_hoje += (int)$matches[1];
            }
        }
        if (strpos($line, 'Erros:') !== false) {
            preg_match('/Erros: (\d+)/', $line, $matches);
            if (isset($matches[1])) {
                $erros_hoje += (int)$matches[1];
            }
        }
    }
    
    echo "<h4>Estatísticas de Hoje:</h4>";
    echo "<ul>";
    echo "<li><strong>Execuções:</strong> $execucoes_hoje</li>";
    echo "<li><strong>Relatórios enviados:</strong> $envios_hoje</li>";
    echo "<li><strong>Erros:</strong> $erros_hoje</li>";
    echo "</ul>";
    
    // Mostrar últimas 30 linhas
    echo "<h4>Últimas 30 linhas do log:</h4>";
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto;'>";
    $last_lines = array_slice($log_lines, -30);
    echo htmlspecialchars(implode("\n", $last_lines));
    echo "</div>";
    
} else {
    echo "<p style='color: orange;'>Nenhum log encontrado ainda.</p>";
}

echo "<hr>";
echo "<h3>Monitoramento em Tempo Real</h3>";
echo "<p>Para monitorar os logs em tempo real, execute no terminal:</p>";
echo "<code>tail -f $log_file</code>";

echo "<hr>";
echo "<h3>Verificar Cron</h3>";
echo "<p>Para verificar se o cron está configurado:</p>";
echo "<code>crontab -l</code>";

echo "<hr>";
echo "<p><a href='teste_automacao_cron.php'>🧪 Testar Automações</a> | <a href='configurar_cron.php'>⚙️ Configurar Cron</a> | <a href='painel/automacao_relatorios.php'>📊 Gerenciar Automações</a></p>";
?>
