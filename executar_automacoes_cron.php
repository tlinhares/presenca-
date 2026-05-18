<?php
/**
 * Script de Cron para Executar Automações de Relatórios
 * 
 * Este script deve ser executado de hora em hora via crontab:
 * 0 * * * * /usr/bin/php /var/www/html/presenca/executar_automacoes_cron.php
 * 
 * Funcionalidades:
 * - Executa de hora em hora
 * - Tolerância de ±5 minutos para o horário configurado
 * - Envio único por dia (verifica ultimo_envio)
 * - Processa apenas automações ativas
 * - Logs detalhados de execução
 */

// Incluir WhatsAppService
require_once __DIR__ . '/core/services/WhatsAppService.php';

// Configurações
$tolerancia_minutos = 60; // Tolerância de ±60 minutos (temporário para teste)
$log_file = __DIR__ . '/logs/automacao_cron_' . date('Y-m-d') . '.log';

// Configurar timezone para Cuiabá
date_default_timezone_set('America/Cuiaba');

// Criar diretório de logs se não existir
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Função de log
function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    echo $log_entry;
}

// Iniciar execução
logMessage("=== INICIANDO EXECUÇÃO DE AUTOMAÇÕES ===<br>");
logMessage("Data/Hora: " . date('Y-m-d H:i:s') . "<br>");
logMessage("PID: " . getmypid());
logMessage("Usuário: " . get_current_user() . "<br>");

