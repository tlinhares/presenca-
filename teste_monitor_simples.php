<?php
/**
 * Teste simples do monitor para debug
 */

echo "<h1>🔍 Teste Simples do Monitor</h1>";

// Testar o endpoint diretamente
echo "<h2>1. Teste do Endpoint</h2>";
$url = "https://presenca.aom.org.br/api/monitor/fetch_dispositivo_tempo_real.php";
echo "<p><strong>URL:</strong> $url</p>";

$context = stream_context_create([
    'http' => [
        'timeout' => 30,
        'method' => 'GET'
    ]
]);

$response = file_get_contents($url, false, $context);

if ($response === false) {
    echo "<p style='color: red;'>❌ <strong>Erro ao acessar o endpoint</strong></p>";
    $error = error_get_last();
    if ($error) {
        echo "<p>Erro: " . htmlspecialchars($error['message']) . "</p>";
    }
} else {
    echo "<p style='color: green;'>✅ <strong>Endpoint acessado com sucesso</strong></p>";
    echo "<h3>Resposta:</h3>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    // Tentar decodificar JSON
    $json = json_decode($response, true);
    if ($json) {
        echo "<h3>JSON Decodificado:</h3>";
        echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT)) . "</pre>";
    } else {
        echo "<p style='color: red;'>❌ <strong>Resposta não é JSON válido</strong></p>";
        echo "<p>Erro JSON: " . json_last_error_msg() . "</p>";
    }
}

echo "<hr>";

// Testar com cURL
echo "<h2>2. Teste com cURL</h2>";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);

$curl_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo "<p style='color: red;'>❌ <strong>Erro cURL:</strong> " . htmlspecialchars($curl_error) . "</p>";
} else {
    echo "<p style='color: green;'>✅ <strong>cURL executado com sucesso</strong></p>";
    echo "<p><strong>HTTP Code:</strong> $http_code</p>";
    echo "<h3>Resposta cURL:</h3>";
    echo "<pre>" . htmlspecialchars($curl_response) . "</pre>";
}

echo "<hr>";
echo "<p><strong>Teste concluído em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>