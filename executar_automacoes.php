<?php
/**
 * Script para executar automações de relatórios
 * Deve ser executado via cron job
 * Exemplo: 0,5,10,15,20,25,30,35,40,45,50,55 * * * * /usr/bin/php /var/www/html/presenca/executar_automacoes.php
 */

include_once(__DIR__ . '/api/conexao.php');
require_once __DIR__ . '/core/services/WhatsAppService.php';

// Verificar se a conexão foi estabelecida
if (!isset($conn) || !$conn) {
    error_log("Erro de conexão com o banco de dados");
    exit(1);
}

try {
    $agora = date('H:i:s');
    $dia_semana = date('N'); // 1=Segunda, 2=Terça, ..., 7=Domingo
    
    // Buscar automações ativas que devem ser executadas agora
    $sql = "SELECT * FROM automacoes_relatorios 
            WHERE ativo = 1 
            AND horario_envio <= ? 
            AND JSON_CONTAINS(dias_semana, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $agora, $dia_semana);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $automacoes = [];
    while ($row = $result->fetch_assoc()) {
        $automacoes[] = $row;
    }
    $stmt->close();
    
    if (empty($automacoes)) {
        echo "Nenhuma automação para executar agora.\n";
        exit(0);
    }
    
    echo "Executando " . count($automacoes) . " automação(ões)...\n";
    
    foreach ($automacoes as $automacao) {
        echo "Executando automação: {$automacao['nome']}\n";
        
        $resultado = executarAutomacao($automacao);
        
        if ($resultado['sucesso']) {
            echo "✓ Sucesso: {$resultado['mensagem']}\n";
        } else {
            echo "✗ Erro: {$resultado['mensagem']}\n";
        }
        
        // Log do resultado
        $stmt = $conn->prepare("
            INSERT INTO logs_automacao (automacao_id, data_envio, status, mensagem) 
            VALUES (?, NOW(), ?, ?)
        ");
        $status = $resultado['sucesso'] ? 'sucesso' : 'erro';
        $stmt->bind_param("iss", $automacao['id'], $status, $resultado['mensagem']);
        $stmt->execute();
        $stmt->close();
    }
    
    echo "Execução concluída.\n";

} catch (Exception $e) {
    error_log("Erro na execução das automações: " . $e->getMessage());
    exit(1);
}

function executarAutomacao($automacao) {
    try {
        // Gerar relatório baseado no tipo
        $arquivo_relatorio = gerarRelatorio($automacao['tipo_relatorio']);
        
        if (!$arquivo_relatorio) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao gerar relatório'];
        }
        
        // Enviar via WhatsApp usando WhatsAppService
        $mensagem = $automacao['mensagem_personalizada'] ?: "Relatório automático gerado em " . date('d/m/Y H:i:s');
        $resultado_whatsapp = WhatsAppService::enviarMensagemEArquivo(
            $automacao['numero_whatsapp'], 
            $mensagem, 
            $arquivo_relatorio,
            [
                'log_callback' => function($msg) {
                    error_log("WhatsApp Automação: $msg");
                }
            ]
        );
        
        if ($resultado_whatsapp['sucesso']) {
            // Atualizar último envio
            global $conn;
            $stmt = $conn->prepare("UPDATE automacoes_relatorios SET ultimo_envio = NOW() WHERE id = ?");
            $stmt->bind_param("i", $automacao['id']);
            $stmt->execute();
            $stmt->close();
            
            return ['sucesso' => true, 'mensagem' => 'Relatório enviado com sucesso via WhatsApp'];
        } else {
            return ['sucesso' => false, 'mensagem' => 'Erro ao enviar WhatsApp: ' . $resultado_whatsapp['mensagem']];
        }
        
    } catch (Exception $e) {
        return ['sucesso' => false, 'mensagem' => $e->getMessage()];
    }
}

function gerarRelatorio($tipo) {
    $data_hoje = date('Y-m-d');
    $timestamp = time();
    
    switch ($tipo) {
        case 'diario':
            $url = "https://presenca.aom.org.br/api/relatorios/exportar_pdf_diario.php?tipo=diario&data={$data_hoje}";
            $arquivo = "/tmp/relatorio_diario_{$timestamp}.pdf";
            break;
        case 'diario_completo':
            $url = "https://presenca.aom.org.br/api/relatorios/exportar_pdf_diario.php?tipo=diario_completo&data={$data_hoje}";
            $arquivo = "/tmp/relatorio_diario_completo_{$timestamp}.pdf";
            break;
        case 'csv':
            $url = "https://presenca.aom.org.br/api/relatorios/exportar_csv.php";
            $arquivo = "/tmp/relatorio_csv_{$timestamp}.csv";
            break;
        case 'csv_diario':
            $url = "https://presenca.aom.org.br/api/relatorios/exportar_csv_diario_automacao.php?tipo=diario&data={$data_hoje}";
            $arquivo = "/tmp/relatorio_csv_diario_{$timestamp}.csv";
            break;
        default:
            return false;
    }
    
    // Baixar arquivo
    $contexto = stream_context_create([
        'http' => [
            'timeout' => 30,
            'method' => 'GET',
            'header' => 'Content-Type: application/json'
        ]
    ]);
    
    $conteudo = file_get_contents($url, false, $contexto);
    
    if ($conteudo === false) {
        return false;
    }
    
    // Salvar arquivo
    if (file_put_contents($arquivo, $conteudo) === false) {
        return false;
    }
    
    return $arquivo;
}

// Funções antigas removidas - usando WhatsAppService agora
// Todas as funções de envio foram migradas para WhatsAppService
?>