try {
    // Incluir conexão com banco
    include_once(__DIR__ . '/api/conexao.php');
    
    if (!isset($conn) || !$conn) {
        throw new Exception("Erro de conexão com o banco de dados<br>");
    }
    
    logMessage("Conexão com banco estabelecida com sucesso<br>");
    
    // Buscar automações ativas
    $sql = "SELECT * FROM automacoes_relatorios WHERE ativo = 1 ORDER BY id";
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Erro ao buscar automações: " . $conn->error);
    }
    
    $automacoes = $result->fetch_all(MYSQLI_ASSOC);
    logMessage("Encontradas " . count($automacoes) . " automações ativas<br>");
    
    $hoje = date('Y-m-d');
    $agora = date('H:i');
    $processadas = 0;
    $enviadas = 0;
    $erros = 0;
    
    // Log de informações do ambiente
    logMessage("Data de referência: $hoje.<br>");
    logMessage("<br>Horário atual: $agora.<br>");
    logMessage("<br>Tolerância configurada: {$tolerancia_minutos} minutos");
    
    foreach ($automacoes as $automacao) {
        $processadas++;
        logMessage("Processando automação ID {$automacao['id']}: {$automacao['nome']}<br>");
        
        try {
            // Verificar se deve executar hoje
            if (!deveExecutarHoje($automacao, $hoje)) {
                logMessage("  → Não deve executar hoje (dias da semana)<br>");
                continue;
            }
            
            // Verificar se já foi enviada hoje
            if (jaEnviadaHoje($automacao, $hoje)) {
                logMessage("  → Já foi enviada hoje (ultimo_envio: {$automacao['ultimo_envio']})<br>");
                continue;
            }
            
            // Verificar se está no horário (com tolerância)
            if (!estaNoHorario($automacao, $agora, $tolerancia_minutos)) {
                logMessage("  → Fora do horário (configurado: {$automacao['horario_envio']}, atual: $agora)<br>");
                continue;
            }
            
            // Executar automação
            logMessage("  → EXECUTANDO AUTOMAÇÃO<br>");
            logMessage("  → DEBUG - Tipo: {$automacao['tipo_relatorio']}, WhatsApp: {$automacao['numero_whatsapp']}<br>");
            $resultado = executarAutomacao($automacao);
            
            if ($resultado['sucesso']) {
                $enviadas++;
                logMessage("  → ✅ SUCESSO: {$resultado['mensagem']}<br>");
                
                // Atualizar ultimo_envio
                atualizarUltimoEnvio($conn, $automacao['id']);
                
                // Log de sucesso
                logarExecucao($conn, $automacao['id'], 'sucesso', $resultado['mensagem']);
                
            } else {
                $erros++;
                logMessage("  → ❌ ERRO: {$resultado['mensagem']}<br>");
                
                // Log de erro
                logarExecucao($conn, $automacao['id'], 'falha', $resultado['mensagem']);
            }
            
        } catch (Exception $e) {
            $erros++;
            logMessage("  → ❌ EXCEÇÃO: " . $e->getMessage() . "<br>");
            
            // Log de erro
            logarExecucao($conn, $automacao['id'], 'falha', $e->getMessage());
        }
    }
    
    // Resumo da execução
    logMessage("=== RESUMO DA EXECUÇÃO ===<br>");
    logMessage("Automações processadas: $processadas");
    logMessage("Relatórios enviados: $enviadas<br>");
    logMessage("Erros: $erros<br>");
    
    // Log de status final
    if ($processadas == 0) {
        logMessage("Nenhuma automação ativa encontrada para processar<br>");
    } elseif ($enviadas == 0 && $erros == 0) {
        logMessage("Nenhuma automação executada (fora do horário ou já enviada hoje)<br>");
    } elseif ($enviadas > 0) {
        logMessage("Execução bem-sucedida com $enviadas envio(s)<br>");
    }
    
    logMessage("Tempo de execução: " . (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) . " segundos");
    logMessage("<br>=== FIM DA EXECUÇÃO ===");
    
} catch (Exception $e) {
    logMessage("ERRO CRÍTICO: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    logMessage("<br>=== FIM DA EXECUÇÃO (COM ERRO) ===");
    exit(1);
}

/**
 * Verifica se a automação deve executar hoje baseado nos dias da semana
 */
function deveExecutarHoje($automacao, $hoje) {
    $dias_semana = json_decode($automacao['dias_semana'], true);
    if (!$dias_semana || empty($dias_semana)) {
        return false;
    }
    
    $dia_atual = date('N', strtotime($hoje)); // 1=Segunda, 7=Domingo
    return in_array($dia_atual, $dias_semana);
}

/**
 * Verifica se já foi enviada hoje
 */
function jaEnviadaHoje($automacao, $hoje) {
    if (empty($automacao['ultimo_envio'])) {
        return false;
    }
    
    $ultimo_envio = date('Y-m-d', strtotime($automacao['ultimo_envio']));
    return $ultimo_envio === $hoje;
}

/**
 * Verifica se está no horário (com tolerância)
 */
function estaNoHorario($automacao, $agora, $tolerancia_minutos) {
    $horario_configurado = $automacao['horario_envio'];
    
    // Converter horários para minutos desde meia-noite
    $agora_minutos = timeParaMinutos($agora);
    $configurado_minutos = timeParaMinutos($horario_configurado);
    
    // Calcular diferença
    $diferenca = abs($agora_minutos - $configurado_minutos);
    
    return $diferenca <= $tolerancia_minutos;
}

/**
 * Converte horário HH:MM para minutos desde meia-noite
 */
function timeParaMinutos($time) {
    list($hora, $minuto) = explode(':', $time);
    return (int)$hora * 60 + (int)$minuto;
}

/**
 * Atualiza o último envio da automação no banco de dados
 * 
 * @param mysqli $conn Conexão com o banco
 * @param int $id ID da automação
 */
function atualizarUltimoEnvio($conn, $id) {
    try {
        $stmt = $conn->prepare("UPDATE automacoes_relatorios SET ultimo_envio = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            logMessage("  → ultimo_envio atualizado para automação ID $id<br>");
        }
    } catch (Exception $e) {
        logMessage("  → AVISO: Erro ao atualizar ultimo_envio: " . $e->getMessage() . "<br>");
    }
}

/**
 * Registra log de execução no banco de dados
 * 
 * @param mysqli $conn Conexão com o banco
 * @param int $id ID da automação
 * @param string $status Status da execução (sucesso/falha)
 * @param string $mensagem Mensagem de log
 */
function logarExecucao($conn, $id, $status, $mensagem) {
    try {
        // Verificar se a tabela existe antes de tentar inserir
        $result = $conn->query("SHOW TABLES LIKE 'logs_automacao'");
        if ($result && $result->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO logs_automacao (automacao_id, data_envio, status, mensagem) VALUES (?, NOW(), ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iss", $id, $status, $mensagem);
                $stmt->execute();
                $stmt->close();
            }
        }
    } catch (Exception $e) {
        // Silenciosamente ignora erros de log para não interromper o fluxo principal
        logMessage("  → AVISO: Erro ao registrar log: " . $e->getMessage() . "<br>");
    }
}

/**
 * Executa uma automação específica
 */
function executarAutomacao($automacao) {
    try {
        // Gerar relatório
        logMessage("    → DEBUG - Iniciando geração de relatório tipo: {$automacao['tipo_relatorio']}<br>");
        $arquivo_relatorio = gerarRelatorio($automacao['tipo_relatorio']);
        logMessage("    → DEBUG - Arquivo gerado: $arquivo_relatorio<br>");
        
        if (!$arquivo_relatorio || !file_exists($arquivo_relatorio)) {
            logMessage("    → DEBUG - Erro: Arquivo não existe ou não foi gerado<br>");
            return ['sucesso' => false, 'mensagem' => 'Erro ao gerar relatório'];
        }
        
        logMessage("    → DEBUG - Arquivo confirmado existente antes de enviar<br>");
        logMessage("    → DEBUG - Tamanho do arquivo: " . filesize($arquivo_relatorio) . " bytes<br>");
        
        // Enviar via WhatsApp (mensagem + arquivo)
        // Enviar via WhatsApp usando WhatsAppService
        $mensagem = $automacao['mensagem_personalizada'] ?: "Relatório automático gerado em " . date('d/m/Y H:i:s');
        $resultado = WhatsAppService::enviarMensagemEArquivo(
            $automacao['numero_whatsapp'], 
            $mensagem, 
            $arquivo_relatorio,
            [
                'log_callback' => function($msg) {
                    logMessage("    → $msg<br>");
                }
            ]
        );
        
        // Limpar arquivo temporário APENAS após tentar enviar
        if (file_exists($arquivo_relatorio)) {
            @unlink($arquivo_relatorio);
            logMessage("    → DEBUG - Arquivo temporário removido<br>");
        }
        
        return $resultado;
        
    } catch (Exception $e) {
        logMessage("    → ERRO EXCEÇÃO em executarAutomacao: " . $e->getMessage() . "<br>");
        // Limpar arquivo em caso de exceção também
        if (isset($arquivo_relatorio) && file_exists($arquivo_relatorio)) {
            @unlink($arquivo_relatorio);
        }
        return ['sucesso' => false, 'mensagem' => $e->getMessage()];
    }
}

/**
 * Gera o relatório baseado no tipo
 */
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
            throw new Exception("Tipo de relatório inválido: $tipo");
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
        throw new Exception("Erro ao baixar arquivo da URL: $url");
    }
    
    // Salvar arquivo
    if (file_put_contents($arquivo, $conteudo) === false) {
        throw new Exception("Erro ao salvar arquivo: $arquivo");
    }
    
    return $arquivo;
}

// Funções antigas removidas - usando WhatsAppService agora
// Todas as funções de envio foram migradas para WhatsAppService
?>
