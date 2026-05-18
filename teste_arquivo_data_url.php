<?php
/**
 * Teste específico para envio de arquivos via Data URL
 * Acesse: https://presenca.aom.org.br/teste_arquivo_data_url.php
 */

$numero_teste = '5565999793296';

// Criar um arquivo de teste simples
$conteudo_teste = "Teste de arquivo CSV\nNome,Idade,Cidade\nJoão,30,São Paulo\nMaria,25,Rio de Janeiro";
$arquivo_teste = '/tmp/teste_arquivo_data_url.csv';
file_put_contents($arquivo_teste, $conteudo_teste);

// Converter para base64 e criar Data URL
$base64_arquivo = base64_encode($conteudo_teste);
$data_url = "data:text/csv;base64,{$base64_arquivo}";

echo "<h2>Teste de Envio de Arquivo via Data URL</h2>";
echo "<p><strong>Número:</strong> $numero_teste</p>";
echo "<p><strong>Arquivo:</strong> teste_arquivo_data_url.csv</p>";
echo "<p><strong>Tamanho base64:</strong> " . strlen($base64_arquivo) . " bytes</p>";
echo "<p><strong>Tamanho Data URL:</strong> " . strlen($data_url) . " bytes</p>";
echo "<p><strong>Data URL preview:</strong> " . substr($data_url, 0, 100) . "...</p>";
echo "<hr>";

// Dados para envio via WhatsApp com Data URL
$dados = [
    'phone' => $numero_teste,
    'isGroup' => false,
    'isNewsletter' => false,
    'isLid' => false,
    'filename' => 'teste_arquivo_data_url.csv',
    'caption' => '📈 Teste de arquivo CSV via Data URL - ' . date('d/m/Y H:i:s'),
    'base64' => $data_url
];

echo "<h3>Dados Enviados:</h3>";
echo "<pre>" . json_encode($dados, JSON_PRETTY_PRINT) . "</pre>";

echo "<h3>Enviando arquivo...</h3>";

$contexto = stream_context_create([
    'http' => [
        'timeout' => 60,
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
            'Authorization: Bearer $2b$10$HXuccMTGKs8y7aZuhrrxdOfPBw3DAFheEg6.pdZBBn6_7nPS4XLG2'
        ],
        'content' => json_encode($dados)
    ]
]);

$url_whatsapp = 'http://10.144.128.34:21465/api/servidor/send-file';
$resposta = file_get_contents($url_whatsapp, false, $contexto);

echo "<h3>Resposta da API:</h3>";
echo "<p><strong>Status HTTP:</strong> " . (isset($http_response_header) ? $http_response_header[0] : 'N/A') . "</p>";
echo "<p><strong>Resposta bruta:</strong></p>";
echo "<pre>" . htmlspecialchars($resposta) . "</pre>";

if ($resposta !== false) {
    $resposta_json = json_decode($resposta, true);
    echo "<h3>Resposta JSON:</h3>";
    echo "<pre>" . json_encode($resposta_json, JSON_PRETTY_PRINT) . "</pre>";
    
    if (isset($resposta_json['status']) && $resposta_json['status'] === 'success') {
        echo "<p style='color: green;'><strong>✅ SUCESSO!</strong> Arquivo enviado com sucesso!</p>";
    } else {
        echo "<p style='color: red;'><strong>❌ ERRO!</strong> " . ($resposta_json['message'] ?? 'Erro desconhecido') . "</p>";
    }
} else {
    echo "<p style='color: red;'><strong>❌ ERRO!</strong> Falha na comunicação com a API</p>";
}

// Limpar arquivo temporário
unlink($arquivo_teste);

echo "<hr>";
echo "<p><a href='painel/automacao_relatorios.php'>← Voltar para Automações</a></p>";
?>
