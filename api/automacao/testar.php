<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();


include_once(__DIR__ . '/../conexao.php');
require_once __DIR__ . '/../../core/services/WhatsAppService.php';

// Verificar se a conexão foi estabelecida
if (!isset($conn) || !$conn) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro de conexão com o banco de dados']);
    exit;
}

// Verificar se a sessão está ativa
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

// Verificar se é admin


// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;
    
    if (!$id) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID da automação não fornecido']);
        exit;
    }
    
    // Buscar dados da automação
    $stmt = $conn->prepare("SELECT * FROM automacoes_relatorios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Automação não encontrada']);
        exit;
    }
    
    $automacao = $result->fetch_assoc();
    $stmt->close();
    
    // Executar teste da automação
    $resultado = executarAutomacao($automacao);
    
    if ($resultado['sucesso']) {
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => 'Teste executado com sucesso! ' . $resultado['mensagem']
        ]);
    } else {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro no teste: ' . $resultado['mensagem']
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
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
                },
                'tipo_mensagem' => 'automacao_relatorio',
                'tipo_notificacao' => 'automacao_relatorio',
                'nome_destinatario' => 'Automação de Relatórios'
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
            error_log("Tipo de relatório inválido: " . $tipo);
            return false;
    }
    
    error_log("Gerando relatório - Tipo: $tipo, URL: $url, Arquivo: $arquivo");
    
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
        error_log("Erro ao baixar arquivo da URL: $url");
        return false;
    }
    
    error_log("Conteúdo baixado: " . strlen($conteudo) . " bytes");
    
    // Salvar arquivo
    if (file_put_contents($arquivo, $conteudo) === false) {
        error_log("Erro ao salvar arquivo: $arquivo");
        return false;
    }
    
    error_log("Arquivo salvo com sucesso: $arquivo");
    return $arquivo;
}

// Funções antigas removidas - usando WhatsAppService agora
// Todas as funções de envio foram migradas para WhatsAppService
?>
