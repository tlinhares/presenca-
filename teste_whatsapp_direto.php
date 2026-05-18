<?php
/**
 * Teste direto da API do WhatsApp
 * Acesse: https://presenca.aom.org.br/teste_whatsapp_direto.php
 */

$numero_teste = '5565999793296';
$mensagem_teste = '🧪 Teste direto da API - ' . date('d/m/Y H:i:s');

// Dados para envio via WhatsApp
$dados = [
    'phone' => $numero_teste,
    'isGroup' => false,
    'isNewsletter' => false,
    'isLid' => false,
    'message' => $mensagem_teste
];

$contexto = stream_context_create([
    'http' => [
        'timeout' => 30,
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Authorization: Bearer $2b$10$HXuccMTGKs8y7aZuhrrxdOfPBw3DAFheEg6.pdZBBn6_7nPS4XLG2'
        ],
        'content' => json_encode($dados)
    ]
]);

$url_whatsapp = 'http://10.144.128.34:21465/api/servidor/send-message';

echo "<h2>Teste Direto da API WhatsApp</h2>";
echo "<p><strong>URL:</strong> $url_whatsapp</p>";
echo "<p><strong>Número:</strong> $numero_teste</p>";
echo "<p><strong>Mensagem:</strong> $mensagem_teste</p>";
echo "<hr>";

echo "<h3>Dados Enviados:</h3>";
echo "<pre>" . json_encode($dados, JSON_PRETTY_PRINT) . "</pre>";

echo "<h3>Enviando...</h3>";

$resposta = file_get_contents($url_whatsapp, false, $contexto);

echo "<h3>Resposta da API:</h3>";
echo "<p><strong>Status HTTP:</strong> " . (isset($http_response_header) ? $http_response_header[0] : 'N/A') . "</p>";
echo "<p><strong>Resposta bruta:</strong></p>";
echo "<pre>" . htmlspecialchars($resposta) . "</pre>";

if ($resposta !== false) {
    $resposta_json = json_decode($resposta, true);
    echo "<h3>Resposta JSON:</h3>";
    echo "<pre>" . json_encode($resposta_json, JSON_PRETTY_PRINT) . "</pre>";
    
    if (isset($resposta_json['success']) && $resposta_json['success']) {
        echo "<p style='color: green;'><strong>✅ SUCESSO!</strong> Mensagem enviada com sucesso!</p>";
    } else {
        echo "<p style='color: red;'><strong>❌ ERRO!</strong> " . ($resposta_json['message'] ?? 'Erro desconhecido') . "</p>";
    }
} else {
    echo "<p style='color: red;'><strong>❌ ERRO!</strong> Falha na comunicação com a API</p>";
}

echo "<hr>";
echo "<p><a href='painel/automacao_relatorios.php'>← Voltar para Automações</a></p>";
?>
