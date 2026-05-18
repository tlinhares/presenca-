<?php
/**
 * Teste de envio de arquivo via WhatsAppService
 * Acesse: https://presenca.aom.org.br/teste_whatsapp_arquivo.php
 */

require_once __DIR__ . '/core/services/WhatsAppService.php';
require_once __DIR__ . '/api/conexao.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Teste WhatsAppService - Arquivo</title>";
echo "<style>body{font-family:Arial;padding:20px;} .sucesso{color:green;} .erro{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border-radius:5px;overflow:auto;}</style></head><body>";

echo "<h1>🧪 Teste de Envio de Arquivo - WhatsAppService</h1>";

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
echo "<p><strong>Telefone:</strong> " . ($usuario_teste['telefone'] ?? 'Não cadastrado') . "</p>";
echo "</div>";

$telefone_teste = $usuario_teste['telefone'] ?? '6599793296';

// Verificar se há um relatório PDF disponível para teste
$arquivos_teste = [
    '/tmp/relatorio_teste.pdf',
    __DIR__ . '/tmp/relatorio_teste.pdf',
    sys_get_temp_dir() . '/relatorio_teste.pdf'
];

$arquivo_teste = null;
foreach ($arquivos_teste as $arquivo) {
    if (file_exists($arquivo)) {
        $arquivo_teste = $arquivo;
        break;
    }
}

// Se não existe, tentar gerar um relatório de teste
if (!$arquivo_teste) {
    echo "<hr><h2>📄 Gerando Arquivo de Teste</h2>";
    
    // Tentar gerar um relatório PDF diário de hoje
    $data_hoje = date('Y-m-d');
    $url_relatorio = "https://presenca.aom.org.br/api/relatorios/exportar_pdf_diario.php?tipo=diario&data={$data_hoje}";
    
    echo "<p>Tentando baixar relatório de: <a href='$url_relatorio' target='_blank'>$url_relatorio</a></p>";
    
    $contexto = stream_context_create([
        'http' => [
            'timeout' => 30,
            'method' => 'GET',
            'header' => 'Content-Type: application/json'
        ]
    ]);
    
    $conteudo = @file_get_contents($url_relatorio, false, $contexto);
    
    if ($conteudo !== false && strlen($conteudo) > 100) {
        // Verificar se é PDF (começa com %PDF)
        if (substr($conteudo, 0, 4) === '%PDF') {
            $arquivo_teste = sys_get_temp_dir() . '/relatorio_teste_' . time() . '.pdf';
            if (file_put_contents($arquivo_teste, $conteudo) !== false) {
                echo "<p class='sucesso'>✅ Relatório PDF gerado com sucesso!</p>";
                echo "<p><strong>Arquivo:</strong> $arquivo_teste</p>";
                echo "<p><strong>Tamanho:</strong> " . number_format(filesize($arquivo_teste)) . " bytes</p>";
            } else {
                echo "<p class='erro'>❌ Erro ao salvar arquivo de teste</p>";
            }
        } else {
            echo "<p class='erro'>❌ Resposta não é um PDF válido</p>";
            echo "<pre>" . htmlspecialchars(substr($conteudo, 0, 500)) . "</pre>";
        }
    } else {
        echo "<p class='erro'>❌ Não foi possível baixar o relatório</p>";
        echo "<p>Criando um PDF simples de teste...</p>";
        
        // Criar um PDF simples de teste
        $arquivo_teste = sys_get_temp_dir() . '/relatorio_teste_' . time() . '.pdf';
        $conteudo_pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> >>\nendobj\n4 0 obj\n<< /Length 44 >>\nstream\nBT\n/F1 12 Tf\n100 700 Td\n(Teste de Relatorio PDF) Tj\nET\nendstream\nendobj\nxref\n0 5\ntrailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n300\n%%EOF";
        
        if (file_put_contents($arquivo_teste, $conteudo_pdf) !== false) {
            echo "<p class='sucesso'>✅ PDF de teste criado!</p>";
            echo "<p><strong>Arquivo:</strong> $arquivo_teste</p>";
        } else {
            echo "<p class='erro'>❌ Erro ao criar PDF de teste</p>";
        }
    }
}

if (!$arquivo_teste || !file_exists($arquivo_teste)) {
    echo "<hr><p class='erro'>❌ Não foi possível criar ou encontrar um arquivo PDF para teste.</p>";
    echo "<p>Por favor, crie manualmente um arquivo PDF em: <code>" . sys_get_temp_dir() . "/relatorio_teste.pdf</code></p>";
    echo "</body></html>";
    exit;
}

