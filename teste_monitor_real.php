<?php
/**
 * Teste do Monitor de Leitura Facial com dados reais
 */

echo "<h1>🧪 Teste do Monitor de Leitura Facial (Dados Reais)</h1>";

// Testar a API de captura real
echo "<h2>1. Teste da API de Captura Real</h2>";

$url = "https://presenca.aom.org.br/api/monitor/fetch_dispositivo_real.php";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Content-Type: application/json',
        'timeout' => 30
    ]
]);

echo "URL: <code>$url</code><br><br>";

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
        
        if (isset($json['success']) && $json['success']) {
            echo "<h3>✅ Monitor funcionando!</h3>";
            echo "<p><strong>Estatísticas:</strong></p>";
            echo "<ul>";
            echo "<li>Total de leituras: " . ($json['estatisticas']['total_leituras'] ?? 0) . "</li>";
            echo "<li>Dispositivos ativos: " . ($json['estatisticas']['dispositivos_ativos'] ?? 0) . "</li>";
            echo "<li>Última leitura: " . ($json['estatisticas']['ultima_leitura'] ?? 'Nenhuma') . "</li>";
            echo "<li>Status do sistema: " . ($json['estatisticas']['status_sistema'] ?? 'Desconhecido') . "</li>";
            echo "</ul>";
            
            if (isset($json['leituras_processadas']) && $json['leituras_processadas'] > 0) {
                echo "<p><strong>Leituras processadas nesta consulta:</strong> " . $json['leituras_processadas'] . "</p>";
            }
            
            if (isset($json['logs']) && count($json['logs']) > 0) {
                echo "<p><strong>Logs recentes:</strong></p>";
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>Timestamp</th><th>Mensagem</th><th>Tipo</th></tr>";
                foreach ($json['logs'] as $log) {
                    $tipoClass = $log['tipo'] === 'error' ? 'color: red;' : 
                                $log['tipo'] === 'success' ? 'color: green;' : 'color: blue;';
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($log['timestamp'] ?? '-') . "</td>";
                    echo "<td>" . htmlspecialchars($log['mensagem'] ?? '-') . "</td>";
                    echo "<td style='$tipoClass'>" . htmlspecialchars($log['tipo'] ?? '-') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>Nenhum log encontrado.</p>";
            }
            
            if (isset($json['used'])) {
                echo "<p><strong>Método usado:</strong> " . htmlspecialchars($json['used']['method'] ?? 'N/A') . "</p>";
                echo "<p><strong>Fonte:</strong> " . htmlspecialchars($json['used']['name'] ?? 'N/A') . "</p>";
            }
        } else {
            echo "<h3>❌ Falha no monitor</h3>";
            echo "<p><strong>Erro:</strong> " . htmlspecialchars($json['error'] ?? 'Erro desconhecido') . "</p>";
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
