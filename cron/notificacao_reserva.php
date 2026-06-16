<?php
/**
 * Script para notificar usuários que não fizeram reserva para o dia
 * Executa a cada hora via crontab (0 * * * *)
 */

// Configurar timezone
date_default_timezone_set('America/Cuiaba');

// Log de execução
$logFile = __DIR__ . '/../logs/notificacoes.log';
$timestamp = date('Y-m-d H:i:s');

require_once __DIR__ . '/../utils/logger.php';

function logNotificacoes($mensagem) {
    Logger::emergencial('notificacao_reserva', $mensagem);
}

logNotificacoes("=== INICIANDO NOTIFICAÇÃO DE RESERVAS ===");

try {
    // Incluir arquivos necessários
    require_once __DIR__ . '/../api/conexao.php';
    require_once __DIR__ . '/../utils/config.php';
    require_once __DIR__ . '/../core/services/WhatsAppService.php';
    
    $data_hoje = date('Y-m-d');
    $hora_atual = date('H:i');
    $dia_semana = date('N'); // 1=segunda, 7=domingo
    
    // Verificar se é dia comercial (segunda a sexta)
    if ($dia_semana > 5) {
        logNotificacoes("Não é dia comercial (dia da semana: $dia_semana) - saindo");
        exit(0);
    }
    
    logNotificacoes("Dia comercial confirmado: " . date('l') . " (dia $dia_semana)");
    
    // Buscar configuração de horário de notificação
    $stmt = $conn->prepare("SELECT valor FROM configuracoes WHERE chave = 'horario_notificacao_diaria'");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    $horario_configurado = $row['valor'] ?? '08:00';
    
    logNotificacoes("Horário configurado: $horario_configurado, Hora atual: $hora_atual");
    
    // Verificar se já foi executado hoje (verificação geral)
    $stmt = $conn->prepare("SELECT id FROM notificacoes_reserva WHERE data = ?");
    $stmt->bind_param("s", $data_hoje);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows > 0) {
        logNotificacoes("Notificações já enviadas hoje - saindo");
        exit(0);
    }
    
    // Verificar se é horário de envio (permitir apenas no horário exato ou até 30 minutos após)
    // Isso evita que o script execute múltiplas vezes no mesmo dia
    $horario_limite = date('H:i', strtotime($horario_configurado . ' +30 minutes'));
    
    if ($hora_atual < $horario_configurado) {
        logNotificacoes("Ainda não é horário de notificação (atual: $hora_atual, configurado: $horario_configurado)");
        exit(0);
    }
    
    if ($hora_atual > $horario_limite) {
        logNotificacoes("Horário limite ultrapassado (atual: $hora_atual, limite: $horario_limite) - Notificações só podem ser enviadas até 30 minutos após o horário configurado");
        exit(0);
    }
    
    logNotificacoes("✅ Horário de notificação confirmado: $hora_atual");
    
    // Verificar se o refeitório está fechado hoje
    $stmt_fechado = $conn->prepare("SELECT id FROM dias_fechado WHERE data = ? AND ativo = 1 LIMIT 1");
    $stmt_fechado->bind_param("s", $data_hoje);
    $stmt_fechado->execute();
    $result_fechado = $stmt_fechado->get_result();
    
    if ($result_fechado->num_rows > 0) {
        logNotificacoes("⚠️ Refeitório está fechado hoje ($data_hoje) - não enviando notificações");
        $stmt_fechado->close();
        exit(0);
    }
    $stmt_fechado->close();
    
    logNotificacoes("✅ Refeitório está aberto hoje - continuando com notificações");
    
    // Buscar usuários ativos que não têm reserva para hoje
    // E que ainda NÃO receberam notificação de lembrete_reserva hoje
    // E que têm notificar_lembrete_diario habilitado (ou NULL, que significa habilitado por padrão)
    $sql_usuarios = "
        SELECT u.id, u.nome, u.email, u.telefone, u.ativo
        FROM usuarios u
        LEFT JOIN notificacoes_usuario nu ON u.id = nu.id_usuario
        WHERE u.ativo = 1
        AND (nu.notificar_lembrete_diario IS NULL OR nu.notificar_lembrete_diario = 1)
        AND NOT EXISTS (
            SELECT 1 FROM reservas_almoco r 
            WHERE r.id_usuario = u.id 
            AND r.data = ?
        )
        AND NOT EXISTS (
            SELECT 1 FROM notificacoes_enviadas n
            WHERE n.usuario_id = u.id
            AND n.tipo_mensagem = 'lembrete_reserva'
            AND DATE(n.data_envio) = ?
            AND n.status = 'sucesso'
        )
    ";
    
    $stmt = $conn->prepare($sql_usuarios);
    $stmt->bind_param("ss", $data_hoje, $data_hoje);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $usuarios_sem_reserva = [];
    while ($row = $result->fetch_assoc()) {
        $usuarios_sem_reserva[] = $row;
    }
    
    if (empty($usuarios_sem_reserva)) {
        logNotificacoes("Nenhum usuário sem reserva encontrado");
        exit(0);
    }
    
    logNotificacoes("Encontrados " . count($usuarios_sem_reserva) . " usuários sem reserva");
    
    // Buscar configurações de email
    $config_email = [];
    $stmt = $conn->prepare("SELECT chave, valor FROM configuracoes WHERE chave IN ('smtp_email', 'port_email', 'senha_email', 'email_notificacoes', 'assunto_email_notificacao', 'template_email_notificacao', 'hora_limite', 'nome_remetente_email')");
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    while ($row = $result->fetch_assoc()) {
        $config_email[$row['chave']] = $row['valor'];
    }
    
    $horario_limite_agendamento = $config_email['hora_limite'] ?? '13:00';
    
    // Preparar template de mensagem
    $template = $config_email['template_email_notificacao'] ?? 'Olá {nome}, você ainda não fez sua reserva de almoço para hoje. Horário limite: {horario_limite}';
    
    // Garantir que a mensagem está em UTF-8
    $template = mb_convert_encoding($template, 'UTF-8', 'auto');
    
    // IMPORTANTE: Criar registro ANTES de começar a enviar para evitar envios duplicados
    // Se o cron rodar novamente antes de terminar, encontrará este registro e não enviará novamente
    // Usar INSERT IGNORE para evitar erro se já existir (caso de race condition)
    $stmt_lock = $conn->prepare("INSERT IGNORE INTO notificacoes_reserva (data, total_usuarios, total_enviados, total_falhas, horario_envio) VALUES (?, 0, 0, 0, NOW())");
    $stmt_lock->bind_param("s", $data_hoje);
    $stmt_lock->execute();
    
    // Verificar se o registro foi criado (se não foi, significa que já existia)
    if ($stmt_lock->affected_rows === 0) {
        logNotificacoes("⚠️ Registro já existe na tabela notificacoes_reserva - outro processo pode estar executando. Saindo para evitar envios duplicados.");
        $stmt_lock->close();
        exit(0);
    }
    
    $stmt_lock->close();
    logNotificacoes("🔒 Registro de bloqueio criado na tabela notificacoes_reserva para evitar envios duplicados");
    
    $total_enviados = 0;
    $total_falhas = 0;
    $total_whatsapp = 0;
    $total_email = 0;

    require_once __DIR__ . '/../core/services/NotificacaoService.php';

    logNotificacoes("Iniciando envio em cascata WhatsApp → email para " . count($usuarios_sem_reserva) . " usuários (config: whatsapp_apis.php / lembrete_reserva)");

    require_once __DIR__ . '/../core/services/PushNotificationService.php';

    foreach ($usuarios_sem_reserva as $index => $usuario) {
        $mensagem = str_replace(
            ['{nome}', '{horario_limite}'],
            [$usuario['nome'], $horario_limite_agendamento],
            $template
        );
        $mensagem = mb_convert_encoding($mensagem, 'UTF-8', 'auto');

        // Push em paralelo (silencioso se não configurado). Curto e direto.
        PushNotificationService::enviarSilencioso(
            $conn,
            (int) $usuario['id'],
            'Lembrete: faça sua reserva de almoço',
            'Você ainda não reservou para hoje. Horário limite: ' . $horario_limite_agendamento,
            ['tipo' => 'lembrete_reserva']
        );

        $telefone_normalizado = WhatsAppService::normalizarTelefone($usuario['telefone'] ?? '');
        $whatsapp_ok = false;

        // 1) Tentar WhatsApp se houver telefone válido — o WhatsAppService respeita a config de
        // 'lembrete_reserva' (modo sorteio/específica/desabilitado) e tenta cada API até esgotar.
        if (!empty($telefone_normalizado)) {
            if ($index > 0) {
                $delay = WhatsAppService::calcularDelayAleatorio(5, 15);
                logNotificacoes("Aguardando $delay segundos antes do próximo envio... (" . ($index + 1) . "/" . count($usuarios_sem_reserva) . ")");
                sleep($delay);
            }

            $resultado_wpp = WhatsAppService::enviarMensagem($usuario['telefone'], $mensagem, [
                'log_callback' => function ($msg) {
                    logNotificacoes("WhatsApp: $msg");
                },
                'usuario_id' => $usuario['id'],
                'nome_destinatario' => $usuario['nome'],
                'tipo_mensagem' => 'lembrete_reserva',
                'tipo_notificacao' => 'lembrete_reserva',
            ]);

            if (!empty($resultado_wpp['sucesso'])) {
                $whatsapp_ok = true;
                $total_whatsapp++;
                $total_enviados++;
                logNotificacoes("✓ WhatsApp enviado para {$usuario['nome']} ($telefone_normalizado)");
                continue; // canal único entregue, próximo usuário
            }

            logNotificacoes("✗ WhatsApp falhou para {$usuario['nome']}: " . ($resultado_wpp['mensagem'] ?? 'Erro desconhecido') . " — caindo para email");
        }

        // 2) Fallback: email (se canal WhatsApp foi desabilitado, falhou em todas as APIs, ou usuário sem telefone)
        $tem_email = !empty($usuario['email']) && filter_var($usuario['email'], FILTER_VALIDATE_EMAIL);
        if (!$tem_email) {
            $total_falhas++;
            logNotificacoes("✗ Usuário {$usuario['nome']} sem telefone válido nem email — lembrete não enviado");
            continue;
        }

        try {
            $enviado_email = enviarEmail($usuario['email'], $usuario['nome'], $mensagem, $config_email);

            try {
                $assunto = $config_email['assunto_email_notificacao'] ?? 'Lembrete: Faça sua reserva de almoço';
                $erro = $enviado_email ? null : 'Falha ao enviar email';
                NotificacaoService::gravarEmail(
                    $usuario['email'],
                    $assunto,
                    $mensagem,
                    $enviado_email,
                    $erro,
                    $usuario['id'],
                    $usuario['nome'],
                    'lembrete_reserva'
                );
            } catch (Exception $e) {
                error_log("Erro ao gravar notificação de email: " . $e->getMessage());
            }

            if ($enviado_email) {
                $total_email++;
                $total_enviados++;
                logNotificacoes("✓ Email enviado para {$usuario['nome']} ({$usuario['email']})");
            } else {
                $total_falhas++;
                logNotificacoes("✗ Falha ao enviar email para {$usuario['nome']} ({$usuario['email']})");
            }
        } catch (Exception $e) {
            $total_falhas++;
            logNotificacoes("✗ Erro ao enviar email para {$usuario['nome']}: " . $e->getMessage());
        }
    }

    logNotificacoes("📊 Distribuição: $total_whatsapp via WhatsApp, $total_email via email (fallback), $total_falhas falhas");
    
    // Atualizar registro na tabela com os valores finais (já foi criado antes de começar)
    $total_usuarios = $total_enviados + $total_falhas;
    $stmt_resumo = $conn->prepare("UPDATE notificacoes_reserva SET total_usuarios = ?, total_enviados = ?, total_falhas = ?, horario_envio = NOW() WHERE data = ?");
    $stmt_resumo->bind_param("iiis", $total_usuarios, $total_enviados, $total_falhas, $data_hoje);
    $stmt_resumo->execute();
    $stmt_resumo->close();
    
    logNotificacoes("✅ Registro atualizado na tabela notificacoes_reserva: $total_usuarios usuários, $total_enviados enviados, $total_falhas falhas");
    
    // Log do resumo
    logNotificacoes("=== RESUMO: $total_enviados enviados, $total_falhas falhas ===");
    
} catch (Exception $e) {
    logNotificacoes("ERRO: " . $e->getMessage());
    exit(1);
}

