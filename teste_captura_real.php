<?php
/**
 * Teste de captura real do dispositivo usando o endpoint correto
 */

require_once 'api/conexao.php';

echo "<h1>🔍 Teste de Captura Real do Dispositivo</h1>";

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
        } else {
            // Se não tem array de eventos, pode ser um evento único
            $eventos[] = normalizarEvento($json);
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
    
    // CORREÇÃO: Usar UserID (que é string) em vez de readID (que é interno do dispositivo)
    $user_id = $event['UserID'] ?? $event['PersonID'] ?? $event['EmployeeNo'] ?? $event['Employee'] ?? $event['ID'] ?? null;
    
    // Converter para inteiro se necessário
    if ($user_id && is_string($user_id)) {
        $user_id = (int) $user_id;
    }
    
    echo "<p><strong>UserID extraído:</strong> " . ($user_id ?? 'NULL') . " (tipo: " . gettype($user_id) . ")</p>";
    
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
        
        // Teste 1: getEvent com AccessControl (baseado no exemplo que funcionou)
        echo "<h3>1. Teste getEvent com AccessControl</h3>";
        $endpoint1 = "http://{$ip}/cgi-bin/eventManager.cgi?action=getEvent&codes=[AccessControl]&heartbeat=5";
        [$code1, $body1, $error1] = http_digest_get($endpoint1, $user, $pass);
        echo "<p><strong>HTTP Code:</strong> $code1</p>";
        if ($error1) {
            echo "<p><strong>Erro cURL:</strong> " . htmlspecialchars($error1) . "</p>";
        }
        
        if ($code1 >= 200 && $code1 < 300) {
            $eventos = processarEventosDispositivo($body1);
            echo "<h3>Eventos processados: " . count($eventos) . "</h3>";
            
            foreach ($eventos as $i => $evento) {
                echo "<h4>Evento " . ($i + 1) . ":</h4>";
                echo "<pre>" . htmlspecialchars(json_encode($evento, JSON_PRETTY_PRINT)) . "</pre>";
                
                // Verificar se o usuário existe
                if ($evento['user_id']) {
                    $stmt_user = $conn->prepare("SELECT id, nome, email, ativo FROM usuarios WHERE id = ?");
                    $stmt_user->bind_param("i", $evento['user_id']);
                    $stmt_user->execute();
                    $resultado_user = $stmt_user->get_result();
                    
                    if ($resultado_user->num_rows > 0) {
                        $usuario = $resultado_user->fetch_assoc();
                        echo "<p style='color: green;'>✅ <strong>Usuário encontrado:</strong> " . htmlspecialchars($usuario['nome']) . " (ID: " . htmlspecialchars($usuario['id']) . ")</p>";
                    } else {
                        echo "<p style='color: red;'>❌ <strong>Usuário não encontrado:</strong> ID " . htmlspecialchars($evento['user_id']) . "</p>";
                    }
                    $stmt_user->close();
                }
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
