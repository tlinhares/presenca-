<?php
/**
 * Script de teste para a API de leitura facial
 * Acesse: https://presenca.aom.org.br/teste_api_facial.php
 */

echo "<h1>🧪 Teste da API de Leitura Facial</h1>";
echo "<p>Testando endpoint: <code>api/culto/receber_leitura_facial.php</code></p>";

// Teste 1: GET (deve retornar status)
echo "<h2>1. Teste GET (Status da API)</h2>";
$url = "http://localhost/presenca/api/culto/receber_leitura_facial.php";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Content-Type: application/json'
    ]
]);

$response = file_get_contents($url, false, $context);
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Teste 2: POST com dados válidos
echo "<h2>2. Teste POST (Dados Válidos)</h2>";
$dados_teste = [
    'nome_usuario' => 'Teste Usuario',
    'ip_dispositivo' => '192.168.1.100',
    'timestamp' => time(),
    'foto_base64' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ...'
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($dados_teste)
    ]
]);

$response = file_get_contents($url, false, $context);
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Teste 3: POST com dados inválidos
echo "<h2>3. Teste POST (Dados Inválidos)</h2>";
$dados_invalidos = [
    'nome_usuario' => '',
    'ip_dispositivo' => '',
    'timestamp' => ''
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($dados_invalidos)
    ]
]);

$response = file_get_contents($url, false, $context);
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Teste 4: Verificar logs
echo "<h2>4. Verificar Logs</h2>";
$logFile = __DIR__ . '/logs/leitura_facial_culto_' . date('Y-m-d') . '.log';
if (file_exists($logFile)) {
    $logs = file($logFile, FILE_IGNORE_NEW_LINES);
    $ultimas_linhas = array_slice($logs, -10);
    echo "<h3>Últimas 10 linhas do log:</h3>";
    echo "<pre>" . htmlspecialchars(implode("\n", $ultimas_linhas)) . "</pre>";
} else {
    echo "<p>Arquivo de log não encontrado: $logFile</p>";
}

echo "<hr>";
echo "<p><strong>Teste concluído em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>

