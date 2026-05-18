<?php
/**
 * Script para simular uma leitura facial
 */

echo "<h1>🧪 Simulador de Leitura Facial</h1>";

// Dados da leitura facial
$dados = [
    'user_id' => '1', // ID do usuário (deve existir na tabela usuarios)
    'event_type' => 'FaceRecognition',
    'result' => 'Pass',
    'time' => date('Y-m-d H:i:s')
];

echo "<h2>Dados da Leitura:</h2>";
echo "<pre>" . json_encode($dados, JSON_PRETTY_PRINT) . "</pre>";

// Enviar para a API de processamento
$url = "https://presenca.aom.org.br/api/culto/processar_leitura_facial.php";

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($dados),
        'timeout' => 30
    ]
]);

echo "<h2>Enviando para API...</h2>";
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
            echo "<h3>✅ Leitura processada com sucesso!</h3>";
            echo "<p><strong>Usuário:</strong> " . htmlspecialchars($json['usuario']['nome'] ?? 'N/A') . "</p>";
            echo "<p><strong>Status:</strong> " . htmlspecialchars($json['status'] ?? 'N/A') . "</p>";
            echo "<p><strong>Horário:</strong> " . htmlspecialchars($json['horario'] ?? 'N/A') . "</p>";
            echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($json['message'] ?? 'N/A') . "</p>";
        } else {
            echo "<h3>❌ Falha no processamento</h3>";
            echo "<p><strong>Erro:</strong> " . htmlspecialchars($json['error'] ?? 'Erro desconhecido') . "</p>";
        }
    } else {
        echo "❌ JSON inválido<br>";
        echo "Erro: " . json_last_error_msg() . "<br>";
    }
}

echo "<hr>";
echo "<p><strong>Teste concluído em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
