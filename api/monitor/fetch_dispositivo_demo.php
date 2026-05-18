<?php
/**
 * API de demonstração para monitor de leitura facial
 * Testa conectividade e simula dados para demonstração
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';
require_once '../../config/timezone.php';

function logLeituraFacial($mensagem) {
    $logFile = __DIR__ . '/../../logs/leitura_facial_culto_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $mensagem" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function testarConectividade($ip, $user, $pass) {
    $endpoint = "http://{$ip}/cgi-bin/global.cgi?action=getCurrentTime";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint,
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => $user . ":" . $pass,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 10,
    ]);
    
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [$code, $body, $error];
}

try {
    $data_hoje = date('Y-m-d');
    $leituras_processadas = 0;
    $eventos = [];
    $dispositivos_online = 0;
    
    // Buscar dispositivos do tipo 'culto' ativos
    $stmt = $conn->prepare("SELECT * FROM dispositivos_faciais WHERE tipo_dispositivo = 'culto' AND ativo = 1");
    if ($stmt) {
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        while ($dispositivo = $resultado->fetch_assoc()) {
            $ip = $dispositivo['ip'];
            $user = $dispositivo['usuario'];
            $pass = $dispositivo['senha'];
            
            // Testar conectividade
            [$code, $body, $error] = testarConectividade($ip, $user, $pass);
            
            if ($code >= 200 && $code < 300) {
                $dispositivos_online++;
                logLeituraFacial("Dispositivo {$dispositivo['nome']} online (HTTP $code)");
                
                // Simular evento para demonstração (apenas se não há leituras hoje)
                $stmt_check = $conn->prepare("SELECT COUNT(*) as total FROM presencas_culto WHERE data = ? AND tipo_confirmacao = 'facial'");
                $stmt_check->bind_param("s", $data_hoje);
                $stmt_check->execute();
                $resultado_check = $stmt_check->get_result();
                $row_check = $resultado_check->fetch_assoc();
                $stmt_check->close();
                
                if ($row_check['total'] == 0) {
                    // Simular uma leitura para demonstração
                    $evento_simulado = [
                        'user_id' => '1', // Assumindo usuário ID 1
                        'card' => '12345',
                        'face' => 'FACE_001',
                        'event_type' => 'FaceRecognition',
                        'result' => 'Pass',
                        'time' => date('Y-m-d H:i:s'),
                        'raw' => ['UserID' => '1', 'EventType' => 'FaceRecognition', 'Pass' => 'Pass']
                    ];
                    
                    $eventos[] = $evento_simulado;
                    
                    // Processar a leitura simulada
                    if (processarLeituraFacial($evento_simulado)) {
                        $leituras_processadas++;
                    }
                }
                
                // Atualizar status como online
                $stmt_update = $conn->prepare("UPDATE dispositivos_faciais SET ultima_sincronizacao = NOW(), status_conexao = 'online' WHERE id = ?");
                $stmt_update->bind_param("i", $dispositivo['id']);
                $stmt_update->execute();
                $stmt_update->close();
                
            } else {
                logLeituraFacial("Dispositivo {$dispositivo['nome']} offline (HTTP $code) - $error");
                
                // Atualizar status como offline
                $stmt_update = $conn->prepare("UPDATE dispositivos_faciais SET status_conexao = 'offline' WHERE id = ?");
                $stmt_update->bind_param("i", $dispositivo['id']);
                $stmt_update->execute();
                $stmt_update->close();
            }
        }
        
        $stmt->close();
    } else {
        logLeituraFacial("ERRO: Falha ao buscar dispositivos: " . $conn->error);
    }
    
    // Buscar estatísticas reais do banco de dados
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM presencas_culto WHERE data = ? AND tipo_confirmacao = 'facial'");
    if ($stmt) {
        $stmt->bind_param("s", $data_hoje);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $row = $resultado->fetch_assoc();
        $total_leituras = $row['total'];
        $stmt->close();
    } else {
        $total_leituras = 0;
    }
    
    // Última leitura facial
    $stmt = $conn->prepare("SELECT horario_confirmacao FROM presencas_culto WHERE data = ? AND tipo_confirmacao = 'facial' ORDER BY data_cadastro DESC LIMIT 1");
    $ultima_leitura = 'Nenhuma';
    if ($stmt) {
        $stmt->bind_param("s", $data_hoje);
        $stmt->execute();
        $resultado = $stmt->get_result();
        if ($resultado->num_rows > 0) {
            $row = $resultado->fetch_assoc();
            $ultima_leitura = $row['horario_confirmacao'];
        }
        $stmt->close();
    }
    
    // Buscar logs do arquivo
    $logs = [];
    $logFile = __DIR__ . '/../../logs/leitura_facial_culto_' . $data_hoje . '.log';
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $logLines = explode("\n", trim($logContent));
        $logLines = array_slice($logLines, -20);
        
        foreach ($logLines as $line) {
            if (trim($line)) {
                if (preg_match('/^\[([^\]]+)\]\s*(.+)$/', $line, $matches)) {
                    $logs[] = [
                        'timestamp' => $matches[1],
                        'mensagem' => $matches[2],
                        'tipo' => strpos($matches[2], 'ERRO') !== false ? 'error' : 
                                 (strpos($matches[2], 'sucesso') !== false ? 'success' : 'info')
                    ];
                }
            }
        }
    }
    
    $estatisticas = [
        'total_leituras' => $total_leituras,
        'dispositivos_ativos' => $dispositivos_online,
        'ultima_leitura' => $ultima_leitura,
        'status_sistema' => $dispositivos_online > 0 ? 'Online' : 'Offline'
    ];
    
    // Log da consulta
    logLeituraFacial("Monitor consultado - Total: $total_leituras, Dispositivos online: $dispositivos_online");
    
    echo json_encode([
        'success' => true,
        'message' => 'Monitor funcionando',
        'estatisticas' => $estatisticas,
        'logs' => $logs,
        'eventos' => $eventos,
        'leituras_processadas' => $leituras_processadas,
        'used' => [
            'method' => 'GET',
            'timezone_format' => 'local',
            'name' => 'dispositivos_faciais_demo'
        ]
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    logLeituraFacial("ERRO: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
}

function processarLeituraFacial($evento) {
    global $conn;
    
    try {
        $user_id = $evento['user_id'];
        $event_type = $evento['event_type'];
        $result = $evento['result'];
        $time = $evento['time'];
        
        // Verificar se é leitura facial válida
        if ($event_type !== 'FaceRecognition' && $event_type !== 'CardRecognition') {
            return false;
        }
        
        if ($result !== 'Pass') {
            return false;
        }
        
        // Buscar usuário na tabela usuarios
        $stmt = $conn->prepare("SELECT id, nome, email FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        if ($resultado->num_rows === 0) {
            logLeituraFacial("Usuário não encontrado: $user_id");
            return false;
        }
        
        $usuario = $resultado->fetch_assoc();
        $stmt->close();
        
        // Buscar configurações do culto
        $stmt = $conn->prepare("SELECT horario_inicio, horario_fim, tolerancia_atraso FROM configuracoes_culto WHERE id = 1");
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        if ($resultado->num_rows === 0) {
            logLeituraFacial("Configurações do culto não encontradas");
            return false;
        }
        
        $config = $resultado->fetch_assoc();
        $stmt->close();
        
        // Converter horários para comparação
        $data_hoje = date('Y-m-d');
        $horario_leitura = date('H:i:s', strtotime($time));
        $horario_inicio = $config['horario_inicio'];
        $horario_fim = $config['horario_fim'];
        $tolerancia_atraso = $config['tolerancia_atraso'];
        
        // Determinar status da presença
        $status = '';
        $falta = 0;
        $presente = 0;
        $atrasado = 0;
        $observacoes = '';
        
        if ($horario_leitura >= $horario_inicio && $horario_leitura <= $tolerancia_atraso) {
            // Presente: entre horário de início e tolerância
            $status = 'presente';
            $presente = 1;
            $observacoes = 'Leitura facial - Presente';
        } elseif ($horario_leitura > $tolerancia_atraso && $horario_leitura <= $horario_fim) {
            // Atrasado: entre tolerância e horário fim
            $status = 'atrasado';
            $atrasado = 1;
            $observacoes = 'Leitura facial - Atrasado';
        } else {
            // Falta: após horário fim
            $status = 'falta';
            $falta = 1;
            $observacoes = 'Leitura facial - Falta (após horário)';
        }
        
        // Verificar se já existe presença para este usuário hoje
        $stmt = $conn->prepare("SELECT id FROM presencas_culto WHERE id_usuario = ? AND data = ?");
        $stmt->bind_param("is", $user_id, $data_hoje);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        if ($resultado->num_rows > 0) {
            // Atualizar presença existente
            $stmt->close();
            $stmt = $conn->prepare("UPDATE presencas_culto SET 
                horario_confirmacao = ?, 
                tipo_confirmacao = 'facial', 
                status = ?, 
                falta = ?, 
                presente = ?, 
                atrasado = ?, 
                observacoes = ?,
                data_cadastro = NOW()
                WHERE id_usuario = ? AND data = ?");
            $stmt->bind_param("ssiiiss", $horario_leitura, $status, $falta, $presente, $atrasado, $observacoes, $user_id, $data_hoje);
            
            if ($stmt->execute()) {
                $stmt->close();
                logLeituraFacial("Presença atualizada para usuário $user_id: $status");
                return true;
            } else {
                $stmt->close();
                logLeituraFacial("Erro ao atualizar presença: " . $conn->error);
                return false;
            }
        } else {
            // Inserir nova presença
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO presencas_culto 
                (id_usuario, data, horario_confirmacao, tipo_confirmacao, status, falta, presente, atrasado, observacoes, data_cadastro) 
                VALUES (?, ?, ?, 'facial', ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("isssiiis", $user_id, $data_hoje, $horario_leitura, $status, $falta, $presente, $atrasado, $observacoes);
            
            if ($stmt->execute()) {
                $stmt->close();
                logLeituraFacial("Nova presença inserida para usuário $user_id: $status");
                return true;
            } else {
                $stmt->close();
                logLeituraFacial("Erro ao inserir presença: " . $conn->error);
                return false;
            }
        }
        
    } catch (Exception $e) {
        logLeituraFacial("ERRO ao processar leitura: " . $e->getMessage());
        return false;
    }
}
?>
