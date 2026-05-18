<?php
/**
 * Teste específico do formato multipart/x-mixed-replace
 */

require_once 'api/conexao.php';

echo "<h1>🔍 Teste do Formato Multipart</h1>";

function http_digest_get($url, $user, $pass) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => $user . ":" . $pass,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 8, // Reduzir timeout para capturar dados parciais
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    return [$code, $body, $error, $info];
}

function processarMultipart($body) {
    echo "<h3>🔍 Processando formato multipart:</h3>";
    echo "<p><strong>Tamanho:</strong> " . strlen($body) . " bytes</p>";
    
    // Mostrar resposta bruta
    echo "<h4>Resposta bruta (primeiros 500 chars):</h4>";
    echo "<pre>" . htmlspecialchars(substr($body, 0, 500)) . "</pre>";
    
    $eventos = [];
    
    if (strpos($body, '--myboundary') !== false) {
        echo "<p style='color: green;'>✅ <strong>Formato multipart detectado</strong></p>";
        
        $partes = explode('--myboundary', $body);
        echo "<p><strong>Partes encontradas:</strong> " . count($partes) . "</p>";
        
        foreach ($partes as $i => $parte) {
            $parte = trim($parte);
            if (empty($parte) || $parte === '--') continue;
            
            echo "<h5>Parte $i:</h5>";
            echo "<pre>" . htmlspecialchars(substr($parte, 0, 200)) . "...</pre>";
            
            // Extrair conteúdo após headers
            $linhas = explode("\n", $parte);
            $conteudo = '';
            $em_conteudo = false;
            
            foreach ($linhas as $linha) {
                if ($em_conteudo) {
                    $conteudo .= $linha . "\n";
                } elseif (trim($linha) === '') {
                    $em_conteudo = true;
                }
            }
            
            $conteudo = trim($conteudo);
            if (!empty($conteudo)) {
                echo "<p><strong>Conteúdo extraído:</strong></p>";
                echo "<pre>" . htmlspecialchars($conteudo) . "</pre>";
                
                // Tentar processar como JSON
                $json = json_decode($conteudo, true);
                if (is_array($json)) {
                    echo "<p style='color: green;'>✅ <strong>JSON válido na parte $i</strong></p>";
                    echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT)) . "</pre>";
                    $eventos[] = $json;
                } else {
                    echo "<p style='color: red;'>❌ <strong>JSON inválido na parte $i</strong> - " . json_last_error_msg() . "</p>";
                }
            }
        }
    } else {
        echo "<p style='color: orange;'>⚠️ <strong>Formato multipart não detectado</strong></p>";
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
        
        // Teste attach com AccessControl
        echo "<h3>Teste attach com AccessControl</h3>";
        $endpoint = "http://{$ip}/cgi-bin/eventManager.cgi?action=attach&codes=[AccessControl]&heartbeat=5";
        [$code, $body, $error, $info] = http_digest_get($endpoint, $user, $pass);
        
        echo "<p><strong>HTTP Code:</strong> $code</p>";
        echo "<p><strong>Bytes recebidos:</strong> " . $info['size_download'] . "</p>";
        echo "<p><strong>Tempo total:</strong> " . $info['total_time'] . "s</p>";
        
        if ($error) {
            echo "<p style='color: red;'><strong>Erro cURL:</strong> " . htmlspecialchars($error) . "</p>";
        }
        
        if ($code >= 200 && $code < 300) {
            $eventos = processarMultipart($body);
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
