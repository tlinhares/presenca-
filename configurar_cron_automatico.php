<?php
/**
 * Script para configurar cron job automático de sincronização facial
 */

echo "=== CONFIGURADOR DE CRON AUTOMÁTICO ===\n\n";

// Caminho do script de cron
$scriptPath = __DIR__ . '/cron/sincronizacao_facial_automatica.php';
$cronCommand = "*/5 * * * * /usr/bin/php $scriptPath >> /var/log/sincronizacao_facial.log 2>&1";

echo "Script de cron: $scriptPath\n";
echo "Comando do cron: $cronCommand\n\n";

// Verificar se o script existe
if (!file_exists($scriptPath)) {
    echo "❌ ERRO: Script de cron não encontrado em $scriptPath\n";
    exit(1);
}

echo "✅ Script de cron encontrado\n";

// Verificar se o diretório de logs existe
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    if (mkdir($logsDir, 0755, true)) {
        echo "✅ Diretório de logs criado: $logsDir\n";
    } else {
        echo "❌ ERRO: Não foi possível criar o diretório de logs\n";
        exit(1);
    }
} else {
    echo "✅ Diretório de logs existe: $logsDir\n";
}

// Verificar permissões
if (!is_writable($logsDir)) {
    echo "❌ ERRO: Diretório de logs não tem permissão de escrita\n";
    exit(1);
}

echo "✅ Diretório de logs tem permissão de escrita\n\n";

// Instruções para configurar o cron
echo "=== INSTRUÇÕES PARA CONFIGURAR O CRON ===\n\n";
echo "1. Execute o comando abaixo para editar o crontab:\n";
echo "   crontab -e\n\n";
echo "2. Adicione a seguinte linha no final do arquivo:\n";
echo "   $cronCommand\n\n";
echo "3. Salve e saia do editor (Ctrl+X, Y, Enter no nano)\n\n";
echo "4. Verifique se o cron foi adicionado com:\n";
echo "   crontab -l\n\n";

// Tentar adicionar automaticamente (opcional)
echo "=== TENTATIVA DE CONFIGURAÇÃO AUTOMÁTICA ===\n";
echo "Deseja tentar configurar o cron automaticamente? (s/n): ";

$handle = fopen("php://stdin", "r");
$input = trim(fgets($handle));
fclose($handle);

if (strtolower($input) === 's' || strtolower($input) === 'sim') {
    // Tentar adicionar ao crontab
    $tempFile = tempnam(sys_get_temp_dir(), 'cron_');
    
    // Obter crontab atual
    exec('crontab -l 2>/dev/null', $currentCron, $returnCode);
    
    if ($returnCode !== 0) {
        $currentCron = [];
    }
    
    // Verificar se já existe
    $cronExists = false;
    foreach ($currentCron as $line) {
        if (strpos($line, 'sincronizacao_facial_automatica.php') !== false) {
            $cronExists = true;
            break;
        }
    }
    
    if ($cronExists) {
        echo "✅ Cron job já existe no crontab\n";
    } else {
        // Adicionar nova linha
        $currentCron[] = $cronCommand;
        
        // Escrever para arquivo temporário
        file_put_contents($tempFile, implode("\n", $currentCron) . "\n");
        
        // Instalar novo crontab
        exec("crontab $tempFile", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "✅ Cron job adicionado com sucesso!\n";
        } else {
            echo "❌ ERRO: Falha ao adicionar cron job automaticamente\n";
            echo "Execute manualmente: crontab -e\n";
        }
        
        // Limpar arquivo temporário
        unlink($tempFile);
    }
} else {
    echo "Configuração manual necessária.\n";
}

echo "\n=== VERIFICAÇÃO ===\n";
echo "Para verificar se o cron está funcionando:\n";
echo "1. Aguarde 5 minutos\n";
echo "2. Verifique os logs: tail -f /var/log/sincronizacao_facial.log\n";
echo "3. Ou verifique os logs do sistema: $logsDir/cron_sincronizacao_" . date('Y-m-d') . ".log\n\n";

echo "=== TESTE MANUAL ===\n";
echo "Para testar manualmente, execute:\n";
echo "php $scriptPath\n\n";

echo "Configuração concluída!\n";
?>
