<?php
/**
 * Teste usando o mesmo método do stream_events.php
 */

require_once 'api/conexao.php';

echo "<h1>🔍 Teste do Método Stream</h1>";

function flush_sse(){ echo str_repeat(' ',2048)."\n"; @ob_flush(); @flush(); }

function enrichTime($j, $tzOffsetSec) {
    if (isset($j['ISOTime'])) return $j;
    if (isset($j['Time'])) {
        $j['ISOTime'] = date('Y-m-d H:i:s', strtotime($j['Time']) + $tzOffsetSec);
    } elseif (isset($j['DateTime'])) {
        $j['ISOTime'] = date('Y-m-d H:i:s', strtotime($j['DateTime']) + $tzOffsetSec);
    } else {
        $j['ISOTime'] = date('Y-m-d H:i:s', time() + $tzOffsetSec);
    }
    return $j;
}

function capturarStream($ip, $user, $pass) {
    $endpoint = "http://{$ip}/cgi-bin/eventManager.cgi?action=attach&codes=[AccessControl]&heartbeat=5";
    
    // Calcular offset de fuso
    $tzOffsetSec = 0;
    $devTimeUrl = "http://{$ip}/cgi-bin/global.cgi?action=getCurrentTime";
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
    
    $eventos = [];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint,
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => $user . ":" . $pass,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_WRITEFUNCTION => function($ch, $chunk) use (&$eventos, $tzOffsetSec) {
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
                
                // JSON direto?
                $j = json_decode($block, true);
                if (is_array($j)) {
                    $j = enrichTime($j, $tzOffsetSec);
                    $eventos[] = $j;
                    echo "<p style='color: green;'>✅ <strong>Evento capturado:</strong> " . htmlspecialchars(json_encode($j)) . "</p>";
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
                    $event = enrichTime($event, $tzOffsetSec);
                    $eventos[] = $event;
                    echo "<p style='color: green;'>✅ <strong>Evento capturado (key=value):</strong> " . htmlspecialchars(json_encode($event)) . "</p>";
                }
            }
            
            return strlen($chunk);
        }
    ]);
    
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [$code, $eventos, $error];
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
        
        echo "<h3>Capturando eventos (timeout de 10 segundos):</h3>";
        
        // Usar timeout para limitar o tempo de captura
        $start_time = time();
        $timeout = 10;
        
        [$code, $eventos, $error] = capturarStream($ip, $user, $pass);
        
        echo "<p><strong>HTTP Code:</strong> $code</p>";
        if ($error) {
            echo "<p style='color: red;'><strong>Erro cURL:</strong> " . htmlspecialchars($error) . "</p>";
        }
        
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
