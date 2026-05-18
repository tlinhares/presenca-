<?php
/**
 * Teste do monitor corrigido
 */

require_once 'api/conexao.php';

echo "<h1>🔍 Teste do Monitor Corrigido</h1>";

// Simular a função capturarStreamDispositivo
function capturarStreamDispositivo($endpoint, $user, $pass, $nomeDispositivo) {
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
        CURLOPT_TIMEOUT => 5, // Timeout reduzido para teste
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_WRITEFUNCTION => function($ch, $chunk) use (&$eventos, $nomeDispositivo) {
            static $buf = '';
            $buf .= $chunk;
            
            echo "<p><strong>Chunk recebido:</strong> " . strlen($chunk) . " bytes</p>";
            echo "<p><strong>Buffer total:</strong> " . strlen($buf) . " bytes</p>";
            
            // Mostrar primeiros 200 chars do buffer
            echo "<p><strong>Buffer (primeiros 200 chars):</strong></p>";
            echo "<pre>" . htmlspecialchars(substr($buf, 0, 200)) . "</pre>";
            
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
                
                // JSON direto?
                $j = json_decode($block, true);
                if (is_array($j)) {
                    $eventos[] = $j;
                    echo "<p style='color: green;'>✅ <strong>Evento JSON capturado:</strong> " . htmlspecialchars(json_encode($j)) . "</p>";
                    continue;
                }
                
                // Tentar como key=value
                $lines = explode("\n", $block);
                $event = [];
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false) {
                        [$key, $value] = explode('=', $line, 2);
                        $event[trim($key)] = trim($value);
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
        
        $endpoint = "http://{$ip}/cgi-bin/eventManager.cgi?action=attach&codes=[AccessControl]&heartbeat=5";
        $eventos = capturarStreamDispositivo($endpoint, $user, $pass, $dispositivo['nome']);
        
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
