<?php
/**
 * Teste do Monitor de Leitura Facial
 */

echo "<h1>🧪 Teste do Monitor de Leitura Facial</h1>";

// Testar a API de captura
echo "<h2>1. Teste da API de Captura</h2>";

$url = "https://presenca.aom.org.br/api/monitor/fetch_dispositivo.php";
$params = [
    'ip' => '192.168.3.87',
    'user' => 'admin',
    'pass' => 'acesso1234'
];

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Content-Type: application/json',
        'timeout' => 30
    ]
]);

$urlWithParams = $url . '?' . http_build_query($params);
echo "URL: <code>$urlWithParams</code><br><br>";

$response = @file_get_contents($urlWithParams, false, $context);

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
        
        if (isset($json['success']) && $json['success']) {
            echo "<h3>✅ Captura bem-sucedida!</h3>";
            echo "<p><strong>Estatísticas:</strong></p>";
            echo "<ul>";
            echo "<li>Total de leituras: " . ($json['estatisticas']['total_leituras'] ?? 0) . "</li>";
            echo "<li>Dispositivos ativos: " . ($json['estatisticas']['dispositivos_ativos'] ?? 0) . "</li>";
            echo "<li>Última leitura: " . ($json['estatisticas']['ultima_leitura'] ?? 'Nenhuma') . "</li>";
            echo "<li>Status do sistema: " . ($json['estatisticas']['status_sistema'] ?? 'Desconhecido') . "</li>";
            echo "</ul>";
            
            if (isset($json['eventos']) && count($json['eventos']) > 0) {
                echo "<p><strong>Eventos capturados:</strong></p>";
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>Usuário</th><th>Cartão/Face</th><th>Tipo</th><th>Resultado</th><th>Hora</th></tr>";
                foreach ($json['eventos'] as $evento) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($evento['user_id'] ?? '-') . "</td>";
                    echo "<td>" . htmlspecialchars($evento['card'] ?? $evento['face'] ?? '-') . "</td>";
                    echo "<td>" . htmlspecialchars($evento['event_type'] ?? '-') . "</td>";
                    echo "<td>" . htmlspecialchars($evento['result'] ?? '-') . "</td>";
                    echo "<td>" . htmlspecialchars($evento['time'] ?? '-') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>Nenhum evento capturado neste período.</p>";
            }
            
            if (isset($json['used'])) {
                echo "<p><strong>Método usado:</strong> " . htmlspecialchars($json['used']['method'] ?? 'N/A') . "</p>";
                echo "<p><strong>Timezone:</strong> " . htmlspecialchars($json['used']['timezone_format'] ?? 'N/A') . "</p>";
                echo "<p><strong>Nome do registro:</strong> " . htmlspecialchars($json['used']['name'] ?? 'N/A') . "</p>";
            }
        } else {
            echo "<h3>❌ Falha na captura</h3>";
            echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($json['message'] ?? 'Erro desconhecido') . "</p>";
            
            if (isset($json['tried']) && count($json['tried']) > 0) {
                echo "<p><strong>Tentativas realizadas:</strong></p>";
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>Método</th><th>Timezone</th><th>Nome</th><th>URL</th><th>HTTP</th><th>Erro</th></tr>";
                foreach ($json['tried'] as $tentativa) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($tentativa['method'] ?? '-') . "</td>";
                    echo "<td>" . htmlspecialchars($tentativa['tz'] ?? '-') . "</td>";
                    echo "<td>" . htmlspecialchars($tentativa['name'] ?? '-') . "</td>";
                    echo "<td style='max-width: 300px; word-break: break-all;'>" . htmlspecialchars($tentativa['url'] ?? '-') . "</td>";
                    echo "<td>" . htmlspecialchars($tentativa['code'] ?? '-') . "</td>";
                    echo "<td>" . htmlspecialchars($tentativa['err'] ?? '-') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        }
    } else {
        echo "❌ JSON inválido<br>";
        echo "Erro: " . json_last_error_msg() . "<br>";
        echo "Primeiros 200 caracteres:<br>";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 200)) . "...</pre>";
    }
}

echo "<hr>";
echo "<p><strong>Teste concluído em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
