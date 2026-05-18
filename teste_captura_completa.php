<?php
/**
 * Teste de captura completa do evento _DoorFace_
 */

require_once 'api/conexao.php';

echo "<h1>🔍 Teste Captura Completa _DoorFace_</h1>";

function capturarEventoCompleto($endpoint, $user, $pass, $nomeDispositivo) {
    $eventos = [];
    
    echo "<h3>Conectando ao dispositivo: $nomeDispositivo</h3>";
    echo "<p><strong>Endpoint:</strong> $endpoint</p>";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint,
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => $user . ":" . $pass,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 15, // Timeout de 15 segundos
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_WRITEFUNCTION => function($ch, $chunk) use (&$eventos, $nomeDispositivo) {
            static $buf = '';
            $buf .= $chunk;
            
            echo "<p><strong>Chunk recebido:</strong> " . strlen($chunk) . " bytes</p>";
            echo "<p><strong>Buffer total:</strong> " . strlen($buf) . " bytes</p>";
            
            // Mostrar primeiros 2000 chars do buffer
            echo "<p><strong>Buffer (primeiros 2000 chars):</strong></p>";
            echo "<pre>" . htmlspecialchars(substr($buf, 0, 2000)) . "</pre>";
            
            while (true) {
                $p = strpos($buf, "\r\n\r\n");
                if ($p === false) break;
                
                $headers = substr($buf, 0, $p);
                $rest = substr($buf, $p + 4);
                
                echo "<p><strong>Headers encontrados:</strong></p>";
                echo "<pre>" . htmlspecialchars($headers) . "</pre>";
                
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
                
                echo "<p><strong>Block processado:</strong></p>";
                echo "<pre>" . htmlspecialchars($block) . "</pre>";
                
                // Ignorar heartbeats
                if ($block === 'Heartbeat') {
                    echo "<p style='color: blue;'>💓 <strong>Heartbeat recebido</strong></p>";
                    continue;
                }
                
                // JSON direto?
                $j = json_decode($block, true);
                if (is_array($j)) {
                    $eventos[] = $j;
                    echo "<p style='color: green;'>✅ <strong>Evento JSON capturado:</strong> " . htmlspecialchars(json_encode($j)) . "</p>";
                    
                    // Verificar se é _DoorFace_
                    if (isset($j['Code']) && $j['Code'] === '_DoorFace_') {
                        echo "<p style='color: orange; font-weight: bold;'>🎯 <strong>EVENTO _DoorFace_ ENCONTRADO!</strong></p>";
                        echo "<p><strong>UserID:</strong> " . ($j['UserID'] ?? 'N/A') . "</p>";
                        echo "<p><strong>ID:</strong> " . ($j['ID'] ?? 'N/A') . "</p>";
                        echo "<p><strong>EmployeeNo:</strong> " . ($j['EmployeeNo'] ?? 'N/A') . "</p>";
                        echo "<p><strong>PersonID:</strong> " . ($j['PersonID'] ?? 'N/A') . "</p>";
                    }
                    continue;
                }
                
                // Tentar como key=value
                $lines = explode("\n", $block);
                $event = [];
                $in_data = false;
                $data_content = '';
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    if (strpos($line, '=') !== false && !$in_data) {
                        [$key, $value] = explode('=', $line, 2);
                        $key = trim($key);
                        $value = trim($value);
                        
                        if ($key === 'data' && strpos($value, '{') !== false) {
                            echo "<p style='color: blue;'>🔍 <strong>Campo data encontrado - começando captura JSON</strong></p>";
                            $in_data = true;
                            $data_content = $value;
                        } else {
                            $event[$key] = $value;
                        }
                    } elseif ($in_data) {
                        echo "<p style='color: blue;'>📝 <strong>Capturando JSON:</strong> " . htmlspecialchars($line) . "</p>";
                        $data_content .= "\n" . $line;
                        
                        if (strpos($line, '}') !== false) {
                            echo "<p style='color: green;'>🏁 <strong>Fim do JSON detectado</strong></p>";
                            $in_data = false;
                            
                            echo "<p><strong>JSON completo capturado:</strong></p>";
                            echo "<pre>" . htmlspecialchars($data_content) . "</pre>";
                            
                            $data_json = json_decode($data_content, true);
                            if (is_array($data_json)) {
                                echo "<p style='color: green;'>✅ <strong>JSON decodificado com sucesso!</strong></p>";
                                echo "<pre>" . htmlspecialchars(json_encode($data_json, JSON_PRETTY_PRINT)) . "</pre>";
                                
                                $event = array_merge($event, $data_json);
                                
                                if (isset($data_json['UserID'])) {
                                    echo "<p style='color: red; font-weight: bold;'>🎯 <strong>UserID ENCONTRADO: " . $data_json['UserID'] . "</strong></p>";
                                }
                                if (isset($data_json['ID'])) {
                                    echo "<p style='color: red; font-weight: bold;'>🎯 <strong>ID ENCONTRADO: " . $data_json['ID'] . "</strong></p>";
                                }
                                if (isset($data_json['EmployeeNo'])) {
                                    echo "<p style='color: red; font-weight: bold;'>🎯 <strong>EmployeeNo ENCONTRADO: " . $data_json['EmployeeNo'] . "</strong></p>";
                                }
                                if (isset($data_json['PersonID'])) {
                                    echo "<p style='color: red; font-weight: bold;'>🎯 <strong>PersonID ENCONTRADO: " . $data_json['PersonID'] . "</strong></p>";
                                }
                            } else {
                                echo "<p style='color: red;'>❌ <strong>Erro ao decodificar JSON:</strong> " . json_last_error_msg() . "</p>";
                                echo "<p><strong>JSON raw:</strong> " . htmlspecialchars($data_content) . "</p>";
                                
                                // Tentar extrair IDs com regex
                                echo "<p style='color: orange;'>🔍 <strong>Tentando extrair IDs com regex:</strong></p>";
                                
                                if (preg_match('/"UserID"\s*:\s*"(\d+)"/', $data_content, $matches)) {
                                    echo "<p style='color: green;'>✅ <strong>UserID extraído: " . $matches[1] . "</strong></p>";
                                }
                                if (preg_match('/"ID"\s*:\s*"(\d+)"/', $data_content, $matches)) {
                                    echo "<p style='color: green;'>✅ <strong>ID extraído: " . $matches[1] . "</strong></p>";
                                }
                                if (preg_match('/"EmployeeNo"\s*:\s*"(\d+)"/', $data_content, $matches)) {
                                    echo "<p style='color: green;'>✅ <strong>EmployeeNo extraído: " . $matches[1] . "</strong></p>";
                                }
                                if (preg_match('/"PersonID"\s*:\s*"(\d+)"/', $data_content, $matches)) {
                                    echo "<p style='color: green;'>✅ <strong>PersonID extraído: " . $matches[1] . "</strong></p>";
                                }
                            }
                        }
                    }
                }
                
                if (!empty($event)) {
                    $eventos[] = $event;
                    echo "<p style='color: green;'>✅ <strong>Evento key=value capturado:</strong> " . htmlspecialchars(json_encode($event)) . "</p>";
                }
            }
            
            return strlen($chunk);
        }
    ]);
    
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "<p><strong>HTTP Code:</strong> $code</p>";
    if ($error) {
        echo "<p style='color: red;'><strong>Erro cURL:</strong> " . htmlspecialchars($error) . "</p>";
    }
    
    return $eventos;
}

