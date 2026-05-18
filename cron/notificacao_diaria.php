<?php
/**
 * Script para envio de notificações diárias
 * Executa a cada hora via crontab
 */

// Configurar timezone
date_default_timezone_set('America/Cuiaba');

// Log de execução
$logFile = __DIR__ . '/../logs/notificacoes_antigo.log';
$timestamp = date('Y-m-d H:i:s');

require_once __DIR__ . '/../utils/logger.php';

function logNotificacoes($mensagem) {
    Logger::emergencial('notificacao_diaria', $mensagem);
}

// Funções antigas removidas - usando WhatsAppService agora
// Todas as funções de envio foram migradas para WhatsAppService

/*
function normalizarTelefone($telefone) {
    if (empty($telefone) || $telefone === null) {
        return '';
    }
    
    // Converter para string se necessário
    $telefone = (string)$telefone;
    
    // Remove espaços extras e trim
    $telefone = trim($telefone);
    
    // Verificar se é um valor inválido conhecido
    if ($telefone === '' || $telefone === '0' || $telefone === 'null' || strtolower($telefone) === 'null') {
        return '';
    }
    
    // Remove tudo que não é dígito (0-9)
    $telefone_normalizado = preg_replace('/[^0-9]/', '', $telefone);
    
    // Validar se tem pelo menos 10 dígitos (formato brasileiro mínimo: DDD + 8 ou 9 dígitos)
    if (strlen($telefone_normalizado) < 10) {
        return '';
    }
    
    // Se o número não começa com código do país do Brasil (55), adicionar
    if (!str_starts_with($telefone_normalizado, '55')) {
        $telefone_normalizado = '55' . $telefone_normalizado;
    }
    
    // Validar tamanho final (código do país + DDD + número)
    // Formato esperado: 55 + DDD (2 dígitos) + número (8 ou 9 dígitos) = 12 ou 13 dígitos
    if (strlen($telefone_normalizado) < 12 || strlen($telefone_normalizado) > 13) {
        return '';
    }
    
    // WhatsApp não aceita o nono dígito - se tiver 13 dígitos, remover o nono dígito
    // Formato: 55 (país) + DDD (2 dígitos) + número (8 ou 9 dígitos)
    // Se tiver 13 dígitos (55 + DDD + 9 dígitos), remover o primeiro dígito dos 9
    if (strlen($telefone_normalizado) === 13) {
        // Remover o nono dígito (posição 4, após 55 + DDD)
        $telefone_normalizado = substr($telefone_normalizado, 0, 4) . substr($telefone_normalizado, 5);
    }
    
    return $telefone_normalizado;
}

/**
 * Gerar relatório PDF diário
 */
function gerarRelatorioDiario($data_hoje) {
    $timestamp = time();
    $url = "https://presenca.aom.org.br/api/relatorios/exportar_pdf_diario.php?tipo=diario&data={$data_hoje}";
    $arquivo = "/tmp/relatorio_diario_{$timestamp}.pdf";
    
    logNotificacoes("Gerando relatório diário - URL: $url");
    
    // Baixar arquivo
    $contexto = stream_context_create([
        'http' => [
            'timeout' => 30,
            'method' => 'GET',
            'header' => 'Content-Type: application/json'
        ]
    ]);
    
    $conteudo = @file_get_contents($url, false, $contexto);
    
    if ($conteudo === false) {
        logNotificacoes("ERRO: Não foi possível baixar o relatório da URL: $url");
        return false;
    }
    
    // Verificar se o conteúdo é válido (PDF deve começar com %PDF)
    if (substr($conteudo, 0, 4) !== '%PDF') {
        logNotificacoes("ERRO: Conteúdo baixado não é um PDF válido");
        return false;
    }
    
    // Salvar arquivo
    if (file_put_contents($arquivo, $conteudo) === false) {
        logNotificacoes("ERRO: Não foi possível salvar o arquivo: $arquivo");
        return false;
    }
    
    logNotificacoes("Relatório gerado com sucesso: $arquivo (" . strlen($conteudo) . " bytes)");
    return $arquivo;
}

