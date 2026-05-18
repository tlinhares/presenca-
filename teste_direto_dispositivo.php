<?php
/**
 * Teste direto do dispositivo para debug
 */

echo "<h1>🔍 Teste Direto do Dispositivo</h1>";

require_once 'api/conexao.php';

function http_digest_get($url, $user, $pass) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
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

function processarEventosDispositivo($body) {
    $eventos = [];
    
    if (empty($body)) {
        return $eventos;
    }
    
    echo "<h3>Resposta bruta do dispositivo:</h3>";
    echo "<pre>" . htmlspecialchars($body) . "</pre>";
    
    // Tentar JSON direto
    $json = json_decode($body, true);
    if (is_array($json)) {
        echo "<h3>JSON decodificado:</h3>";
        echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT)) . "</pre>";
        
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
        echo "<h3>Resposta não é JSON válido</h3>";
        echo "<p>Erro JSON: " . json_last_error_msg() . "</p>";
    }
    
    return $eventos;
}

function normalizarEvento($event) {
    echo "<h4>Evento bruto:</h4>";
    echo "<pre>" . htmlspecialchars(json_encode($event, JSON_PRETTY_PRINT)) . "</pre>";
    
    // Tentar diferentes campos para identificar o usuário
    $user_id = $event['UserID'] ?? $event['PersonID'] ?? $event['EmployeeNo'] ?? $event['Employee'] ?? $event['ID'] ?? null;
    
    echo "<p><strong>User ID encontrado:</strong> " . ($user_id ?? 'NULL') . "</p>";
    
    return [
        'user_id' => $user_id,
        'card' => $event['CardNo'] ?? $event['CardID'] ?? $event['Card'] ?? null,
        'face' => $event['FaceID'] ?? $event['Face'] ?? null,
        'event_type' => $event['EventType'] ?? $event['Code'] ?? $event['Event'] ?? 'FaceRecognition',
        'result' => $event['Pass'] ?? $event['Result'] ?? $event['Status'] ?? 'Pass',
        'time' => $event['Time'] ?? $event['DateTime'] ?? $event['ISOTime'] ?? $event['Date'] ?? date('Y-m-d H:i:s'),
        'raw' => $event
    ];
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
        
        // Teste 1: getCurrentTime (conectividade básica)
        echo "<h3>1. Teste de Conectividade (getCurrentTime)</h3>";
        $endpoint1 = "http://{$ip}/cgi-bin/global.cgi?action=getCurrentTime";
        [$code1, $body1, $error1] = http_digest_get($endpoint1, $user, $pass);
        echo "<p><strong>HTTP Code:</strong> $code1</p>";
        echo "<p><strong>Resposta:</strong> " . htmlspecialchars($body1) . "</p>";
        if ($error1) {
            echo "<p><strong>Erro cURL:</strong> " . htmlspecialchars($error1) . "</p>";
        }
        
        // Teste 2: recordFinder com diferentes nomes
        echo "<h3>2. Teste recordFinder com diferentes nomes</h3>";
        $nomes = ['AccessControlEvent', 'AccessControlFaceRec', 'AccessControlCardRec', 'AccessControlLog', 'Event', 'Record', 'FaceRecord', 'CardRecord'];
        
        foreach ($nomes as $nome) {
            echo "<h4>Testando nome: $nome</h4>";
            $endpoint2 = "http://{$ip}/cgi-bin/recordFinder.cgi?action=find&name=$nome&StartTime=" . urlencode(date('Y-m-d H:i:s', time() - 300)) . "&EndTime=" . urlencode(date('Y-m-d H:i:s'));
            [$code2, $body2, $error2] = http_digest_get($endpoint2, $user, $pass);
            echo "<p><strong>HTTP Code:</strong> $code2</p>";
            if ($code2 >= 200 && $code2 < 300) {
                echo "<p><strong>Resposta:</strong> " . htmlspecialchars(substr($body2, 0, 200)) . "...</p>";
                $eventos2 = processarEventosDispositivo($body2);
                echo "<p><strong>Eventos encontrados:</strong> " . count($eventos2) . "</p>";
                
                if (count($eventos2) > 0) {
                    echo "<h5>Eventos processados:</h5>";
                    foreach ($eventos2 as $i => $evento) {
                        echo "<h6>Evento " . ($i + 1) . ":</h6>";
                        echo "<pre>" . htmlspecialchars(json_encode($evento, JSON_PRETTY_PRINT)) . "</pre>";
                    }
                    break; // Se encontrou eventos, para de testar outros nomes
                }
            } else {
                echo "<p><strong>Erro:</strong> " . htmlspecialchars($error2) . "</p>";
            }
        }
        
        // Teste 3: eventManager com diferentes códigos
        echo "<h3>3. Teste eventManager com diferentes códigos</h3>";
        $codigos = ['[AccessControl]', '[_DoorFace_]', '[FaceRecognition]', '[CardRecognition]', '[All]', '[]'];
        
        foreach ($codigos as $codigo) {
            echo "<h4>Testando código: $codigo</h4>";
            $endpoint3 = "http://{$ip}/cgi-bin/eventManager.cgi?action=getEvent&codes=$codigo&heartbeat=5";
            [$code3, $body3, $error3] = http_digest_get($endpoint3, $user, $pass);
            echo "<p><strong>HTTP Code:</strong> $code3</p>";
            if ($code3 >= 200 && $code3 < 300) {
                echo "<p><strong>Resposta:</strong> " . htmlspecialchars(substr($body3, 0, 200)) . "...</p>";
                $eventos3 = processarEventosDispositivo($body3);
                echo "<p><strong>Eventos encontrados:</strong> " . count($eventos3) . "</p>";
                
                if (count($eventos3) > 0) {
                    echo "<h5>Eventos processados:</h5>";
                    foreach ($eventos3 as $i => $evento) {
                        echo "<h6>Evento " . ($i + 1) . ":</h6>";
                        echo "<pre>" . htmlspecialchars(json_encode($evento, JSON_PRETTY_PRINT)) . "</pre>";
                    }
                    break; // Se encontrou eventos, para de testar outros códigos
                }
            } else {
                echo "<p><strong>Erro:</strong> " . htmlspecialchars($error3) . "</p>";
            }
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
