<?php
/**
 * API para processar sincronização automática de usuários do culto
 * Este script deve ser executado via cron para manter usuários sincronizados
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/timezone.php';
include_once(__DIR__ . '/../../api/conexao.php');

$logFile = __DIR__ . "/../../logs/sincronizacao_culto_automatica_" . date('Y-m-d') . ".log";

function logConsole($msg, $logFile) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

logConsole("=== INICIANDO SINCRONIZAÇÃO AUTOMÁTICA DE CULTO ===", $logFile);

try {
    // 1. Preparar sincronização (criar registros pendentes)
    logConsole("1. Preparando sincronização...", $logFile);
    
    $sql_preparar = "INSERT INTO facial_sync_culto (id_usuario, id_dispositivo, data, status, origem, tentativas, detalhes)
                     SELECT u.id, d.id, CURDATE(), 'pendente', 'culto', 0, 'Sincronização automática'
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
    logConsole("Registros preparados: $registros_preparados", $logFile);
    
    // 2. Processar sincronizações pendentes
    logConsole("2. Processando sincronizações pendentes...", $logFile);
    
    $sql_pendentes = "SELECT fs.id, fs.id_usuario, fs.id_dispositivo, fs.tentativas,
                             u.nome, u.foto_base64,
                             d.nome as dispositivo_nome, d.ip, d.porta, d.usuario, d.senha
                      FROM facial_sync_culto fs
                      JOIN usuarios u ON fs.id_usuario = u.id
                      JOIN dispositivos_faciais d ON fs.id_dispositivo = d.id
                      WHERE fs.data = CURDATE() 
                      AND fs.status IN ('pendente', 'falha')
                      AND fs.tentativas < 3
                      ORDER BY fs.id";
    
    $result_pendentes = $conn->query($sql_pendentes);
    $total_pendentes = $result_pendentes->num_rows;
    logConsole("Total de pendências encontradas: $total_pendentes", $logFile);
    
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
        
        // 1. Primeiro, adicionar o usuário
        $hoje = date('Y-m-d H:i:s');
        $valido_ate = date('Y-m-d H:i:s', strtotime('+1 year'));
        
        $dados_usuario = [
            "UserList" => [
                [
                    "UserID" => (string)$id_usuario,
                    "UserName" => $row['nome'],
                    "UserType" => 0, // General user
                    "Authority" => 2, // Normal user (não administrador)
                    "Password" => "123456", // Senha padrão
                    "Doors" => [0], // Todas as portas
                    "TimeSections" => [255], // Sempre permitido
                    "ValidFrom" => $hoje,
                    "ValidTo" => $valido_ate
                ]
            ]
        ];
        
        $url_usuario = "http://{$row['ip']}:{$row['porta']}/cgi-bin/AccessUser.cgi?action=insertMulti";
        
        $ch_usuario = curl_init();
        curl_setopt($ch_usuario, CURLOPT_URL, $url_usuario);
        curl_setopt($ch_usuario, CURLOPT_POST, true);
        curl_setopt($ch_usuario, CURLOPT_POSTFIELDS, json_encode($dados_usuario));
        curl_setopt($ch_usuario, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($dados_usuario))
        ]);
        curl_setopt($ch_usuario, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_usuario, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch_usuario, CURLOPT_USERPWD, "{$row['usuario']}:{$row['senha']}");
        curl_setopt($ch_usuario, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch_usuario, CURLOPT_CONNECTTIMEOUT, 5);
        
        $resposta_usuario = curl_exec($ch_usuario);
        $erro_usuario = curl_error($ch_usuario);
        $codigo_usuario = curl_getinfo($ch_usuario, CURLINFO_HTTP_CODE);
        curl_close($ch_usuario);
        
        $tentativas++;
        
        if ($codigo_usuario == 200 && !$erro_usuario) {
            // 2. Se usuário foi adicionado com sucesso, adicionar a foto
            $dados_foto = [
                "FaceList" => [
                    [
                        "UserID" => (string)$id_usuario,
                        "PhotoData" => [$row['foto_base64']]
                    ]
                ]
            ];
            
            $url_foto = "http://{$row['ip']}:{$row['porta']}/cgi-bin/AccessFace.cgi?action=insertMulti";
            
            $ch_foto = curl_init();
            curl_setopt($ch_foto, CURLOPT_URL, $url_foto);
            curl_setopt($ch_foto, CURLOPT_POST, true);
            curl_setopt($ch_foto, CURLOPT_POSTFIELDS, json_encode($dados_foto));
            curl_setopt($ch_foto, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($dados_foto))
            ]);
            curl_setopt($ch_foto, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_foto, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($ch_foto, CURLOPT_USERPWD, "{$row['usuario']}:{$row['senha']}");
            curl_setopt($ch_foto, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch_foto, CURLOPT_CONNECTTIMEOUT, 5);
            
            $resposta_foto = curl_exec($ch_foto);
            $erro_foto = curl_error($ch_foto);
            $codigo_foto = curl_getinfo($ch_foto, CURLINFO_HTTP_CODE);
            curl_close($ch_foto);
            
            if ($codigo_foto == 200 && !$erro_foto) {
                // Sucesso completo - usuário e foto sincronizados
                $sql_update = "UPDATE facial_sync_culto 
                              SET status = 'sincronizado', 
                                  tentativas = ?, 
                                  ultima_tentativa = NOW(), 
                                  detalhes = ? 
                              WHERE id = ?";
                
                $stmt_update = $conn->prepare($sql_update);
                $detalhes = "Sincronização automática realizada com sucesso. Usuário: $codigo_usuario, Foto: $codigo_foto";
                $stmt_update->bind_param("isi", $tentativas, $detalhes, $id_sync);
                $stmt_update->execute();
                $stmt_update->close();
                
                $sincronizados++;
                $dispositivo_stats[$id_dispositivo]['sincronizados']++;
                logConsole("✓ Usuário '{$row['nome']}' sincronizado com sucesso no dispositivo {$row['dispositivo_nome']}", $logFile);
            } else {
                // Usuário foi adicionado, mas foto falhou
                $sql_update = "UPDATE facial_sync_culto 
                              SET status = 'falha', 
                                  tentativas = ?, 
                                  ultima_tentativa = NOW(), 
                                  detalhes = ? 
                              WHERE id = ?";
                
                $stmt_update = $conn->prepare($sql_update);
                $detalhes = "Usuário adicionado, mas falha na foto. Usuário: $codigo_usuario, Foto: $codigo_foto, Erro: $erro_foto";
                $stmt_update->bind_param("isi", $tentativas, $detalhes, $id_sync);
                $stmt_update->execute();
                $stmt_update->close();
                
                $falhas++;
                $dispositivo_stats[$id_dispositivo]['falhas']++;
                logConsole("✗ Usuário '{$row['nome']}' adicionado, mas falha na foto no dispositivo {$row['dispositivo_nome']}. Código: $codigo_foto, Erro: $erro_foto", $logFile);
            }
        } else {
            // Falha ao adicionar usuário
            $sql_update = "UPDATE facial_sync_culto 
                          SET status = 'falha', 
                              tentativas = ?, 
                              ultima_tentativa = NOW(), 
                              detalhes = ? 
                          WHERE id = ?";
            
            $stmt_update = $conn->prepare($sql_update);
            $detalhes = "Falha ao adicionar usuário. Código: $codigo_usuario, Erro: $erro_usuario";
            $stmt_update->bind_param("isi", $tentativas, $detalhes, $id_sync);
            $stmt_update->execute();
            $stmt_update->close();
            
            $falhas++;
            $dispositivo_stats[$id_dispositivo]['falhas']++;
            logConsole("✗ Falha ao adicionar usuário '{$row['nome']}' no dispositivo {$row['dispositivo_nome']}. Código: $codigo_usuario, Erro: $erro_usuario", $logFile);
            
        }
    }
    
    // 3. Log de resumo por dispositivo
    logConsole("3. Resumo por dispositivo:", $logFile);
    foreach ($dispositivo_stats as $stats) {
        logConsole("  - {$stats['nome']} ({$stats['ip']}): {$stats['sincronizados']} sincronizados, {$stats['falhas']} falhas", $logFile);
    }
    
    // 4. Log final
    logConsole("=== SINCRONIZAÇÃO AUTOMÁTICA CONCLUÍDA ===", $logFile);
    logConsole("Total processado: " . ($sincronizados + $falhas), $logFile);
    logConsole("Sincronizados: $sincronizados", $logFile);
    logConsole("Falhas: $falhas", $logFile);
    
    // Obter estatísticas atualizadas após processamento
    $sql_stats_final = "SELECT 
                          COUNT(*) as total_usuarios_culto,
                          SUM(CASE WHEN u.foto_base64 IS NOT NULL AND u.foto_base64 != '' THEN 1 ELSE 0 END) as usuarios_com_foto,
                          SUM(CASE WHEN d.ativo = 1 AND d.tipo_dispositivo = 'culto' THEN 1 ELSE 0 END) as dispositivos_ativos
                        FROM usuarios u
                        CROSS JOIN dispositivos_faciais d
                        WHERE u.culto = 1 AND u.ativo = 1";
    
    $result_stats_final = $conn->query($sql_stats_final);
    $stats_final = $result_stats_final->fetch_assoc();
    
    // Status final de sincronização
    $sql_status_final = "SELECT 
                           fs.status,
                           COUNT(*) as quantidade
                         FROM facial_sync_culto fs
                         WHERE fs.data = CURDATE()
                         GROUP BY fs.status";
    
    $result_status_final = $conn->query($sql_status_final);
    $status_final = [];
    while ($row = $result_status_final->fetch_assoc()) {
        $status_final[$row['status']] = $row['quantidade'];
    }

    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Sincronização automática concluída',
        'registros_preparados' => $registros_preparados,
        'total_pendentes' => $total_pendentes,
        'sincronizados' => $sincronizados,
        'falhas' => $falhas,
        'dispositivos' => $dispositivo_stats,
        'estatisticas_finais' => $stats_final,
        'status_finais' => $status_final,
        'log_file' => $logFile
    ]);
    
} catch (Exception $e) {
    $erro_msg = "Erro na sincronização automática: " . $e->getMessage();
    logConsole("ERRO: $erro_msg", $logFile);
    
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $erro_msg,
        'log_file' => $logFile
    ]);
}

$conn->close();
?>