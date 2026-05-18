<?php
/**
 * Script de debug para identificar problemas na API facial
 */

echo "<h1>🔍 Debug da API Facial</h1>";

// Verificar se a API existe
$apiFile = __DIR__ . '/api/culto/receber_leitura_facial.php';
echo "<h2>1. Verificação do Arquivo</h2>";
if (file_exists($apiFile)) {
    echo "✅ Arquivo existe: $apiFile<br>";
    echo "Tamanho: " . filesize($apiFile) . " bytes<br>";
    echo "Última modificação: " . date('Y-m-d H:i:s', filemtime($apiFile)) . "<br>";
} else {
    echo "❌ Arquivo não encontrado: $apiFile<br>";
}

// Verificar logs
echo "<h2>2. Verificação dos Logs</h2>";
$logFile = __DIR__ . '/logs/leitura_facial_culto_' . date('Y-m-d') . '.log';
if (file_exists($logFile)) {
    echo "✅ Log existe: $logFile<br>";
    $logs = file($logFile, FILE_IGNORE_NEW_LINES);
    echo "Total de linhas: " . count($logs) . "<br>";
    
    // Mostrar últimas 5 linhas
    $ultimas = array_slice($logs, -5);
    echo "<h3>Últimas 5 linhas:</h3>";
    echo "<pre>" . htmlspecialchars(implode("\n", $ultimas)) . "</pre>";
} else {
    echo "❌ Log não encontrado: $logFile<br>";
}

// Verificar diretório de logs
echo "<h2>3. Verificação do Diretório de Logs</h2>";
$logsDir = __DIR__ . '/logs';
if (is_dir($logsDir)) {
    echo "✅ Diretório existe: $logsDir<br>";
    echo "Permissões: " . substr(sprintf('%o', fileperms($logsDir)), -4) . "<br>";
} else {
    echo "❌ Diretório não existe: $logsDir<br>";
    echo "Tentando criar...<br>";
    if (mkdir($logsDir, 0755, true)) {
        echo "✅ Diretório criado com sucesso<br>";
    } else {
        echo "❌ Falha ao criar diretório<br>";
    }
}

// Teste de requisição
echo "<h2>4. Teste de Requisição</h2>";

// Detectar URL base automaticamente
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = "$protocol://$host" . dirname($_SERVER['REQUEST_URI']);
$url = $baseUrl . "/api/culto/receber_leitura_facial.php";

echo "URL sendo testada: <code>$url</code><br><br>";

// Teste GET
echo "<h3>Teste GET:</h3>";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 10
    ]
]);

$response = @file_get_contents($url, false, $context);
if ($response !== false) {
    echo "✅ Resposta GET recebida:<br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
} else {
    echo "❌ Falha na requisição GET<br>";
    $error = error_get_last();
    if ($error) {
        echo "Erro: " . $error['message'] . "<br>";
    }
}

// Teste POST
echo "<h3>Teste POST:</h3>";
$dados = [
    'nome_usuario' => 'Teste Debug',
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
if ($response !== false) {
    echo "✅ Resposta POST recebida:<br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
} else {
    echo "❌ Falha na requisição POST<br>";
    $error = error_get_last();
    if ($error) {
        echo "Erro: " . $error['message'] . "<br>";
    }
}

// Teste direto (include)
echo "<h2>5. Teste Direto (Include)</h2>";
echo "<h3>Simulando requisição GET:</h3>";

// Simular GET
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['HTTP_USER_AGENT'] = 'Debug Script';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/presenca/api/culto/receber_leitura_facial.php';

ob_start();
try {
    include $apiFile;
    $output = ob_get_contents();
    echo "✅ API executada com sucesso:<br>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
} catch (Exception $e) {
    echo "❌ Erro na execução: " . $e->getMessage() . "<br>";
}
ob_end_clean();

echo "<h3>Simulando requisição POST:</h3>";

// Simular POST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Simular dados POST
$dados_post = [
    'nome_usuario' => 'Teste Debug',
    'ip_dispositivo' => '192.168.1.100',
    'timestamp' => time()
];

// Simular input stream
$input_data = json_encode($dados_post);
file_put_contents('php://temp', $input_data);

ob_start();
try {
    include $apiFile;
    $output = ob_get_contents();
    echo "✅ API executada com sucesso:<br>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
} catch (Exception $e) {
    echo "❌ Erro na execução: " . $e->getMessage() . "<br>";
}
ob_end_clean();

echo "<hr>";
echo "<p><strong>Debug concluído em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