/*
function enviarArquivoWhatsApp($telefone, $arquivo) {
    try {
        // Normalizar número de telefone
        $telefone_normalizado = normalizarTelefone($telefone);
        
        if (empty($telefone_normalizado)) {
            logNotificacoes("ERRO: Número de telefone inválido para envio de arquivo");
            return false;
        }
        
        // Adicionar + no início
        $telefone_com_codigo = '+' . ltrim($telefone_normalizado, '+');
        
        // Verificar se arquivo existe
        if (!file_exists($arquivo)) {
            logNotificacoes("ERRO: Arquivo não encontrado: $arquivo");
            return false;
        }
        
        // Ler arquivo e converter para base64
        $conteudo_arquivo = file_get_contents($arquivo);
        if ($conteudo_arquivo === false) {
            logNotificacoes("ERRO: Não foi possível ler o arquivo: $arquivo");
            return false;
        }
        
        $base64_arquivo = base64_encode($conteudo_arquivo);
        $nome_arquivo = basename($arquivo);
        $extensao = pathinfo($arquivo, PATHINFO_EXTENSION);
        
        // Determinar o tipo MIME
        $mime_type = 'application/pdf';
        if ($extensao === 'csv') {
            $mime_type = 'text/csv';
        }
        
        // Criar Data URL
        $data_url = "data:{$mime_type};base64,{$base64_arquivo}";
        
        // Caption do arquivo
        $caption = '📊 Relatório Diário - ' . date('d/m/Y');
        
        // Dados para envio via WhatsApp
        $dados_arquivo = [
            'phone' => $telefone_com_codigo,
            'isGroup' => false,
            'isNewsletter' => false,
            'isLid' => false,
            'filename' => $nome_arquivo,
            'caption' => $caption,
            'base64' => $data_url
        ];
        
        $url_whatsapp = 'http://10.144.128.34:21465/api/servidor/send-file';
        
        $ch = curl_init($url_whatsapp);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($dados_arquivo),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
                'Authorization: Bearer $2b$10$HXuccMTGKs8y7aZuhrrxdOfPBw3DAFheEg6.pdZBBn6_7nPS4XLG2'
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $resposta_arquivo = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        logNotificacoes("Enviando arquivo WhatsApp - HTTP Code: $http_code");
        
        if ($resposta_arquivo === false || !empty($curl_error)) {
            logNotificacoes("ERRO ao enviar arquivo: " . ($curl_error ?: 'Erro desconhecido'));
            return false;
        }
        
        $resposta_json = json_decode($resposta_arquivo, true);
        
        if (isset($resposta_json['status']) && $resposta_json['status'] === 'success') {
            logNotificacoes("Arquivo enviado com sucesso para $telefone_com_codigo");
            return true;
        } else {
            $erro_detalhado = $resposta_json['message'] ?? $resposta_json['error'] ?? 'Erro desconhecido';
            logNotificacoes("ERRO no envio do arquivo: " . $erro_detalhado);
            return false;
        }
        
    } catch (Exception $e) {
        logNotificacoes("ERRO ao enviar arquivo: " . $e->getMessage());
        return false;
    }
}
*/

/*
function enviarWhatsApp($telefone, $mensagem) {
    try {
        // Normalizar número de telefone antes de enviar (já foi normalizado antes, mas garantir)
        $telefone_normalizado = normalizarTelefone($telefone);
        
        if (empty($telefone_normalizado)) {
            logNotificacoes("ERRO: Número de telefone inválido após normalização: '$telefone'");
            return false;
        }
        
        logNotificacoes("Enviando WhatsApp - Original: '$telefone' → Normalizado: '$telefone_normalizado'");
        
        // Adicionar sinal de + no início do número (requisito da API do WhatsApp)
        // A função normalizarTelefone remove todos os caracteres não numéricos, então sempre adicionamos o +
        $telefone_com_codigo = '+' . ltrim($telefone_normalizado, '+');
        
        // GARANTIR que o número sempre tenha o + no início
        if (substr($telefone_com_codigo, 0, 1) !== '+') {
            $telefone_com_codigo = '+' . $telefone_com_codigo;
        }
        
        // Dados para envio via WhatsApp
        $dados = [
            'phone' => $telefone_com_codigo,
            'isGroup' => false,
            'isNewsletter' => false,
            'isLid' => false,
            'message' => $mensagem
        ];
        
        // Atualizar o array de dados com o número garantido
        $dados['phone'] = $telefone_com_codigo;
        
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
        $resposta = file_get_contents($url_whatsapp, false, $contexto);
        
        logNotificacoes("Enviando WhatsApp para $telefone_com_codigo: " . substr($mensagem, 0, 50) . "...");
        logNotificacoes("Resposta WhatsApp: " . $resposta);
        
        if ($resposta === false) {
            logNotificacoes("Erro na comunicação com API do WhatsApp");
            return false;
        }
        
        $resposta_json = json_decode($resposta, true);
        
        // A API retorna "status": "success" em vez de "success": true
        if (isset($resposta_json['status']) && $resposta_json['status'] === 'success') {
            logNotificacoes("WhatsApp enviado com sucesso para $telefone_com_codigo");
            return true;
        } else {
            $erro_detalhado = $resposta_json['message'] ?? $resposta_json['error'] ?? 'Erro desconhecido';
            logNotificacoes("Erro no envio WhatsApp: " . $erro_detalhado);
            return false;
        }
        
    } catch (Exception $e) {
        logNotificacoes("Erro ao enviar WhatsApp: " . $e->getMessage());
        return false;
    }
}
*/

