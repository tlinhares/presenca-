<?php
/**
 * Script para Configurar Cron Automaticamente
 * Acesse: https://presenca.aom.org.br/configurar_cron.php
 */

echo "<h2>Configuração do Cron para Automação de Relatórios</h2>";

// Verificar se já existe a entrada no crontab
$cron_entry = "0 * * * * /usr/bin/php /var/www/html/presenca/executar_automacoes_cron.php";
$crontab_file = '/tmp/crontab_current';

// Obter crontab atual
exec('crontab -l 2>/dev/null', $current_crontab);

$cron_exists = false;
foreach ($current_crontab as $line) {
    if (strpos($line, 'executar_automacoes_cron.php') !== false) {
        $cron_exists = true;
        break;
    }
}

if ($cron_exists) {
    echo "<p style='color: green;'><strong>✅ Cron já configurado!</strong></p>";
    echo "<p>O cron já está configurado para executar de hora em hora.</p>";
} else {
    echo "<p style='color: orange;'><strong>⚠️ Cron não configurado</strong></p>";
    echo "<p>Para configurar manualmente, execute no terminal:</p>";
    echo "<code>crontab -e</code>";
    echo "<p>E adicione esta linha:</p>";
    echo "<code>$cron_entry</code>";
}

echo "<hr>";
echo "<h3>Informações do Sistema</h3>";

// Verificar se o PHP está disponível
$php_path = exec('which php');
echo "<p><strong>PHP Path:</strong> $php_path</p>";

// Verificar se o script existe
$script_path = '/var/www/html/presenca/executar_automacoes_cron.php';
echo "<p><strong>Script Path:</strong> $script_path</p>";
echo "<p><strong>Script existe:</strong> " . (file_exists($script_path) ? '✅ SIM' : '❌ NÃO') . "</p>";

// Verificar permissões
echo "<p><strong>Script executável:</strong> " . (is_executable($script_path) ? '✅ SIM' : '❌ NÃO') . "</p>";

// Verificar diretório de logs
$logs_dir = '/var/www/html/presenca/logs';
echo "<p><strong>Diretório de logs:</strong> $logs_dir</p>";
echo "<p><strong>Logs existem:</strong> " . (is_dir($logs_dir) ? '✅ SIM' : '❌ NÃO') . "</p>";

if (!is_dir($logs_dir)) {
    echo "<p style='color: blue;'>Criando diretório de logs...</p>";
    if (mkdir($logs_dir, 0755, true)) {
        echo "<p style='color: green;'>✅ Diretório de logs criado com sucesso!</p>";
    } else {
        echo "<p style='color: red;'>❌ Erro ao criar diretório de logs</p>";
    }
}

echo "<hr>";
echo "<h3>Teste Manual</h3>";
echo "<p>Para testar o script manualmente, execute:</p>";
echo "<code>/usr/bin/php $script_path</code>";

echo "<hr>";
echo "<h3>Monitoramento</h3>";
echo "<p>Para monitorar os logs em tempo real:</p>";
echo "<code>tail -f $logs_dir/automacao_cron_" . date('Y-m-d') . ".log</code>";

echo "<hr>";
echo "<p><a href='teste_automacao_cron.php'>🧪 Testar Automações</a> | <a href='painel/automacao_relatorios.php'>⚙️ Gerenciar Automações</a></p>";
?>
