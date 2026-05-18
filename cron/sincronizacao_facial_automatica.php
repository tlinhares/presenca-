<?php
/**
 * Cron job para processar sincronizações faciais automaticamente
 * Executa a cada 5 minutos para processar usuários pendentes
 */

// Configurar timezone
date_default_timezone_set('America/Cuiaba');

// Log de execução
$logFile = __DIR__ . '/../logs/cron_sincronizacao_' . date('Y-m-d') . '.log';
$timestamp = date('Y-m-d H:i:s');

require_once __DIR__ . '/../utils/logger.php';

function logCron($mensagem) {
    Logger::emergencial('sincronizacao_facial_automatica', $mensagem);
}

logCron("=== INICIANDO CRON DE SINCRONIZAÇÃO FACIAL ===");

try {
    // Incluir arquivos necessários
    require_once __DIR__ . '/../api/conexao.php';
    require_once __DIR__ . '/../config/timezone.php';
    
    // Executar processamento automático diretamente
    $scriptPath = __DIR__ . '/../api/culto/processar_sincronizacao_automatica.php';
    
    // Capturar saída
    ob_start();
    include $scriptPath;
    $resultado = ob_get_clean();
    
    if ($resultado === false) {
        logCron("ERRO: Falha ao executar processamento automático");
        exit(1);
    }
    
    $dados = json_decode($resultado, true);
    
    if ($dados && $dados['status'] === 'sucesso') {
        logCron("SUCESSO: Sincronização automática concluída");
        logCron("Registros preparados: {$dados['registros_preparados']}");
        logCron("Total pendentes: {$dados['total_pendentes']}");
        logCron("Sincronizados: {$dados['sincronizados']}");
        logCron("Falhas: {$dados['falhas']}");
        
        // Log de estatísticas finais se disponível
        if (isset($dados['estatisticas_finais'])) {
            $stats = $dados['estatisticas_finais'];
            logCron("Usuários de culto: {$stats['total_usuarios_culto']}");
            logCron("Usuários com foto: {$stats['usuarios_com_foto']}");
            logCron("Dispositivos ativos: {$stats['dispositivos_ativos']}");
        }
        
        // Log de status finais se disponível
        if (isset($dados['status_finais'])) {
            $status = $dados['status_finais'];
            foreach ($status as $tipo => $quantidade) {
                logCron("Status '$tipo': $quantidade");
            }
        }
    } else {
        logCron("ERRO: Resposta inválida do processamento automático");
        logCron("Resposta: " . $resultado);
    }
    
} catch (Exception $e) {
    logCron("ERRO: " . $e->getMessage());
    exit(1);
}

logCron("=== CRON DE SINCRONIZAÇÃO FACIAL CONCLUÍDO ===");
?>
