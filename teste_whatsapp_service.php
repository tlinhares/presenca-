<?php
/**
 * Script de teste do WhatsAppService
 * Acesse: https://presenca.aom.org.br/teste_whatsapp_service.php
 */

require_once __DIR__ . '/core/services/WhatsAppService.php';
require_once __DIR__ . '/api/conexao.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Teste WhatsAppService</title>";
echo "<style>body{font-family:Arial;padding:20px;} .sucesso{color:green;} .erro{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style></head><body>";

echo "<h1>🧪 Teste do WhatsAppService</h1>";

// Buscar dados do usuário 22222
$stmt = $conn->prepare("SELECT id, nome, telefone FROM usuarios WHERE id = 22222 LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$usuario_teste = $result->fetch_assoc();
$stmt->close();

if (!$usuario_teste) {
    echo "<p class='erro'>❌ Usuário ID 22222 não encontrado no banco de dados!</p>";
    echo "</body></html>";
    exit;
}

echo "<div class='info'>";
echo "<h2>👤 Usuário de Teste</h2>";
echo "<p><strong>ID:</strong> {$usuario_teste['id']}</p>";
echo "<p><strong>Nome:</strong> {$usuario_teste['nome']}</p>";
echo "<p><strong>Telefone Original:</strong> " . ($usuario_teste['telefone'] ?? 'Não cadastrado') . "</p>";
echo "</div>";

// Teste 1: Normalização
echo "<hr><h2>Teste 1: Normalização de Telefone</h2>";
$telefones_teste = [
    '6599793296',
    '556599793296',
    '+556599793296',
    '(65) 99979-3296',
    '5565999793296', // Com nono dígito
    $usuario_teste['telefone'] ?? '6599793296' // Telefone do usuário
];

echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr><th>Original</th><th>Normalizado</th><th>Formatado (+)</th></tr>";
foreach ($telefones_teste as $tel) {
    if (empty($tel)) continue;
    $normalizado = WhatsAppService::normalizarTelefone($tel);
    $formatado = '+' . $normalizado;
    echo "<tr><td>$tel</td><td>$normalizado</td><td>$formatado</td></tr>";
}
echo "</table>";

// Teste 2: Envio de mensagem
echo "<hr><h2>Teste 2: Envio de Mensagem</h2>";

$telefone_teste = $usuario_teste['telefone'] ?? '6599793296';
$mensagem_teste = '🧪 Teste do WhatsAppService Centralizado - ' . date('d/m/Y H:i:s');

echo "<p><strong>Telefone:</strong> $telefone_teste</p>";
echo "<p><strong>Mensagem:</strong> $mensagem_teste</p>";

echo "<p><button onclick='enviarMensagem()' style='padding:10px 20px;font-size:16px;cursor:pointer;'>Enviar Mensagem de Teste</button></p>";
echo "<div id='resultado_mensagem'></div>";

// Teste 3: Envio de arquivo (se houver arquivo de teste)
echo "<hr><h2>Teste 3: Envio de Arquivo</h2>";
echo "<p><em>Para testar envio de arquivo, crie um arquivo PDF de teste ou use um relatório existente.</em></p>";

echo "<script>
function enviarMensagem() {
    var btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Enviando...';
    var div = document.getElementById('resultado_mensagem');
    div.innerHTML = '<p class=\"info\">⏳ Enviando mensagem...</p>';
    
    fetch('teste_whatsapp_service_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            acao: 'enviar_mensagem',
            telefone: '$telefone_teste',
            mensagem: '$mensagem_teste'
        })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = 'Enviar Mensagem de Teste';
        if (data.sucesso) {
            div.innerHTML = '<p class=\"sucesso\">✅ ' + data.mensagem + '</p><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        } else {
            div.innerHTML = '<p class=\"erro\">❌ ' + data.mensagem + '</p><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = 'Enviar Mensagem de Teste';
        div.innerHTML = '<p class=\"erro\">❌ Erro: ' + err.message + '</p>';
    });
}
</script>";

echo "<hr>";
echo "<p><em>Versão do WhatsAppService: 1.0</em></p>";
echo "</body></html>";