try {
    // Buscar dispositivos do tipo 'culto' ativos
    $stmt = $conn->prepare("SELECT * FROM dispositivos_faciais WHERE tipo_dispositivo = 'culto' AND ativo = 1");
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    while ($dispositivo = $resultado->fetch_assoc()) {
        echo "<h2>Dispositivo: " . htmlspecialchars($dispositivo['nome']) . "</h2>";
        echo "<p><strong>IP:</strong> " . htmlspecialchars($dispositivo['ip']) . "</p>";
        
        $ip = $dispositivo['ip'];
        $user = $dispositivo['usuario'];
        $pass = $dispositivo['senha'];
        
        $endpoint = "http://{$ip}/cgi-bin/eventManager.cgi?action=attach&codes=[_DoorFace_]&heartbeat=5";
        $eventos = capturarEventoCompleto($endpoint, $user, $pass, $dispositivo['nome']);
        
        echo "<h3>✅ Eventos capturados: " . count($eventos) . "</h3>";
        
        foreach ($eventos as $i => $evento) {
            echo "<h4>Evento " . ($i + 1) . ":</h4>";
            echo "<pre>" . htmlspecialchars(json_encode($evento, JSON_PRETTY_PRINT)) . "</pre>";
        }
        
        echo "<hr>";
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><strong>Teste concluído em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
