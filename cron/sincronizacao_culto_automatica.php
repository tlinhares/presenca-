<?php
// Script de cron para sincronização automática de usuários do culto
// Este script deve ser executado a cada 5 minutos via cron
// Exemplo de configuração no crontab:
// */5 * * * * /usr/bin/php /var/www/html/presenca/cron/sincronizacao_culto_automatica.php

// Configurar timezone a partir do banco de dados
require_once __DIR__ . '/../config/timezone.php';

// Log de execução
$logFile = __DIR__ . "/../logs/cron_culto_" . date('Y-m-d') . ".log";

require_once __DIR__ . '/../utils/logger.php';

function logCron($msg, $logFile = null) {
    Logger::emergencial('sincronizacao_culto_automatica', $msg);
}

logCron("=== INICIANDO CRON DE SINCRONIZAÇÃO DE CULTO ===", $logFile);

try {
    // Incluir conexão
    include_once(__DIR__ . '/../api/conexao.php');
    
    // Verificar se a tabela facial_sync_culto existe
    $result = $conn->query("SHOW TABLES LIKE 'facial_sync_culto'");
    if ($result->num_rows == 0) {
        logCron("ERRO: Tabela facial_sync_culto não existe", $logFile);
        exit(1);
    }
    
    // 1. Preparar sincronização (criar registros pendentes para usuários do culto)
    logCron("1. Preparando sincronização...", $logFile);
    
    $sql_preparar = "INSERT INTO facial_sync_culto (id_usuario, id_dispositivo, data, status, origem, tentativas, detalhes)
                     SELECT u.id, d.id, CURDATE(), 'pendente', 'culto', 0, 'Sincronização automática via cron'
                     FROM usuarios u
                     CROSS JOIN dispositivos_faciais d
                     WHERE u.culto = 1 
                     AND u.ativo = 1 
                     AND u.foto_base64 IS NOT NULL 
                     AND u.foto_base64 != ''
                     AND d.ativo = 1 
                     AND d.tipo_dispositivo = 'culto'
                     AND NOT EXISTS (
                         SELECT 1 FROM facial_sync_culto fs 
                         WHERE fs.id_usuario = u.id 
                         AND fs.id_dispositivo = d.id 
                         AND fs.data = CURDATE()
                     )";
    
    $result_preparar = $conn->query($sql_preparar);
    $registros_preparados = $conn->affected_rows;
    logCron("Registros preparados: $registros_preparados", $logFile);
    
    // 2. Processar sincronizações pendentes (máximo 10 por execução para não sobrecarregar)
    logCron("2. Processando sincronizações pendentes...", $logFile);
    
    $sql_pendentes = "SELECT fs.id, fs.id_usuario, fs.id_dispositivo, fs.tentativas,
                             u.nome, u.foto_base64,
                             d.nome as dispositivo_nome, d.ip, d.porta, d.usuario, d.senha
                      FROM facial_sync_culto fs
                      JOIN usuarios u ON fs.id_usuario = u.id
                      JOIN dispositivos_faciais d ON fs.id_dispositivo = d.id
                      WHERE fs.data = CURDATE() 
                      AND fs.status IN ('pendente', 'falha')
                      AND fs.tentativas < 3
                      ORDER BY fs.id
                      LIMIT 10";
    
    $result_pendentes = $conn->query($sql_pendentes);
    $total_pendentes = $result_pendentes->num_rows;
    logCron("Total de pendências processadas: $total_pendentes", $logFile);
    
    $sincronizados = 0;
    $falhas = 0;
    $dispositivo_stats = [];
    
    while ($row = $result_pendentes->fetch_assoc()) {
        $id_sync = $row['id'];
        $id_usuario = $row['id_usuario'];
        $id_dispositivo = $row['id_dispositivo'];
        $tentativas = $row['tentativas'];
        
        // Inicializar stats do dispositivo
        if (!isset($dispositivo_stats[$id_dispositivo])) {
            $dispositivo_stats[$id_dispositivo] = [
                'nome' => $row['dispositivo_nome'],
                'ip' => $row['ip'],
                'sincronizados' => 0,
                'falhas' => 0
            ];
        }
        
        // Preparar dados para envio
        $dados = [
            'UserID' => (string)$id_usuario,
            'Name' => $row['nome'],
            'PhotoData' => [$row['foto_base64']],
            'Type' => 'culto',
            'Status' => 'active'
        ];
        
        // Fazer requisição para o dispositivo
        $url = "http://{$row['ip']}:{$row['porta']}/cgi-bin/AccessFace.cgi?action=updateMulti";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($dados))
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch, CURLOPT_USERPWD, "{$row['usuario']}:{$row['senha']}");
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $resposta = curl_exec($ch);
        $erro = curl_error($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $tentativas++;
        
        if ($codigo == 200 && !$erro) {
            // Sucesso
            $sql_update = "UPDATE facial_sync_culto 
                          SET status = 'sincronizado', 
                              tentativas = ?, 
                              ultima_tentativa = NOW(), 
                              detalhes = ? 
                          WHERE id = ?";
            
            $stmt_update = $conn->prepare($sql_update);
            $detalhes = "Sincronização automática via cron realizada com sucesso. Código: $codigo";
            $stmt_update->bind_param("isi", $tentativas, $detalhes, $id_sync);
            $stmt_update->execute();
            $stmt_update->close();
            
            $sincronizados++;
            $dispositivo_stats[$id_dispositivo]['sincronizados']++;
            logCron("✓ Usuário '{$row['nome']}' sincronizado com sucesso no dispositivo {$row['dispositivo_nome']}", $logFile);
            
        } else {
            // Falha
            $sql_update = "UPDATE facial_sync_culto 
                          SET status = 'falha', 
                              tentativas = ?, 
                              ultima_tentativa = NOW(), 
                              detalhes = ? 
                          WHERE id = ?";
            
            $stmt_update = $conn->prepare($sql_update);
            $detalhes = "Falha na sincronização automática via cron. Código: $codigo, Erro: $erro";
            $stmt_update->bind_param("isi", $tentativas, $detalhes, $id_sync);
            $stmt_update->execute();
            $stmt_update->close();
            
            $falhas++;
            $dispositivo_stats[$id_dispositivo]['falhas']++;
            logCron("✗ Falha ao sincronizar usuário '{$row['nome']}' no dispositivo {$row['dispositivo_nome']}. Código: $codigo, Erro: $erro", $logFile);
        }
    }
    
    // 3. Log de resumo por dispositivo
    logCron("3. Resumo por dispositivo:", $logFile);
    foreach ($dispositivo_stats as $stats) {
        logCron("  - {$stats['nome']} ({$stats['ip']}): {$stats['sincronizados']} sincronizados, {$stats['falhas']} falhas", $logFile);
    }
    
    // 4. Log final
    logCron("=== CRON DE SINCRONIZAÇÃO CONCLUÍDA ===", $logFile);
    logCron("Registros preparados: $registros_preparados", $logFile);
    logCron("Total processado: " . ($sincronizados + $falhas), $logFile);
    logCron("Sincronizados: $sincronizados", $logFile);
    logCron("Falhas: $falhas", $logFile);
    
    // 5. Limpar logs antigos (manter apenas últimos 7 dias)
    $sql_limpar = "DELETE FROM facial_sync_culto WHERE data < DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $result_limpar = $conn->query($sql_limpar);
    $registros_limpos = $conn->affected_rows;
    if ($registros_limpos > 0) {
        logCron("Logs antigos removidos: $registros_limpos registros", $logFile);
    }
    
    logCron("=== CRON FINALIZADO COM SUCESSO ===", $logFile);
    
} catch (Exception $e) {
    $erro_msg = "ERRO no cron de sincronização: " . $e->getMessage();
    logCron($erro_msg, $logFile);
    exit(1);
}

$conn->close();
?>

