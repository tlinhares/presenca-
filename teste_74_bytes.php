<?php
/**
 * Teste específico dos 74 bytes que o dispositivo está retornando
 */

require_once 'api/conexao.php';

echo "<h1>🔍 Teste dos 74 Bytes</h1>";

function capturar74Bytes($ip, $user, $pass) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$ip}/cgi-bin/eventManager.cgi?action=attach&codes=[AccessControl]&heartbeat=5",
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => $user . ":" . $pass,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 5, // Timeout maior para capturar os dados
        CURLOPT_CONNECTTIMEOUT => 1,
    ]);
    
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    return [$code, $body, $error, $info];
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
        
        [$code, $body, $error, $info] = capturar74Bytes($ip, $user, $pass);
        
        echo "<p><strong>HTTP Code:</strong> $code</p>";
        echo "<p><strong>Bytes recebidos:</strong> " . $info['size_download'] . "</p>";
        echo "<p><strong>Tempo total:</strong> " . $info['total_time'] . "s</p>";
        
        if ($error) {
            echo "<p style='color: red;'><strong>Erro cURL:</strong> " . htmlspecialchars($error) . "</p>";
        }
        
        if ($code >= 200 && $code < 300) {
            echo "<h3>📋 Análise dos 74 bytes:</h3>";
            
            echo "<h4>1. Dados em Hexadecimal:</h4>";
            echo "<pre>" . bin2hex($body) . "</pre>";
            
            echo "<h4>2. Dados em Texto:</h4>";
            echo "<pre>" . htmlspecialchars($body) . "</pre>";
            
            echo "<h4>3. Análise de Conteúdo:</h4>";
            
            // Verificar se contém headers HTTP
            if (strpos($body, 'Content-Type') !== false) {
                echo "<p style='color: blue;'>📄 <strong>Headers HTTP detectados</strong></p>";
            }
            
            // Verificar se contém boundary
            if (strpos($body, 'boundary') !== false) {
                echo "<p style='color: blue;'>🔗 <strong>Boundary detectado</strong></p>";
            }
            
            // Verificar se contém JSON
            if (strpos($body, '{') !== false || strpos($body, '}') !== false) {
                echo "<p style='color: green;'>📄 <strong>JSON detectado</strong></p>";
            }
            
            // Verificar se contém UserID
            if (strpos($body, 'UserID') !== false) {
                echo "<p style='color: green;'>👤 <strong>UserID detectado</strong></p>";
            }
            
            // Verificar se contém dados de evento
            if (strpos($body, 'Event') !== false) {
                echo "<p style='color: green;'>🎯 <strong>Evento detectado</strong></p>";
            }
            
            // Tentar extrair JSON se existir
            if (preg_match('/\{.*\}/', $body, $matches)) {
                echo "<h4>4. JSON Extraído:</h4>";
                $json_str = $matches[0];
                echo "<pre>" . htmlspecialchars($json_str) . "</pre>";
                
                $json = json_decode($json_str, true);
                if ($json) {
                    echo "<p style='color: green;'>✅ <strong>JSON válido</strong></p>";
                    echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT)) . "</pre>";
                } else {
                    echo "<p style='color: red;'>❌ <strong>JSON inválido</strong> - " . json_last_error_msg() . "</p>";
                }
            }
            
            // Verificar se é apenas headers
            if (strpos($body, 'HTTP/') !== false || strpos($body, 'Content-Type:') !== false) {
                echo "<p style='color: orange;'>⚠️ <strong>Parece ser apenas headers HTTP</strong></p>";
            }
            
        } else {
            echo "<p style='color: red;'>❌ <strong>Falha na captura</strong></p>";
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