logNotificacoes("=== INICIANDO NOTIFICAÇÕES DIÁRIAS ===");

try {
    // Incluir arquivos necessários
    require_once __DIR__ . '/../api/conexao.php';
    require_once __DIR__ . '/../utils/config.php';
    require_once __DIR__ . '/../core/services/WhatsAppService.php';
    
    $data_hoje = date('Y-m-d');
    $hora_atual = date('H:i');
    
    // Buscar configuração de horário de notificação
    $stmt = $conn->prepare("SELECT valor FROM configuracoes WHERE chave = 'horario_notificacao_diaria'");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    $horario_configurado = $row['valor'] ?? '08:00';
    
    logNotificacoes("Configuração lida do banco: horario_notificacao_diaria = $horario_configurado");
    logNotificacoes("Hora atual: $hora_atual");
    
    // Verificar se é horário de envio de notificações
    if ($hora_atual !== $horario_configurado) {
        logNotificacoes("Não é horário de notificação (atual: $hora_atual, configurado: $horario_configurado)");
        exit(0);
    }
    
    logNotificacoes("✅ Horário de notificação confirmado: $hora_atual (configurado: $horario_configurado)");
    
    // Buscar usuários com reservas para hoje
    $sql_reservas = "
        SELECT COUNT(*) as total_reservas
        FROM reservas_almoco 
        WHERE data = ?
    ";
    $stmt = $conn->prepare($sql_reservas);
    $stmt->bind_param("s", $data_hoje);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_reservas = $row['total_reservas'];
    
    // Buscar dependentes com reservas para hoje
    $sql_dependentes = "
        SELECT COUNT(*) as total_dependentes
        FROM reservas_adicionais 
        WHERE data = ?
    ";
    $stmt = $conn->prepare($sql_dependentes);
    $stmt->bind_param("s", $data_hoje);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_dependentes = $row['total_dependentes'];
    
    $total_geral = $total_reservas + $total_dependentes;
    
    logNotificacoes("Reservas para hoje: $total_reservas próprias + $total_dependentes dependentes = $total_geral total");
    
    // Verificar sincronizações faciais
    $sql_sync = "
        SELECT 
            COUNT(*) as total_sync,
            COUNT(CASE WHEN status = 'sincronizado' THEN 1 END) as sincronizados,
            COUNT(CASE WHEN status = 'falha' THEN 1 END) as falhas
        FROM facial_sync 
        WHERE data = ?
    ";
    $stmt = $conn->prepare($sql_sync);
    $stmt->bind_param("s", $data_hoje);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $total_sync = $row['total_sync'];
    $sincronizados = $row['sincronizados'];
    $falhas = $row['falhas'];
    
    logNotificacoes("Sincronizações: $sincronizados sucessos, $falhas falhas de $total_sync total");
    
    // Preparar mensagem de notificação
    $mensagem = "📊 RELATÓRIO DIÁRIO - " . date('d/m/Y') . "\n\n";
    $mensagem .= "🍽️ RESERVAS DE ALMOÇO:\n";
    $mensagem .= "• Próprias: $total_reservas\n";
    $mensagem .= "• Dependentes: $total_dependentes\n";
    $mensagem .= "• Total: $total_geral\n\n";
    $mensagem .= "👤 SINCRONIZAÇÃO FACIAL:\n";
    $mensagem .= "• Sincronizados: $sincronizados\n";
    $mensagem .= "• Falhas: $falhas\n";
    $mensagem .= "• Total processado: $total_sync\n\n";
    
    if ($falhas > 0) {
        $mensagem .= "⚠️ ATENÇÃO: $falhas falhas na sincronização facial!\n";
    }
    
    if ($total_geral == 0) {
        $mensagem .= "ℹ️ Nenhuma reserva para hoje.\n";
    }
    
    $mensagem .= "\n🕐 Relatório gerado às " . date('H:i') . "\n\n";
    $mensagem .= "🔗 *Acesse o sistema:*\n";
    $mensagem .= "https://presenca.aom.org.br/\n\n";
    $mensagem .= "💡 *Lembrete:* Não esqueça de acessar o sistema para gerenciar suas reservas e verificar informações atualizadas!\n\n";
    $mensagem .= "🤖 Sistema de Presença Facial";
    
    logNotificacoes("Mensagem preparada: " . strlen($mensagem) . " caracteres");
    logNotificacoes("Conteúdo: " . str_replace("\n", " | ", $mensagem));
    
    // Gerar relatório PDF diário
    $arquivo_relatorio = gerarRelatorioDiario($data_hoje);
    if (!$arquivo_relatorio) {
        logNotificacoes("AVISO: Não foi possível gerar o relatório PDF, continuando apenas com mensagem");
    }
    
    // Buscar telefones de administradores para envio
    // Filtrar telefones válidos (não nulos, não vazios, não '0', não 'null', e com pelo menos 10 dígitos após normalização)
    $sql_telefones = "
        SELECT id, nome, telefone 
        FROM usuarios 
        WHERE categoria = 'admin' 
        AND telefone IS NOT NULL 
        AND telefone != '' 
        AND telefone != 'null'
        AND telefone != '0'
        AND TRIM(telefone) != ''
        AND LENGTH(TRIM(telefone)) >= 10
    ";
    $stmt = $conn->prepare($sql_telefones);
    $stmt->execute();
    $result_telefones = $stmt->get_result();
    
    $total_telefones_encontrados = $result_telefones->num_rows;
    logNotificacoes("Total de administradores com telefone encontrados: $total_telefones_encontrados");
    
    // Preparar lista de destinatários
    $destinatarios = [];
    $telefones_enviados = 0;
    $telefones_erro = 0;
    
    while ($row = $result_telefones->fetch_assoc()) {
        $telefone = trim($row['telefone']); // Remove espaços iniciais/finais
        
        logNotificacoes("Processando usuário ID: {$row['id']}, Nome: {$row['nome']}");
        logNotificacoes("Telefone original: '$telefone' (tipo: " . gettype($telefone) . ", tamanho: " . strlen($telefone) . ")");
        
        // Normalizar telefone antes de enviar usando WhatsAppService
        $telefone_normalizado = WhatsAppService::normalizarTelefone($telefone);
        
        if (!empty($telefone_normalizado)) {
            $destinatarios[] = [
                'telefone' => $telefone,
                'mensagem' => $mensagem,
                'arquivo' => $arquivo_relatorio,
                'nome' => $row['nome'],
                'id' => $row['id'],
                'usuario_id' => $row['id']
            ];
            logNotificacoes("Telefone normalizado para usuário ID {$row['id']}: '$telefone' → '$telefone_normalizado'");
        } else {
            $telefones_erro++;
            logNotificacoes("AVISO: Telefone inválido ou vazio após normalização para usuário ID {$row['id']} ({$row['nome']}): '$telefone' - Pulando envio");
        }
    }
    $stmt->close();
    
    // Enviar em lote com delay inteligente
    if (!empty($destinatarios)) {
        logNotificacoes("Enviando " . count($destinatarios) . " relatórios WhatsApp com delay aleatório entre 5-15 segundos...");
        
        foreach ($destinatarios as $index => $destinatario) {
            // Delay aleatório (exceto primeiro envio)
            if ($index > 0) {
                $delay = WhatsAppService::calcularDelayAleatorio(5, 15);
                logNotificacoes("Aguardando $delay segundos antes do próximo envio... (" . ($index + 1) . "/" . count($destinatarios) . ")");
                sleep($delay);
            }
            
            // Enviar mensagem e arquivo usando WhatsAppService
            $resultado = WhatsAppService::enviarMensagemEArquivo($destinatario['telefone'], $destinatario['mensagem'], $destinatario['arquivo'], [
                'log_callback' => function($msg) {
                    logNotificacoes("WhatsApp: $msg");
                },
                'usuario_id' => $destinatario['usuario_id'] ?? $destinatario['id'] ?? null,
                'nome_destinatario' => $destinatario['nome'] ?? null,
                'tipo_notificacao' => 'relatorio_diario',
                'tipo_mensagem' => 'relatorio_diario'
            ]);
            
            if ($resultado['sucesso']) {
                $telefones_enviados++;
                logNotificacoes("✓ Mensagem e arquivo enviados para {$destinatario['nome']} (" . ($index + 1) . "/" . count($destinatarios) . ")");
            } else {
                $telefones_erro++;
                logNotificacoes("✗ Falha ao enviar WhatsApp para {$destinatario['nome']}: " . ($resultado['mensagem'] ?? 'Erro desconhecido'));
            }
        }
    }
    
    logNotificacoes("Envio WhatsApp: $telefones_enviados sucessos, $telefones_erro erros");
    logNotificacoes("Notificação preparada e enviada com sucesso");
    
    // Limpar arquivo temporário do relatório
    if ($arquivo_relatorio && file_exists($arquivo_relatorio)) {
        @unlink($arquivo_relatorio);
        logNotificacoes("Arquivo temporário removido: $arquivo_relatorio");
    }
    
} catch (Exception $e) {
    logNotificacoes("ERRO: " . $e->getMessage());
    exit(1);
}

logNotificacoes("=== NOTIFICAÇÕES DIÁRIAS CONCLUÍDAS ===");
?>
