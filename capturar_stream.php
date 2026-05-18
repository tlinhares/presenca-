<?php
/**
 * Capturar stream e salvar em arquivo
 */

$ip = "10.144.129.64";
$user = "admin";
$pass = "Arcs2901";
$codes = "[_DoorFace_]";

$url = "https://presenca.aom.org.br/exemplo/stream_events.php?ip=" . urlencode($ip) . "&user=" . urlencode($user) . "&pass=" . urlencode($pass) . "&codes=" . urlencode($codes) . "&heartbeat=5";

echo "Capturando stream de: $url\n";
echo "Aguardando 5 segundos...\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Bytes recebidos: " . strlen($response) . "\n";

if ($error) {
    echo "Erro: $error\n";
}

// Salvar em arquivo mesmo com timeout
file_put_contents('/tmp/stream_response.txt', $response);
echo "Resposta salva em /tmp/stream_response.txt\n";

// Mostrar primeiros 1000 caracteres
echo "Primeiros 1000 caracteres:\n";
echo substr($response, 0, 1000) . "\n";
?>
