<?php
/**
 * Script para testar captura de eventos do dispositivo
 */

echo "<h1>🔍 Teste de Captura de Eventos</h1>";

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

try {
    // Buscar dispositivos do tipo 'culto' ativos
    $stmt = $conn->prepare("SELECT * FROM dispositivos_faciais WHERE tipo_dispositivo = 'culto' AND ativo = 1");
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    while ($dispositivo = $resultado->fetch_assoc()) {
        echo "<h2>Dispositivo: " . htmlspecialchars($dispositivo['nome']) . "</h2>";
        echo "<p><strong>IP:</strong> " . htmlspecialchars($dispositivo['ip']) . "</p>";
        echo "<p><strong>Usuário:</strong> " . htmlspecialchars($dispositivo['usuario']) . "</p>";
        
        $ip = $dispositivo['ip'];
        $user = $dispositivo['usuario'];
        $pass = $dispositivo['senha'];
        
        // Teste 1: getCurrentTime
        echo "<h3>1. Teste de Conectividade (getCurrentTime)</h3>";
        $endpoint1 = "http://{$ip}/cgi-bin/global.cgi?action=getCurrentTime";
        [$code1, $body1, $error1] = http_digest_get($endpoint1, $user, $pass);
        echo "<p><strong>HTTP Code:</strong> $code1</p>";
        echo "<p><strong>Resposta:</strong></p>";
        echo "<pre>" . htmlspecialchars($body1) . "</pre>";
        if ($error1) {
            echo "<p><strong>Erro cURL:</strong> " . htmlspecialchars($error1) . "</p>";
        }
        
        // Teste 2: getEvent com _DoorFace_
        echo "<h3>2. Teste de Eventos (_DoorFace_)</h3>";
        $endpoint2 = "http://{$ip}/cgi-bin/eventManager.cgi?action=getEvent&codes=[_DoorFace_]&heartbeat=5";
        [$code2, $body2, $error2] = http_digest_get($endpoint2, $user, $pass);
        echo "<p><strong>HTTP Code:</strong> $code2</p>";
        echo "<p><strong>Resposta:</strong></p>";
        echo "<pre>" . htmlspecialchars($body2) . "</pre>";
        if ($error2) {
            echo "<p><strong>Erro cURL:</strong> " . htmlspecialchars($error2) . "</p>";
        }
        
        // Teste 3: recordFinder
        echo "<h3>3. Teste de Busca Histórica (recordFinder)</h3>";
        $endpoint3 = "http://{$ip}/cgi-bin/recordFinder.cgi?action=find&name=AccessControlEvent&StartTime=" . urlencode(date('Y-m-d H:i:s', time() - 300)) . "&EndTime=" . urlencode(date('Y-m-d H:i:s'));
        [$code3, $body3, $error3] = http_digest_get($endpoint3, $user, $pass);
        echo "<p><strong>HTTP Code:</strong> $code3</p>";
        echo "<p><strong>Resposta:</strong></p>";
        echo "<pre>" . htmlspecialchars($body3) . "</pre>";
        if ($error3) {
            echo "<p><strong>Erro cURL:</strong> " . htmlspecialchars($error3) . "</p>";
        }
        
        // Teste 4: Diferentes códigos de evento
        echo "<h3>4. Teste com Diferentes Códigos</h3>";
        $codigos = ['[AccessControl]', '[_DoorFace_]', '[FaceRecognition]', '[CardRecognition]', '[All]'];
        
        foreach ($codigos as $codigo) {
            echo "<h4>Código: $codigo</h4>";
            $endpoint4 = "http://{$ip}/cgi-bin/eventManager.cgi?action=getEvent&codes=$codigo&heartbeat=5";
            [$code4, $body4, $error4] = http_digest_get($endpoint4, $user, $pass);
            echo "<p><strong>HTTP Code:</strong> $code4</p>";
            if ($code4 >= 200 && $code4 < 300) {
                echo "<p><strong>Resposta:</strong></p>";
                echo "<pre>" . htmlspecialchars($body4) . "</pre>";
            } else {
                echo "<p><strong>Erro:</strong> " . htmlspecialchars($error4) . "</p>";
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
