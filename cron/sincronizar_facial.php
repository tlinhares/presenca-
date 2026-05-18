<?php
/**
 * Script de cron para sincronização automática das reservas de almoço com o dispositivo facial SS3542
 * 
 * Este script deve ser executado por uma tarefa cron para automatizar o processo de sincronização
 * diária dos usuários com reservas de almoço no dispositivo de reconhecimento facial.
 * 
 * Exemplo de configuração cron (executar às 5:00 da manhã):
 * 0 5 * * * php /caminho/completo/para/cron/sincronizar_facial.php >> /caminho/para/logs/cron_facial.log 2>&1
 */

// Configurar tratamento de erros
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_time_limit(600); // 10 minutos (para permitir processamento mais longo)
ini_set('memory_limit', '256M'); // Aumentar limite de memória

// Função para registrar log
function log_message($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message" . PHP_EOL;
}

log_message("==== INÍCIO DA SINCRONIZAÇÃO FACIAL (CRON) ====<br>");

// Verificar se o script está sendo executado via CLI ou web
$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli) {
    log_message("AVISO: Este script deve ser executado via linha de comando.<br>");
}

try {
    // Determinar o caminho base do sistema
    $base_path = dirname(__DIR__);
    log_message("Caminho base: $base_path <br>");
    
    // Definir caminhos para os arquivos necessários
    $verificar_preparar_url = 'api/presenca/verificar_e_preparar.php';
    $executar_sync_url = 'api/presenca/executar_sync.php';
    
    // Data atual
    $data_hoje = date('Y-m-d');
    log_message("Data atual: $data_hoje<br>");
    
    // Iniciar processo
    log_message("Etapa 1: Verificando e preparando registros para sincronização...<br>");
    
    // Parâmetros para a sincronização
    $cron_param = 'cron=1'; // Indicador de que é uma execução via cron
    $data_param = "data=$data_hoje";
    $limite_param = "limite=30"; // Processar 30 usuários por vez
    $execucoes_param = "max_execucoes=10"; // Executar até 10 ciclos
    $intervalo_param = "intervalo=2"; // Intervalo de 2 segundos entre ciclos
    
    // Construir URLs completas com parâmetros
    $verificar_url = "$base_path/$verificar_preparar_url?$cron_param&$data_param";
    $executar_url = "$base_path/$executar_sync_url?$cron_param&$limite_param&$execucoes_param&$intervalo_param";
    
    // ETAPA 1: Verificar e preparar registros
    if ($is_cli) {
        // Via CLI, incluir diretamente os scripts PHP
        log_message("Executando verificação via include...<br>");
        
        // Redirecionar a saída para capturar o resultado
        ob_start();
        
        // Variáveis para simular o GET
        $_GET['cron'] = '1';
        $_GET['data'] = $data_hoje;
        
        // Incluir o script
        include("$base_path/$verificar_preparar_url");
        
        $resultado = ob_get_clean();
        
        // Processar resultado
        log_message("Resultado da verificação: $resultado.<br>");
    } else {
        // Via HTTP, usar curl para fazer a requisição
        log_message("Executando verificação via cURL...<br>");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $verificar_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $resultado = curl_exec($ch);
        $curl_error = curl_error($ch);
        
        if ($resultado === false) {
            log_message("ERRO ao executar verificação: $curl_error<br>");
        } else {
            log_message("Resultado da verificação: $resultado<br>");
        }
        
        curl_close($ch);
    }
    
    // ETAPA 2: Executar a sincronização
    log_message("Etapa 2: Executando sincronização...<br>");
    
    if ($is_cli) {
        // Via CLI, incluir diretamente o script
        log_message("Executando sincronização via include...<br>");
        
        // Redirecionar a saída para capturar o resultado
        ob_start();
        
        // Variáveis para simular o GET
        $_GET['cron'] = '1';
        $_GET['limite'] = '30';
        $_GET['max_execucoes'] = '10';
        $_GET['intervalo'] = '2';
        
        // Incluir o script
        include("$base_path/$executar_sync_url");
        
        $resultado = ob_get_clean();
        
        // Processar resultado
        log_message("Resultado da sincronização: $resultado<br>");
    } else {
        // Via HTTP, usar curl para fazer a requisição
        log_message("Executando sincronização via cURL...<br>");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $executar_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutos
        
        $resultado = curl_exec($ch);
        $curl_error = curl_error($ch);
        
        if ($resultado === false) {
            log_message("ERRO ao executar sincronização: $curl_error<br>");
        } else {
            log_message("Resultado da sincronização: $resultado<br>");
        }
        
        curl_close($ch);
    }
    
    log_message("Processo completo.<br>");
    
} catch (Exception $e) {
    log_message("ERRO CRÍTICO: " . $e->getMessage()."<br>");
}

log_message("==== FIM DA SINCRONIZAÇÃO FACIAL (CRON) ===="); 