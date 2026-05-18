<?php
/**
 * Teste do método attach que realmente funciona
 */

require_once 'api/conexao.php';

echo "<h1>🔍 Teste do Método Attach</h1>";

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
        
        // Teste 1: attach com AccessControl (método que funciona)
        echo "<h3>1. Teste attach com AccessControl</h3>";
        $endpoint1 = "http://{$ip}/cgi-bin/eventManager.cgi?action=attach&codes=[AccessControl]&heartbeat=5";
        [$code1, $body1, $error1] = http_digest_get($endpoint1, $user, $pass);
        echo "<p><strong>HTTP Code:</strong> $code1</p>";
        if ($error1) {
            echo "<p><strong>Erro cURL:</strong> " . htmlspecialchars($error1) . "</p>";
        }
        
        if ($code1 >= 200 && $code1 < 300) {
            echo "<h3>Resposta do dispositivo:</h3>";
            echo "<pre>" . htmlspecialchars($body1) . "</pre>";
            
            // Tentar processar como JSON
            $json = json_decode($body1, true);
            if (is_array($json)) {
                echo "<h3>JSON decodificado:</h3>";
                echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT)) . "</pre>";
            } else {
                echo "<h3>Resposta não é JSON válido</h3>";
                echo "<p>Erro JSON: " . json_last_error_msg() . "</p>";
            }
        }
        
        // Teste 2: attach com _DoorFace_ (código específico)
        echo "<h3>2. Teste attach com _DoorFace_</h3>";
        $endpoint2 = "http://{$ip}/cgi-bin/eventManager.cgi?action=attach&codes=[_DoorFace_]&heartbeat=5";
        [$code2, $body2, $error2] = http_digest_get($endpoint2, $user, $pass);
        echo "<p><strong>HTTP Code:</strong> $code2</p>";
        if ($error2) {
            echo "<p><strong>Erro cURL:</strong> " . htmlspecialchars($error2) . "</p>";
        }
        
        if ($code2 >= 200 && $code2 < 300) {
            echo "<h3>Resposta do dispositivo:</h3>";
            echo "<pre>" . htmlspecialchars($body2) . "</pre>";
            
            // Tentar processar como JSON
            $json2 = json_decode($body2, true);
            if (is_array($json2)) {
                echo "<h3>JSON decodificado:</h3>";
                echo "<pre>" . htmlspecialchars(json_encode($json2, JSON_PRETTY_PRINT)) . "</pre>";
            } else {
                echo "<h3>Resposta não é JSON válido</h3>";
                echo "<p>Erro JSON: " . json_last_error_msg() . "</p>";
            }
        }
        
        // Teste 3: attach com All (todos os eventos)
        echo "<h3>3. Teste attach com All</h3>";
        $endpoint3 = "http://{$ip}/cgi-bin/eventManager.cgi?action=attach&codes=[All]&heartbeat=5";
        [$code3, $body3, $error3] = http_digest_get($endpoint3, $user, $pass);
        echo "<p><strong>HTTP Code:</strong> $code3</p>";
        if ($error3) {
            echo "<p><strong>Erro cURL:</strong> " . htmlspecialchars($error3) . "</p>";
        }
        
        if ($code3 >= 200 && $code3 < 300) {
            echo "<h3>Resposta do dispositivo:</h3>";
            echo "<pre>" . htmlspecialchars($body3) . "</pre>";
            
            // Tentar processar como JSON
            $json3 = json_decode($body3, true);
            if (is_array($json3)) {
                echo "<h3>JSON decodificado:</h3>";
                echo "<pre>" . htmlspecialchars(json_encode($json3, JSON_PRETTY_PRINT)) . "</pre>";
            } else {
                echo "<h3>Resposta não é JSON válido</h3>";
                echo "<p>Erro JSON: " . json_last_error_msg() . "</p>";
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