echo "<hr><h2>📤 Teste de Envio de Arquivo</h2>";
echo "<p><strong>Telefone:</strong> $telefone_teste</p>";
echo "<p><strong>Arquivo:</strong> $arquivo_teste</p>";
echo "<p><strong>Tamanho:</strong> " . number_format(filesize($arquivo_teste)) . " bytes</p>";

$mensagem_teste = "🧪 Teste de envio de arquivo PDF via WhatsAppService\n\n";
$mensagem_teste .= "Data: " . date('d/m/Y H:i:s') . "\n";
$mensagem_teste .= "Este é um teste do sistema centralizado de WhatsApp.";

echo "<p><strong>Mensagem:</strong></p>";
echo "<pre>" . htmlspecialchars($mensagem_teste) . "</pre>";

echo "<hr><h2>🚀 Executando Teste</h2>";

// Teste 1: Apenas mensagem
echo "<h3>Teste 1: Enviar Mensagem</h3>";
$resultado_mensagem = WhatsAppService::enviarMensagem($telefone_teste, $mensagem_teste, [
    'log_callback' => function($msg) {
        echo "<p class='info'>📝 LOG: " . htmlspecialchars($msg) . "</p>";
    }
]);

echo "<pre>";
print_r($resultado_mensagem);
echo "</pre>";

if ($resultado_mensagem['sucesso']) {
    echo "<p class='sucesso'>✅ Mensagem enviada com sucesso!</p>";
} else {
    echo "<p class='erro'>❌ Erro ao enviar mensagem: {$resultado_mensagem['mensagem']}</p>";
}

// Aguardar um pouco
sleep(2);

// Teste 2: Enviar arquivo
echo "<hr><h3>Teste 2: Enviar Arquivo</h3>";
$resultado_arquivo = WhatsAppService::enviarArquivo($telefone_teste, $arquivo_teste, "📊 Relatório PDF de Teste - " . date('d/m/Y'), [
    'log_callback' => function($msg) {
        echo "<p class='info'>📝 LOG: " . htmlspecialchars($msg) . "</p>";
    }
]);

echo "<pre>";
print_r($resultado_arquivo);
echo "</pre>";

if ($resultado_arquivo['sucesso']) {
    echo "<p class='sucesso'>✅ Arquivo enviado com sucesso!</p>";
} else {
    echo "<p class='erro'>❌ Erro ao enviar arquivo: {$resultado_arquivo['mensagem']}</p>";
}

// Teste 3: Enviar mensagem e arquivo juntos
echo "<hr><h3>Teste 3: Enviar Mensagem e Arquivo (Método Combinado)</h3>";
$resultado_combinado = WhatsAppService::enviarMensagemEArquivo($telefone_teste, $mensagem_teste, $arquivo_teste, [
    'log_callback' => function($msg) {
        echo "<p class='info'>📝 LOG: " . htmlspecialchars($msg) . "</p>";
    }
]);

echo "<pre>";
print_r($resultado_combinado);
echo "</pre>";

if ($resultado_combinado['sucesso']) {
    echo "<p class='sucesso'>✅ Mensagem e arquivo enviados com sucesso!</p>";
} else {
    echo "<p class='erro'>❌ Erro: {$resultado_combinado['mensagem']}</p>";
}

// Limpar arquivo temporário
if (strpos($arquivo_teste, 'relatorio_teste_') !== false) {
    @unlink($arquivo_teste);
    echo "<p class='info'>🗑️ Arquivo temporário removido</p>";
}

echo "<hr>";
echo "<h2>📊 Resumo dos Testes</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;width:100%;'>";
echo "<tr><th>Teste</th><th>Status</th><th>Mensagem</th></tr>";
echo "<tr><td>Mensagem</td><td>" . ($resultado_mensagem['sucesso'] ? '✅ Sucesso' : '❌ Erro') . "</td><td>{$resultado_mensagem['mensagem']}</td></tr>";
echo "<tr><td>Arquivo</td><td>" . ($resultado_arquivo['sucesso'] ? '✅ Sucesso' : '❌ Erro') . "</td><td>{$resultado_arquivo['mensagem']}</td></tr>";
echo "<tr><td>Combinado</td><td>" . ($resultado_combinado['sucesso'] ? '✅ Sucesso' : '❌ Erro') . "</td><td>{$resultado_combinado['mensagem']}</td></tr>";
echo "</table>";

echo "<hr>";
echo "<p><a href='teste_whatsapp_service.php'>← Voltar para Teste de Mensagem</a> | ";
echo "<a href='painel/automacao_relatorios.php'>← Voltar para Automações</a></p>";

echo "</body></html>";

