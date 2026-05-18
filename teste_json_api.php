<?php
/**
 * Teste específico para verificar se a API retorna JSON válido
 */

echo "<h1>🧪 Teste de JSON da API Facial</h1>";

// URL da API
$url = "https://presenca.aom.org.br/api/culto/receber_leitura_facial.php";

echo "<h2>1. Teste GET</h2>";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Content-Type: application/json',
        'timeout' => 10
    ]
]);

$response = @file_get_contents($url, false, $context);

if ($response === false) {
    echo "❌ Falha na requisição<br>";
    $error = error_get_last();
    if ($error) {
        echo "Erro: " . $error['message'] . "<br>";
    }
} else {
    echo "✅ Resposta recebida:<br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    // Verificar se é JSON válido
    $json = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ JSON válido<br>";
        echo "<h3>Dados decodificados:</h3>";
        echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT)) . "</pre>";
    } else {
        echo "❌ JSON inválido<br>";
        echo "Erro: " . json_last_error_msg() . "<br>";
        echo "Primeiros 100 caracteres:<br>";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 100)) . "...</pre>";
    }
}

echo "<h2>2. Teste POST</h2>";

$dados = [
    'nome_usuario' => 'Teste JSON',
    'ip_dispositivo' => '192.168.1.100',
    'timestamp' => time()
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($dados),
        'timeout' => 10
    ]
]);

$response = @file_get_contents($url, false, $context);

if ($response === false) {
    echo "❌ Falha na requisição<br>";
    $error = error_get_last();
    if ($error) {
        echo "Erro: " . $error['message'] . "<br>";
    }
} else {
    echo "✅ Resposta recebida:<br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    // Verificar se é JSON válido
    $json = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ JSON válido<br>";
        echo "<h3>Dados decodificados:</h3>";
        echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT)) . "</pre>";
    } else {
        echo "❌ JSON inválido<br>";
        echo "Erro: " . json_last_error_msg() . "<br>";
        echo "Primeiros 100 caracteres:<br>";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 100)) . "...</pre>";
    }
}

echo "<h2>3. Teste com cURL</h2>";

// Teste com cURL para ter mais controle
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ Erro cURL: $error<br>";
} else {
    echo "✅ Resposta cURL (HTTP $httpCode):<br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    // Verificar se é JSON válido
    $json = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ JSON válido<br>";
    } else {
        echo "❌ JSON inválido<br>";
        echo "Erro: " . json_last_error_msg() . "<br>";
    }
}

echo "<hr>";
echo "<p><strong>Teste concluído em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>

