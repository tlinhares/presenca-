<?php
/**
 * Teste de captura real completa do dispositivo
 */

require_once 'api/conexao.php';

echo "<h1>🔍 Teste de Captura Real Completa</h1>";

function http_digest_get($url, $user, $pass) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => $user . ":" . $pass,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 15, // Aumentar timeout
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_VERBOSE => true, // Para debug
    ]);
    
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    return [$code, $body, $error, $info];
}

function processarEventosDispositivo($body) {
    $eventos = [];
    
    if (empty($body)) {
        return $eventos;
    }
    
    echo "<h3>🔍 Processando resposta:</h3>";
    echo "<p><strong>Tamanho:</strong> " . strlen($body) . " bytes</p>";
    echo "<h4>Resposta bruta (primeiros 200 chars):</h4>";
    echo "<pre>" . htmlspecialchars(substr($body, 0, 200)) . "</pre>";
    
    // Tentar JSON direto com campo "data"
    $json = json_decode($body, true);
    if (is_array($json)) {
        echo "<p style='color: green;'>✅ <strong>JSON válido detectado</strong></p>";
        
        // Se tem campo "data" com JSON string, decodificar
        if (isset($json['data']) && is_string($json['data'])) {
            echo "<p><strong>Campo 'data' encontrado</strong></p>";
            
            $data_json = json_decode($json['data'], true);
            if (is_array($data_json)) {
                echo "<p style='color: green;'>✅ <strong>JSON do campo 'data' decodificado</strong></p>";
                $eventos[] = normalizarEvento($data_json);
            } else {
                echo "<p style='color: red;'>❌ <strong>Erro ao decodificar JSON do campo 'data'</strong></p>";
            }
        } else {
            echo "<p><strong>Usando JSON direto</strong></p>";
            $eventos[] = normalizarEvento($json);
        }
    } else {
        echo "<p style='color: red;'>❌ <strong>JSON inválido</strong> - " . json_last_error_msg() . "</p>";
    }
    
    return $eventos;
}

function normalizarEvento($event) {
    echo "<h4>🔧 Normalizando evento:</h4>";
    
    $user_id = $event['UserID'] ?? $event['PersonID'] ?? $event['EmployeeNo'] ?? $event['Employee'] ?? $event['ID'] ?? null;
    
    if ($user_id && is_string($user_id)) {
        $user_id = (int) $user_id;
    }
    
    echo "<p><strong>UserID extraído:</strong> " . ($user_id ?? 'NULL') . "</p>";
    
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
        
        // Teste 1: attach com AccessControl
        echo "<h3>1. Teste attach com AccessControl</h3>";
        $endpoint1 = "http://{$ip}/cgi-bin/eventManager.cgi?action=attach&codes=[AccessControl]&heartbeat=5";
        [$code1, $body1, $error1, $info1] = http_digest_get($endpoint1, $user, $pass);
        
        echo "<p><strong>HTTP Code:</strong> $code1</p>";
        echo "<p><strong>Bytes recebidos:</strong> " . $info1['size_download'] . "</p>";
        echo "<p><strong>Tempo total:</strong> " . $info1['total_time'] . "s</p>";
        
        if ($error1) {
            echo "<p style='color: red;'><strong>Erro cURL:</strong> " . htmlspecialchars($error1) . "</p>";
        }
        
        if ($code1 >= 200 && $code1 < 300) {
            $eventos = processarEventosDispositivo($body1);
            echo "<h3>✅ Eventos processados: " . count($eventos) . "</h3>";
            
            foreach ($eventos as $i => $evento) {
                echo "<h4>Evento " . ($i + 1) . ":</h4>";
                echo "<pre>" . htmlspecialchars(json_encode($evento, JSON_PRETTY_PRINT)) . "</pre>";
            }
        } else {
            echo "<p style='color: red;'>❌ <strong>Falha na conexão</strong></p>";
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
