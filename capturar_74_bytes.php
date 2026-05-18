<?php
/**
 * Capturar os 74 bytes que estão sendo recebidos do dispositivo
 */

require_once 'api/conexao.php';

echo "<h1>🔍 Capturar 74 Bytes do Dispositivo</h1>";

function http_digest_get($url, $user, $pass) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => $user . ":" . $pass,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 10, // Aumentar para capturar mais dados
        CURLOPT_CONNECTTIMEOUT => 3,
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
        
        // Teste 1: attach com AccessControl
        echo "<h3>1. Teste attach com AccessControl</h3>";
        $endpoint1 = "http://{$ip}/cgi-bin/eventManager.cgi?action=attach&codes=[AccessControl]&heartbeat=5";
        [$code1, $body1, $error1, $info1] = http_digest_get($endpoint1, $user, $pass);
        
        echo "<p><strong>HTTP Code:</strong> $code1</p>";
        echo "<p><strong>Bytes recebidos:</strong> " . $info1['size_download'] . "</p>";
        echo "<p><strong>Tempo total:</strong> " . $info1['total_time'] . "s</p>";
        
        if ($error1) {
            echo "<p><strong>Erro cURL:</strong> " . htmlspecialchars($error1) . "</p>";
        }
        
        if ($code1 >= 200 && $code1 < 300) {
            echo "<h3>Resposta bruta (hex):</h3>";
            echo "<pre>" . bin2hex($body1) . "</pre>";
            
            echo "<h3>Resposta bruta (texto):</h3>";
            echo "<pre>" . htmlspecialchars($body1) . "</pre>";
            
            echo "<h3>Análise da resposta:</h3>";
            echo "<p><strong>Tamanho:</strong> " . strlen($body1) . " bytes</p>";
            echo "<p><strong>Primeiros 10 caracteres:</strong> " . htmlspecialchars(substr($body1, 0, 10)) . "</p>";
            echo "<p><strong>Últimos 10 caracteres:</strong> " . htmlspecialchars(substr($body1, -10)) . "</p>";
            
            // Verificar se é SSE
            if (strpos($body1, 'data:') !== false) {
                echo "<p style='color: green;'>✅ <strong>Formato SSE detectado</strong></p>";
                $linhas = explode("\n", $body1);
                foreach ($linhas as $i => $linha) {
                    if (strpos($linha, 'data:') === 0) {
                        echo "<p><strong>Linha SSE $i:</strong> " . htmlspecialchars($linha) . "</p>";
                    }
                }
            } else {
                echo "<p style='color: orange;'>⚠️ <strong>Formato SSE não detectado</strong></p>";
            }
            
            // Tentar JSON
            $json = json_decode($body1, true);
            if ($json) {
                echo "<p style='color: green;'>✅ <strong>JSON válido detectado</strong></p>";
                echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT)) . "</pre>";
            } else {
                echo "<p style='color: red;'>❌ <strong>JSON inválido</strong> - " . json_last_error_msg() . "</p>";
            }
        }
        
        echo "<hr>";
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><strong>Captura concluída em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
