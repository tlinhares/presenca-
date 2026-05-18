<?php
/**
 * Cron job para processar sincronizações automáticas
 * Executa a cada 5 minutos para processar ações pendentes dos triggers
 */

require_once __DIR__ . '/../api/conexao.php';
require_once __DIR__ . '/../config/timezone.php';

// Log do cron
$logFile = __DIR__ . '/../logs/cron_sync_automatica_' . date('Y-m-d') . '.log';

require_once __DIR__ . '/../utils/logger.php';

function logCron($mensagem) {
    Logger::emergencial('processar_sync_automatica', $mensagem);
}

try {
    logCron("Iniciando processamento de sincronizações automáticas");
    
    // Fazer requisição para a API de processamento
    $url = 'http://localhost' . dirname($_SERVER['PHP_SELF']) . '/api/culto/processar_sincronizacao_automatica.php';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: Cron-Sync-Automatica/1.0'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        throw new Exception("Erro cURL: $error");
    }
    
    if ($httpCode !== 200) {
        throw new Exception("Erro HTTP $httpCode: $response");
    }
    
    $data = json_decode($response, true);
    
    if ($data && $data['status'] === 'success') {
        $resultado = $data['data'];
        logCron("Processamento concluído - Adicionados: {$resultado['adicionados']}, Removidos: {$resultado['removidos']}, Falhas: {$resultado['total_falhas']}");
    } else {
        logCron("ERRO no processamento: " . ($data['message'] ?? 'Resposta inválida'));
    }
    
} catch (Exception $e) {
    logCron("ERRO: " . $e->getMessage());
}

$conn->close();
?>
