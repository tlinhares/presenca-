<?php
/**
 * Teste de captura parcial dos dados do dispositivo
 */

require_once 'api/conexao.php';

echo "<h1>🔍 Teste de Captura Parcial</h1>";

function capturarDadosDispositivo($ip, $user, $pass) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$ip}/cgi-bin/eventManager.cgi?action=attach&codes=[AccessControl]&heartbeat=5",
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => $user . ":" . $pass,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 3, // Timeout muito baixo para capturar dados parciais
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_VERBOSE => true,
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
        
        // Múltiplas tentativas com diferentes timeouts
        $timeouts = [1, 2, 3, 5, 8];
        
        foreach ($timeouts as $timeout) {
            echo "<h3>Teste com timeout de {$timeout}s:</h3>";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "http://{$ip}/cgi-bin/eventManager.cgi?action=attach&codes=[AccessControl]&heartbeat=5",
                CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
                CURLOPT_USERPWD => $user . ":" . $pass,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            
            echo "<p><strong>HTTP Code:</strong> $code</p>";
            echo "<p><strong>Bytes recebidos:</strong> " . $info['size_download'] . "</p>";
            echo "<p><strong>Tempo total:</strong> " . $info['total_time'] . "s</p>";
            
            if ($error) {
                echo "<p style='color: red;'><strong>Erro cURL:</strong> " . htmlspecialchars($error) . "</p>";
            }
            
            if ($code >= 200 && $code < 300 && !empty($body)) {
                echo "<h4>Dados capturados (hex):</h4>";
                echo "<pre>" . bin2hex($body) . "</pre>";
                
                echo "<h4>Dados capturados (texto):</h4>";
                echo "<pre>" . htmlspecialchars($body) . "</pre>";
                
                // Verificar se contém boundary
                if (strpos($body, '--myboundary') !== false) {
                    echo "<p style='color: green;'>✅ <strong>Boundary detectado!</strong></p>";
                }
                
                // Verificar se contém JSON
                if (strpos($body, '{') !== false) {
                    echo "<p style='color: green;'>✅ <strong>JSON detectado!</strong></p>";
                }
                
                // Verificar se contém UserID
                if (strpos($body, 'UserID') !== false) {
                    echo "<p style='color: green;'>✅ <strong>UserID detectado!</strong></p>";
                }
                
                break; // Se capturou dados, para de testar outros timeouts
            } else {
                echo "<p style='color: orange;'>⚠️ <strong>Nenhum dado capturado</strong></p>";
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
