<?php
/**
 * Teste de geração de relatório
 * Acesse: https://presenca.aom.org.br/teste_geracao_relatorio.php
 */

$data_hoje = date('Y-m-d');
$timestamp = time();

echo "<h2>Teste de Geração de Relatório</h2>";
echo "<p><strong>Data:</strong> $data_hoje</p>";
echo "<p><strong>Timestamp:</strong> $timestamp</p>";
echo "<hr>";

// Testar diferentes tipos de relatório
$tipos = [
    'diario' => "https://presenca.aom.org.br/api/relatorios/exportar_pdf_diario.php?tipo=diario&data={$data_hoje}",
    'diario_completo' => "https://presenca.aom.org.br/api/relatorios/exportar_pdf_diario.php?tipo=diario_completo&data={$data_hoje}",
    'csv' => "https://presenca.aom.org.br/api/relatorios/exportar_csv.php",
    'csv_diario' => "https://presenca.aom.org.br/api/relatorios/exportar_csv_diario_automacao.php?tipo=diario&data={$data_hoje}"
];

foreach ($tipos as $tipo => $url) {
    echo "<h3>Testando: $tipo</h3>";
    echo "<p><strong>URL:</strong> $url</p>";
    
    $contexto = stream_context_create([
        'http' => [
            'timeout' => 30,
            'method' => 'GET',
            'header' => 'Content-Type: application/json'
        ]
    ]);
    
    $conteudo = file_get_contents($url, false, $contexto);
    
    if ($conteudo === false) {
        echo "<p style='color: red;'><strong>❌ ERRO!</strong> Falha ao baixar arquivo</p>";
    } else {
        $tamanho = strlen($conteudo);
        echo "<p style='color: green;'><strong>✅ SUCESSO!</strong> Arquivo baixado: $tamanho bytes</p>";
        
        // Verificar se é HTML (erro) ou arquivo real
        if (strpos($conteudo, '<html') !== false || strpos($conteudo, '<!DOCTYPE') !== false) {
            echo "<p style='color: orange;'><strong>⚠️ ATENÇÃO!</strong> Conteúdo parece ser HTML (possível erro)</p>";
            echo "<pre>" . htmlspecialchars(substr($conteudo, 0, 500)) . "...</pre>";
        } else {
            echo "<p style='color: green;'><strong>✅ OK!</strong> Conteúdo parece ser um arquivo válido</p>";
        }
        
        // Salvar arquivo de teste
        $arquivo_teste = "/tmp/teste_relatorio_{$tipo}_{$timestamp}";
        if ($tipo === 'csv' || $tipo === 'csv_diario') {
            $arquivo_teste .= '.csv';
        } else {
            $arquivo_teste .= '.pdf';
        }
        
        if (file_put_contents($arquivo_teste, $conteudo)) {
            echo "<p><strong>Arquivo salvo:</strong> $arquivo_teste</p>";
            echo "<p><strong>Tamanho no disco:</strong> " . filesize($arquivo_teste) . " bytes</p>";
        } else {
            echo "<p style='color: red;'><strong>❌ ERRO!</strong> Falha ao salvar arquivo</p>";
        }
    }
    
    echo "<hr>";
}

echo "<p><a href='painel/automacao_relatorios.php'>← Voltar para Automações</a></p>";
?>