logNotificacoes("=== NOTIFICAÇÃO DE RESERVAS CONCLUÍDA ===");

/**
 * Busca mensagem aleatória do tipo especificado na tabela mensagens_padrao
 * Se não encontrar, retorna mensagem padrão como fallback
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param string $tipo Tipo da mensagem (ex: 'lembrete_reserva')
 * @param array $dados Array com dados para substituir placeholders (ex: ['nome' => 'João', 'horario_limite' => '09:01'])
 * @return string Mensagem com placeholders substituídos
 */
function buscarMensagemAleatoria($conn, $tipo, $dados = []) {
    try {
        $stmt = $conn->prepare("
            SELECT mensagem 
            FROM mensagens_padrao 
            WHERE tipo = ? AND ativo = 1 
            ORDER BY RAND() 
            LIMIT 1
        ");
        
        if (!$stmt) {
            throw new Exception("Erro ao preparar consulta: " . $conn->error);
        }
        
        $stmt->bind_param("s", $tipo);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows === 0) {
            // Fallback para mensagem padrão
            $mensagem_padrao = 'Olá {nome}, você ainda não fez sua reserva de almoço para hoje. Horário limite: {horario_limite}';
        } else {
            $row = $result->fetch_assoc();
            $mensagem_padrao = $row['mensagem'];
        }
        
        // Substituir placeholders
        foreach ($dados as $key => $value) {
            $mensagem_padrao = str_replace('{' . $key . '}', $value, $mensagem_padrao);
        }
        
        return $mensagem_padrao;
        
    } catch (Exception $e) {
        // Em caso de erro, usar mensagem padrão
        error_log("Erro ao buscar mensagem aleatória: " . $e->getMessage());
        $mensagem_padrao = 'Olá {nome}, você ainda não fez sua reserva de almoço para hoje. Horário limite: {horario_limite}';
        
        // Substituir placeholders mesmo no fallback
        foreach ($dados as $key => $value) {
            $mensagem_padrao = str_replace('{' . $key . '}', $value, $mensagem_padrao);
        }
        
        return $mensagem_padrao;
    }
}

// Funções antigas removidas - usando WhatsAppService agora
// Todas as funções de envio foram migradas para WhatsAppService

/**
 * Enviar email usando PHPMailer
 */
function enviarEmail($email, $nome, $mensagem, $config) {
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configurações do servidor
        $mail->isSMTP();
        $mail->Host = $config['smtp_email'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['email_notificacoes'];
        $mail->Password = $config['senha_email'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $config['port_email'];
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
        // Remetente
        $mail->setFrom($config['email_notificacoes'], $config['nome_remetente_email'] ?? 'Sistema de Presença AOM');
        
        // Destinatário
        $mail->addAddress($email, $nome);
        
        // Conteúdo
        $mail->isHTML(false);
        $mail->Subject = $config['assunto_email_notificacao'] ?? 'Lembrete: Faça sua reserva de almoço';
        $mail->Body = $mensagem;
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        logNotificacoes("Erro ao enviar email: " . $e->getMessage());
        return false;
    }
}
?>

