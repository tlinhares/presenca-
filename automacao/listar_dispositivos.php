<?php

// Credenciais atualizadas conforme a imagem
$client_id = '4y3t8eac4fqwfk9gmxjh'; // Access ID/Client ID
$client_secret = 'e4efb18fef9b43c29c291f0c20f19d16'; // Access Secret/Client Secret
$base_url = 'https://openapi.tuyaus.com'; // Western America Data Center

function getAccessToken($client_id, $client_secret, $base_url) {
    $timestamp = round(microtime(true) * 1000);
    $stringToSign = $client_id . $timestamp;
    $sign = strtoupper(hash_hmac('sha256', $stringToSign, $client_secret, false));

    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'client_id: ' . $client_id,
        'sign: ' . $sign,
        't: ' . $timestamp,
        'sign_method: HMAC-SHA256'
    ];

    $url = $base_url . '/v1.0/token?grant_type=1';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Adicionado para desenvolvimento
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Adicionado para desenvolvimento

    $response = curl_exec($ch);
    
    // Debug: Mostrar resposta completa
    if (curl_errno($ch)) {
        echo 'Erro cURL: ' . curl_error($ch);
    }
    
    curl_close($ch);

    $result = json_decode($response, true);
    
    if (isset($result['success']) && $result['success'] === true) {
        return $result['result']['access_token'];
    } else {
        echo "<pre>Resposta do servidor:\n";
        print_r($result);
        echo "</pre>";
        return null;
    }
}

function listarDispositivos($token, $client_id, $client_secret, $base_url) {
    $timestamp = round(microtime(true) * 1000);
    $stringToSign = $client_id . $token . $timestamp;
    $sign = strtoupper(hash_hmac('sha256', $stringToSign, $client_secret, false));

    $headers = [
        'Content-Type: application/json',
        'client_id: ' . $client_id,
        'access_token: ' . $token,
        'sign: ' . $sign,
        't: ' . $timestamp,
        'sign_method: HMAC-SHA256'
    ];

    $url = $base_url . '/v1.0/iot-03/devices';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Adicionado para desenvolvimento
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Adicionado para desenvolvimento

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        echo 'Erro cURL: ' . curl_error($ch);
    }
    
    curl_close($ch);

    return json_decode($response, true);
}

// Execução principal
$token = getAccessToken($client_id, $client_secret, $base_url);
if ($token) {
    echo "<h3>Token obtido com sucesso!</h3>";
    $dispositivos = listarDispositivos($token, $client_id, $client_secret, $base_url);

    echo "<h3>Dispositivos encontrados:</h3><pre>";
    print_r($dispositivos);
    echo "</pre>";
} else {
    echo "❌ Falha ao obter token. Verifique as credenciais e a resposta do servidor acima.";
}