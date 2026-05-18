<?php
/**
 * API para capturar dados em tempo real dos dispositivos faciais
 * Baseado nos arquivos events_realtime_sse.php e stream_events.php
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

function http_digest_get($url, $user, $pass) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => $user . ":" . $pass,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 20, // Timeout de 20 segundos (como no exemplo Python)
        CURLOPT_CONNECTTIMEOUT => 10, // Timeout de conexão
        CURLOPT_FOLLOWLOCATION => true, // Seguir redirecionamentos (HTTP 302)
        CURLOPT_MAXREDIRS => 5, // Máximo de 5 redirecionamentos
        CURLOPT_VERBOSE => false,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    // Log detalhado de erros
    if ($error) {
        logLeituraFacial("Erro cURL na URL $url: $error");
    }
    
    // Log de respostas de erro para debug
    if ($code >= 400) {
        $body_preview = substr((string)$body, 0, 200);
        logLeituraFacial("HTTP $code na URL: $url | URL efetiva: $effective_url | Resposta: $body_preview");
    }
    
    return [$code, (string)$body];
}

function capturarStreamDispositivo($endpoint, $user, $pass, $nomeDispositivo) {
    static $eventos_processados = [];
    $eventos = [];
    
    // Calcular offset de fuso
    $tzOffsetSec = 0;
    $parsed_url = parse_url($endpoint);
    $ip = $parsed_url['host'];
    $protocolo = isset($parsed_url['scheme']) ? $parsed_url['scheme'] : 'http';
    $porta = isset($parsed_url['port']) ? $parsed_url['port'] : (($protocolo == 'https') ? 443 : 80);
    $devTimeUrl = "{$protocolo}://{$ip}";
    if ($porta != 80 && $porta != 443) {
        $devTimeUrl .= ":{$porta}";
    }
    $devTimeUrl .= "/cgi-bin/global.cgi?action=getCurrentTime";
    $ch_time = curl_init();
    curl_setopt_array($ch_time, [
        CURLOPT_URL => $devTimeUrl,
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => $user . ":" . $pass,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 5,
    ]);
    $timeBody = curl_exec($ch_time);
    $timeCode = curl_getinfo($ch_time, CURLINFO_HTTP_CODE);
    curl_close($ch_time);
    
    if ($timeCode >= 200 && $timeCode < 300 && preg_match('/result\s*=\s*([0-9:\- ]{19})/i', $timeBody, $m)) {
        $deviceLocalStr = $m[1];
        $deviceLocalEpoch_assumingServerTZ = strtotime($deviceLocalStr);
        if ($deviceLocalEpoch_assumingServerTZ !== false) {
            $tzOffsetSec = $deviceLocalEpoch_assumingServerTZ - time();
        }
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint,
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => $user . ":" . $pass,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20, // Timeout de 20 segundos para capturar eventos
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true, // Seguir redirecionamentos (HTTP 302)
        CURLOPT_MAXREDIRS => 5, // Máximo de 5 redirecionamentos
        CURLOPT_WRITEFUNCTION => function($ch, $chunk) use (&$eventos, $tzOffsetSec, $nomeDispositivo) {
            static $buf = '';
            $buf .= $chunk;
            
            while (true) {
                $p = strpos($buf, "\r\n\r\n");
                if ($p === false) break;
                
                $headers = substr($buf, 0, $p);
                $rest = substr($buf, $p + 4);
                
                $len = null;
                if (preg_match('/Content-Length:\s*(\d+)/i', $headers, $m)) $len = (int)$m[1];
                
                if ($len !== null) {
                    if (strlen($rest) < $len) { $buf = $headers . "\r\n\r\n" . $rest; break; }
                    $body = substr($rest, 0, $len);
                    $buf = substr($rest, $len);
                } else {
                    $next = strpos($rest, "\r\n--");
                    if ($next === false) { $buf = $headers . "\r\n\r\n" . $rest; break; }
                    $body = substr($rest, 0, $next);
                    $buf = substr($rest, $next + 2);
                }
                
                $block = trim($body);
                if ($block === '') continue;
                
                // Ignorar heartbeats
                if ($block === 'Heartbeat') {
                    logLeituraFacial("Dispositivo $nomeDispositivo - Heartbeat recebido");
                    continue;
                }
                
                // JSON direto?
                $j = json_decode($block, true);
                if (is_array($j)) {
                    $j = enrichTime($j, $tzOffsetSec);
                    $eventos[] = $j;
                    logLeituraFacial("Dispositivo $nomeDispositivo - Evento capturado: " . json_encode($j));
                    continue;
                }
                
                // Usar a mesma lógica do stream_events.php que funciona
                $kv = parse_kv_block($block);
                $event = $kv;
                
                if (!empty($kv['data']) && is_string($kv['data'])) {
                    $dataJson = json_decode($kv['data'], true);
                    if (is_array($dataJson)) {
                        foreach ($dataJson as $k => $v) {
                            $event[$k] = $v;
                        }
                        logLeituraFacial("Dispositivo $nomeDispositivo - Dados JSON decodificados: " . json_encode($dataJson));
                    }
                }
                if (!empty($event)) {
                    $event = enrichTime($event, $tzOffsetSec);
                    
                    // Verificar se é evento _DoorFace_ e processar dados JSON
                    if (isset($event['Code']) && strpos($event['Code'], '_DoorFace_') !== false) {
                        // Log do evento _DoorFace_ (informação crucial)
                        $user_id_evento = $event['UserID'] ?? 'N/A';
                        $similarity = $event['Similarity'] ?? 'N/A';
                        $time_evento = $event['ISOTime'] ?? date('Y-m-d H:i:s');
                        logLeituraFacial("_DoorFace_ - Dispositivo: $nomeDispositivo | UserID: $user_id_evento | Similarity: $similarity | Time: $time_evento");
                        
                        // Processar leitura facial se tem UserID
                        if (isset($event['UserID'])) {
                            // Verificar se já processamos este evento recentemente (evitar duplicação)
                            // Usar timestamp mais preciso para evitar duplicação
                            $evento_key = $nomeDispositivo . '_' . $event['UserID'] . '_' . $event['ISOTime'];
                            if (!isset($eventos_processados[$evento_key])) {
                                $eventos_processados[$evento_key] = true;
                                
                                // Processar leitura (função já gera log do resultado)
                                processarLeituraFacial($event);
                            }
                            // Se já foi processado, não logar nada (evitar spam)
                        }
                        
                        $eventos[] = $event;
                    } else {
                        // Ignorar outros tipos de evento
                        logLeituraFacial("Dispositivo $nomeDispositivo - Evento ignorado (não é _DoorFace_): " . ($event['Code'] ?? 'sem código'));
                    }
                }
            }
            
            return strlen($chunk);
        }
    ]);
    
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        logLeituraFacial("Dispositivo $nomeDispositivo - Erro cURL no stream: $error");
    }
    
    if ($code >= 400) {
        logLeituraFacial("Dispositivo $nomeDispositivo - Stream retornou HTTP $code");
    } else if ($code >= 200 && $code < 300) {
        logLeituraFacial("Dispositivo $nomeDispositivo - Stream conectado (HTTP $code), eventos capturados: " . count($eventos));
    }
    
    return $eventos;
}

function parse_kv_block($block){
    $out = [];
    if (($pos = strpos($block, 'data=')) !== false) {
        $after = substr($block, $pos+5);
        $after = ltrim($after);
        if (strlen($after) && $after[0]==='{') {
            $json = capture_json($after);
            if ($json !== null) {
                $out['data'] = $json;
                $block = substr($block, 0, $pos);
            }
        }
    }
    $pairs = preg_split('/[;\r\n]+/', $block);
    foreach ($pairs as $pair) {
        $pair = trim($pair);
        if ($pair==='' || strpos($pair,'=')===false) continue;
        [$k,$v] = array_map('trim', explode('=',$pair,2));
        if ($k!=='') $out[$k] = $v;
    }
    return $out;
}

function capture_json($s){
    $depth=0; $inStr=false; $esc=false;
    for ($i=0;$i<strlen($s);$i++){
        $ch=$s[$i];
        if ($inStr){ if($esc){$esc=false;continue;} if($ch==='\\'){$esc=true;continue;} if($ch==='"'){$inStr=false;continue;} }
        else { if($ch==='"'){$inStr=true;continue;} if($ch==='{'){$depth++;} if($ch==='}'){ $depth--; if($depth===0){ return substr($s,0,$i+1);} } }
    }
    return null;
}

function enrichTime(array $o, int $offsetSec){
    // Converte epoch (RealUTC/UTC/UTCTime/Timestamp) para ISOTime aplicando offset do DISPOSITIVO
    $epoch = null;
    foreach (['RealUTC','UTC','UTCTime','Timestamp'] as $k){
        if (isset($o[$k]) && is_numeric($o[$k])) { $epoch = (int)$o[$k]; break; }
    }
    if ($epoch !== null && $epoch>0 && $epoch< 99999999999) {
        $o['ISOTime'] = date('Y-m-d H:i:s', $epoch + $offsetSec);
    }
    if (!isset($o['Event']) && isset($o['Code'])) $o['Event']=$o['Code'];
    if (!isset($o['UserID']) && isset($o['PersonID'])) $o['UserID']=$o['PersonID'];
    return $o;
}

function processarLeituraFacial($evento) {
    global $conn;
    
    try {
        // Extrair dados do evento _DoorFace_
        $user_id = $evento['UserID'] ?? null;
        $similarity = $evento['Similarity'] ?? 0;
        $time = $evento['ISOTime'] ?? date('Y-m-d H:i:s');
        
        // Verificar se tem UserID
        if (!$user_id) {
            return false;
        }
        
        // Verificar se a similaridade é aceitável (>= 80%)
        if ($similarity < 80) {
            return false;
        }
        
        // Buscar usuário na tabela usuarios
        $stmt = $conn->prepare("SELECT id, nome, email FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        if ($resultado->num_rows === 0) {
            return false;
        }
        
        $usuario = $resultado->fetch_assoc();
        $stmt->close();
        
        // Buscar configurações do culto
        $stmt = $conn->prepare("SELECT chave, valor FROM configuracoes_culto WHERE chave IN ('horario_inicio', 'horario_fim', 'tolerancia_atraso', 'dias_semana', 'permitir_atraso')");
        if (!$stmt) {
            return false;
        }
        
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        $config = [];
        while ($row = $resultado->fetch_assoc()) {
            $config[$row['chave']] = $row['valor'];
        }
        $stmt->close();
        
        // Valores padrão se não encontrar configurações
        $horario_inicio = $config['horario_inicio'] ?? '07:30:00';
        $horario_fim = $config['horario_fim'] ?? '08:30:00';
        $tolerancia_atraso = (int)($config['tolerancia_atraso'] ?? '15');
        $dias_semana = $config['dias_semana'] ?? '1,2,3,4,5';
        $permitir_atraso = (int)($config['permitir_atraso'] ?? '1'); // 1=permitir, 0=não permitir
        
        // Verificar se hoje é dia de culto
        $dia_atual = date('N'); // 1=segunda, 7=domingo
        $dias_culto = explode(',', $dias_semana);
        $dias_culto = array_map('trim', $dias_culto);
        
        if (!in_array($dia_atual, $dias_culto)) {
            // Log único e claro: leitura recebida mas não processada
            logLeituraFacial("→ Leitura recebida - Usuário {$usuario['nome']} (ID: $user_id) às $time - NÃO PROCESSADA: Hoje não é dia de culto (dia $dia_atual). Dias configurados: $dias_semana");
            return false; // Não processar a leitura
        }
        
        // Converter horários para comparação
        $data_hoje = date('Y-m-d');
        $horario_leitura = date('H:i:s', strtotime($time));
        
        // Calcular horário limite para presente (início + tolerância)
        $horario_limite_presente = date('H:i:s', strtotime($horario_inicio . ' +' . $tolerancia_atraso . ' minutes'));
        
        // Determinar status da presença
        $status = '';
        $observacoes = '';
        $status_display = '';
        
        if ($horario_leitura >= $horario_inicio && $horario_leitura <= $horario_limite_presente) {
            // Presente: entre horário de início e limite de tolerância
            $status = 'presente';
            $status_display = 'Presente';
            $observacoes = 'Leitura facial - Presente';
        } elseif ($horario_leitura > $horario_limite_presente && $horario_leitura <= $horario_fim) {
            // Verificar se atraso é permitido
            if ($permitir_atraso == 1) {
                // Atrasado: após tolerância mas antes do horário fim (permitido)
                $status = 'atrasado';
                $status_display = 'Atrasado';
                $observacoes = 'Leitura facial - Atrasado';
            } else {
                // Atrasado não permitido: tratar como falta
                $status = 'falta';
                $status_display = 'Falta (atraso não permitido)';
                $observacoes = 'Leitura facial - Falta (atraso não permitido)';
            }
        } else {
            // Fora de horário: antes do início ou após o fim
            $status = 'falta';
            $status_display = 'Fora de horário';
            $observacoes = 'Leitura facial - Fora de horário';
        }
        
        // Log único do resultado do processamento
        logLeituraFacial("→ Leitura recebida - Usuário {$usuario['nome']} (ID: $user_id) às $time - PROCESSADA: $status_display");
        
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
                observacoes = ?,
                data_cadastro = NOW()
                WHERE id_usuario = ? AND data = ?");
            $stmt->bind_param("sssis", $horario_leitura, $status, $observacoes, $user_id, $data_hoje);
            
            if ($stmt->execute()) {
                $stmt->close();
                return true;
            } else {
                $stmt->close();
                return false;
            }
        } else {
            // Inserir nova presença
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO presencas_culto 
                (id_usuario, data, horario_confirmacao, tipo_confirmacao, status, observacoes, data_cadastro) 
                VALUES (?, ?, ?, 'facial', ?, ?, NOW())");
            $stmt->bind_param("issss", $user_id, $data_hoje, $horario_leitura, $status, $observacoes);
            
            if ($stmt->execute()) {
                $stmt->close();
                return true;
            } else {
                $stmt->close();
                return false;
            }
        }
        
    } catch (Exception $e) {
        logLeituraFacial("ERRO ao processar leitura: " . $e->getMessage());
        return false;
    }
}

try {
    $data_hoje = date('Y-m-d');
    $leituras_processadas = 0;
    $eventos = [];
    
    // Buscar dispositivos do tipo 'culto' ativos
    $stmt = $conn->prepare("SELECT * FROM dispositivos_faciais WHERE tipo_dispositivo = 'culto' AND ativo = 1");
    if ($stmt) {
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        while ($dispositivo = $resultado->fetch_assoc()) {
            $ip = $dispositivo['ip'];
            $porta = $dispositivo['porta'] ?? 80;
            $user = $dispositivo['usuario'];
            $pass = $dispositivo['senha'];
            
            // Determinar protocolo baseado na porta
            // Se porta for 443, usar HTTPS; caso contrário, tentar HTTP primeiro
            $protocolo = ($porta == 443) ? 'https' : 'http';
            $url_base = "{$protocolo}://{$ip}";
            if ($porta != 80 && $porta != 443) {
                $url_base .= ":{$porta}";
            }
            
            // Se porta não é 443 mas dispositivo pode estar forçando HTTPS, tentar ambos
            $tentar_https = ($porta != 443);
            
            // Tentar capturar eventos do dispositivo usando diferentes métodos
            $eventos_dispositivo = [];
            
            // Método 1: attach com All para capturar todos os eventos e filtrar _DoorFace_
            $endpoint1 = "{$url_base}/cgi-bin/eventManager.cgi?action=attach&codes=[All]&heartbeat=5";
            logLeituraFacial("Dispositivo {$dispositivo['nome']} - Tentando método stream: $endpoint1");
            $eventos_dispositivo = capturarStreamDispositivo($endpoint1, $user, $pass, $dispositivo['nome']);
            
            // Se não funcionou e porta não é 443, tentar HTTPS
            if (empty($eventos_dispositivo) && $tentar_https) {
                $url_base_https = "https://{$ip}";
                $endpoint1_https = "{$url_base_https}/cgi-bin/eventManager.cgi?action=attach&codes=[All]&heartbeat=5";
                logLeituraFacial("Dispositivo {$dispositivo['nome']} - Tentando método stream com HTTPS: $endpoint1_https");
                $eventos_dispositivo = capturarStreamDispositivo($endpoint1_https, $user, $pass, $dispositivo['nome']);
                if (!empty($eventos_dispositivo)) {
                    $url_base = $url_base_https; // Usar HTTPS para próximas tentativas
                }
            }
            
            if (!empty($eventos_dispositivo)) {
                logLeituraFacial("Dispositivo {$dispositivo['nome']} - Método stream: " . count($eventos_dispositivo) . " eventos capturados");
            } else {
                logLeituraFacial("Dispositivo {$dispositivo['nome']} - Método stream não retornou eventos, tentando recordFinder");
                // Método 2: recordFinder (busca histórica) - Usando timestamp Unix como na documentação
                // Buscar eventos dos últimos 5 minutos (300 segundos)
                $start_time = time() - 300;
                $end_time = time();
                
                // Tentar diferentes tipos de eventos de acesso
                $tipos_eventos = [
                    'AccessControlFaceRec',  // Eventos de reconhecimento facial
                    'AccessControlEvent',    // Eventos gerais de controle de acesso
                    'AccessControlCardRec',  // Eventos de cartão (como no exemplo Python)
                ];
                
                $eventos_encontrados = false;
                foreach ($tipos_eventos as $tipo_evento) {
                    $endpoint2 = "{$url_base}/cgi-bin/recordFinder.cgi?action=find&name={$tipo_evento}&StartTime={$start_time}&EndTime={$end_time}";
                    logLeituraFacial("Dispositivo {$dispositivo['nome']} - Tentando recordFinder com tipo: {$tipo_evento}");
                    [$code2, $body2] = http_digest_get($endpoint2, $user, $pass);
                    
                    // Se HTTP 401/302 e não estamos usando HTTPS, tentar HTTPS
                    if (($code2 == 401 || $code2 == 302) && $tentar_https && strpos($url_base, 'https') === false) {
                        $url_base_https = "https://{$ip}";
                        $endpoint2_https = "{$url_base_https}/cgi-bin/recordFinder.cgi?action=find&name={$tipo_evento}&StartTime={$start_time}&EndTime={$end_time}";
                        logLeituraFacial("Dispositivo {$dispositivo['nome']} - Tentando recordFinder com HTTPS: $endpoint2_https");
                        [$code2, $body2] = http_digest_get($endpoint2_https, $user, $pass);
                        if ($code2 >= 200 && $code2 < 300) {
                            $url_base = $url_base_https; // Usar HTTPS para próximas tentativas
                        }
                    }
                    
                    if ($code2 >= 200 && $code2 < 300) {
                        logLeituraFacial("Dispositivo {$dispositivo['nome']} - Resposta recordFinder ({$tipo_evento}): " . substr($body2, 0, 500));
                        $eventos_temp = processarEventosDispositivo($body2);
                        if (!empty($eventos_temp)) {
                            $eventos_dispositivo = array_merge($eventos_dispositivo, $eventos_temp);
                            logLeituraFacial("Dispositivo {$dispositivo['nome']} - Método recordFinder ({$tipo_evento}): " . count($eventos_temp) . " eventos encontrados");
                            $eventos_encontrados = true;
                            break; // Parar após encontrar eventos em um tipo
                        }
                    } else {
                        logLeituraFacial("Dispositivo {$dispositivo['nome']} - recordFinder ({$tipo_evento}) retornou HTTP {$code2}");
                    }
                }
                
                if (!$eventos_encontrados) {
                    // Método 3: getCurrentTime (teste de conectividade)
                    $endpoint3 = "{$url_base}/cgi-bin/global.cgi?action=getCurrentTime";
                    [$code3, $body3] = http_digest_get($endpoint3, $user, $pass);
                    
                    if ($code3 >= 200 && $code3 < 300) {
                        logLeituraFacial("Dispositivo {$dispositivo['nome']} - Conectado mas sem eventos (HTTP $code3)");
                    } else {
                        logLeituraFacial("Dispositivo {$dispositivo['nome']} - Falha de conexão (HTTP $code3)");
                    }
                }
            }
            
            // Processar eventos capturados
            foreach ($eventos_dispositivo as $evento) {
                $eventos[] = $evento;
                
                // Verificar se é evento de reconhecimento facial
                // Pode ser _DoorFace_ (stream) ou AccessControlFaceRec (recordFinder)
                $code = $evento['Code'] ?? $evento['EventType'] ?? '';
                $is_facial_event = (
                    strpos($code, '_DoorFace_') !== false ||
                    strpos($code, 'AccessControlFaceRec') !== false ||
                    strpos($code, 'FaceRecognition') !== false ||
                    (isset($evento['UserID']) && !empty($evento['UserID']))
                );
                
                if ($is_facial_event) {
                    // Processar a leitura facial
                    if (processarLeituraFacial($evento)) {
                        $leituras_processadas++;
                    }
                } else {
                    logLeituraFacial("Dispositivo {$dispositivo['nome']} - Evento ignorado (não é facial): " . $code);
                }
            }
            
            // Atualizar status do dispositivo
            if (count($eventos_dispositivo) > 0) {
                $stmt_update = $conn->prepare("UPDATE dispositivos_faciais SET ultima_sincronizacao = NOW(), status_conexao = 'online' WHERE id = ?");
                $stmt_update->bind_param("i", $dispositivo['id']);
                $stmt_update->execute();
                $stmt_update->close();
            } else {
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
    
    // Contar dispositivos ativos (que conseguiram conectar)
    $dispositivos_ativos = 0;
    $stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM dispositivos_faciais WHERE tipo_dispositivo = 'culto' AND ativo = 1");
    if ($stmt_count) {
        $stmt_count->execute();
        $resultado_count = $stmt_count->get_result();
        if ($row = $resultado_count->fetch_assoc()) {
            $dispositivos_ativos = $row['total'];
        }
        $stmt_count->close();
    }
    
    $estatisticas = [
        'total_leituras' => $total_leituras,
        'dispositivos_ativos' => $dispositivos_ativos,
        'ultima_leitura' => $ultima_leitura,
        'status_sistema' => 'Online'
    ];
    
    // Buscar presenças do dia atual
    $presencas_hoje = [];
    $stmt_presencas = $conn->prepare("
        SELECT p.*, u.nome, u.email 
        FROM presencas_culto p 
        JOIN usuarios u ON p.id_usuario = u.id 
        WHERE p.data = ? 
        ORDER BY p.horario_confirmacao DESC
    ");
    if ($stmt_presencas) {
        $stmt_presencas->bind_param("s", $data_hoje);
        $stmt_presencas->execute();
        $resultado_presencas = $stmt_presencas->get_result();
        while ($presenca = $resultado_presencas->fetch_assoc()) {
            $presencas_hoje[] = $presenca;
        }
        $stmt_presencas->close();
    }
    
    // Log da consulta
    logLeituraFacial("Monitor consultado - Total: $total_leituras, Processadas: $leituras_processadas");
    
    echo json_encode([
        'success' => true,
        'message' => 'Dados capturados com sucesso',
        'estatisticas' => $estatisticas,
        'logs' => $logs,
        'eventos' => $eventos,
        'presencas' => $presencas_hoje,
        'leituras_processadas' => $leituras_processadas,
        'used' => [
            'method' => 'GET',
            'timezone_format' => 'local',
            'name' => 'dispositivos_faciais_tempo_real'
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

function processarEventosDispositivo($body) {
    $eventos = [];
    
    // Processar diferentes formatos de resposta do dispositivo
    if (empty($body)) {
        return $eventos;
    }
    
    logLeituraFacial("Resposta bruta do dispositivo: " . substr($body, 0, 500));
    
    // Processar formato multipart/x-mixed-replace (stream de dados)
    if (strpos($body, '--myboundary') !== false) {
        $partes = explode('--myboundary', $body);
        foreach ($partes as $parte) {
            $parte = trim($parte);
            if (empty($parte) || $parte === '--') continue;
            
            // Extrair conteúdo após headers
            $linhas = explode("\n", $parte);
            $conteudo = '';
            $em_conteudo = false;
            
            foreach ($linhas as $linha) {
                if ($em_conteudo) {
                    $conteudo .= $linha . "\n";
                } elseif (trim($linha) === '') {
                    $em_conteudo = true;
                }
            }
            
            $conteudo = trim($conteudo);
            if (!empty($conteudo)) {
                // Tentar processar como JSON
                $json = json_decode($conteudo, true);
                if (is_array($json)) {
                    $eventos[] = normalizarEvento($json);
                }
            }
        }
    }
    
    // Processar formato Server-Sent Events (SSE)
    if (empty($eventos)) {
        $linhas = explode("\n", $body);
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if (strpos($linha, 'data:') === 0) {
                $json_str = substr($linha, 5); // Remove "data:"
                $json = json_decode($json_str, true);
                if (is_array($json)) {
                    $eventos[] = normalizarEvento($json);
                }
            }
        }
    }
    
    // Se não encontrou eventos, tentar JSON direto com campo "data"
    if (empty($eventos)) {
        $json = json_decode($body, true);
        if (is_array($json)) {
            // Se tem campo "data" com JSON string, decodificar
            if (isset($json['data']) && is_string($json['data'])) {
                $data_json = json_decode($json['data'], true);
                if (is_array($data_json)) {
                    // Usar os dados do campo "data" como evento principal
                    $eventos[] = normalizarEvento($data_json);
                }
            } else {
                // Usar o JSON direto como evento
                $eventos[] = normalizarEvento($json);
            }
        }
    }
    
    // Se ainda não encontrou eventos, tentar outros formatos
    if (empty($eventos)) {
        $json = json_decode($body, true);
        if (is_array($json)) {
            if (isset($json['Events']) && is_array($json['Events'])) {
                foreach ($json['Events'] as $event) {
                    $eventos[] = normalizarEvento($event);
                }
            } elseif (isset($json['Event']) && is_array($json['Event'])) {
                foreach ($json['Event'] as $event) {
                    $eventos[] = normalizarEvento($event);
                }
            } elseif (isset($json['Items']) && is_array($json['Items'])) {
                foreach ($json['Items'] as $event) {
                    $eventos[] = normalizarEvento($event);
                }
            } elseif (isset($json['Records']) && is_array($json['Records'])) {
                foreach ($json['Records'] as $event) {
                    $eventos[] = normalizarEvento($event);
                }
            }
        } else {
            // Tentar XML
            if (strpos($body, '<') !== false) {
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($body);
                if ($xml !== false) {
                    $json = json_decode(json_encode($xml), true);
                    if (is_array($json)) {
                        $eventos = processarEventosDispositivo(json_encode($json));
                    }
                }
            }
            
            // Tentar formato key=value (formato comum do recordFinder)
            if (empty($eventos) && strpos($body, '=') !== false) {
                // O recordFinder pode retornar múltiplos eventos separados por linhas vazias ou delimitadores
                $blocos = preg_split('/\r?\n\r?\n/', $body); // Separar por blocos (linhas vazias)
                if (count($blocos) == 1) {
                    $blocos = explode("\n", $body); // Se não houver blocos, processar linha por linha
                }
                
                $evento_atual = [];
                foreach ($blocos as $bloco) {
                    $bloco = trim($bloco);
                    if (empty($bloco)) continue;
                    
                    $linhas = explode("\n", $bloco);
                    foreach ($linhas as $linha) {
                        $linha = trim($linha);
                        if (empty($linha) || strpos($linha, '=') === false) continue;
                        
                        [$key, $value] = explode('=', $linha, 2);
                        $key = trim($key);
                        $value = trim($value);
                        
                        // Se encontrar um campo que indica início de novo evento (como Code ou EventType)
                        if (($key === 'Code' || $key === 'EventType') && !empty($evento_atual)) {
                            // Salvar evento anterior e começar novo
                            if (!empty($evento_atual)) {
                                $eventos[] = normalizarEvento($evento_atual);
                            }
                            $evento_atual = [];
                        }
                        
                        $evento_atual[$key] = $value;
                    }
                    
                    // Se o bloco tem dados, adicionar evento
                    if (!empty($evento_atual)) {
                        $eventos[] = normalizarEvento($evento_atual);
                        $evento_atual = [];
                    }
                }
                
                // Adicionar último evento se houver
                if (!empty($evento_atual)) {
                    $eventos[] = normalizarEvento($evento_atual);
                }
            }
        }
    }
    
    return $eventos;
}

function normalizarEvento($event) {
    // Log do evento bruto para debug
    logLeituraFacial("Evento capturado: " . json_encode($event));
    
    // CORREÇÃO: Usar UserID (que é string) em vez de readID (que é interno do dispositivo)
    $user_id = $event['UserID'] ?? $event['PersonID'] ?? $event['EmployeeNo'] ?? $event['Employee'] ?? $event['ID'] ?? null;
    
    // Converter para inteiro se necessário
    if ($user_id && is_string($user_id)) {
        $user_id = (int) $user_id;
    }
    
    logLeituraFacial("UserID extraído: " . ($user_id ?? 'NULL') . " (tipo: " . gettype($user_id) . ")");
    
    // Se não encontrou user_id, tentar buscar por nome ou cartão
    if (!$user_id) {
        $nome = $event['Name'] ?? $event['UserName'] ?? null;
        $card = $event['CardNo'] ?? $event['CardID'] ?? $event['Card'] ?? null;
        
        if ($nome || $card) {
            // Buscar usuário por nome ou cartão na tabela usuarios
            global $conn;
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE nome LIKE ? OR cartao = ? LIMIT 1");
            $nome_like = "%$nome%";
            $stmt->bind_param("ss", $nome_like, $card);
            $stmt->execute();
            $resultado = $stmt->get_result();
            if ($resultado->num_rows > 0) {
                $row = $resultado->fetch_assoc();
                $user_id = $row['id'];
                logLeituraFacial("Usuário encontrado por nome/cartão: $user_id");
            }
            $stmt->close();
        }
    }
    
    // Processar timestamp - pode vir em diferentes formatos
    $time = null;
    if (isset($event['Time']) && is_numeric($event['Time'])) {
        // Timestamp Unix
        $time = date('Y-m-d H:i:s', $event['Time']);
    } elseif (isset($event['UTCTime']) && is_numeric($event['UTCTime'])) {
        // Timestamp UTC
        $time = date('Y-m-d H:i:s', $event['UTCTime']);
    } elseif (isset($event['DateTime'])) {
        $time = $event['DateTime'];
    } elseif (isset($event['ISOTime'])) {
        $time = $event['ISOTime'];
    } elseif (isset($event['Date'])) {
        $time = $event['Date'];
    } else {
        $time = date('Y-m-d H:i:s');
    }
    
    // Extrair código do evento (importante para filtrar _DoorFace_)
    $code = $event['Code'] ?? $event['EventType'] ?? $event['Event'] ?? null;
    
    // Construir evento normalizado no formato esperado por processarLeituraFacial
    $evento_normalizado = [
        'UserID' => $user_id,
        'Code' => $code,
        'ISOTime' => $time,
        'Similarity' => $event['Similarity'] ?? $event['Score'] ?? 100,
        'Name' => $event['Name'] ?? $event['UserName'] ?? null,
        'CardNo' => $event['CardNo'] ?? $event['CardID'] ?? $event['Card'] ?? null,
        'FaceID' => $event['FaceID'] ?? $event['Face'] ?? null,
        'EventType' => $code,
        'Time' => $time,
        'DateTime' => $time,
        'raw' => $event
    ];
    
    return $evento_normalizado;
}
?>
