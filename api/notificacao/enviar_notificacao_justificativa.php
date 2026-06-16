<?php
/**
 * Função para enviar notificações sobre decisão de justificativas de culto
 * Verifica se o usuário tem notificações habilitadas e envia via WhatsApp ou Email
 */

if (!defined('NOTIFICACAO_JUSTIFICATIVA_LOADED')) {
    define('NOTIFICACAO_JUSTIFICATIVA_LOADED', true);
    
    require_once __DIR__ . '/../conexao.php';
    require_once __DIR__ . '/../../includes/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../../includes/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../../includes/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/../../core/services/WhatsAppService.php';
    require_once __DIR__ . '/../../core/services/NotificacaoService.php';
    require_once __DIR__ . '/enviar_notificacao_reserva.php'; // Reutilizar funções auxiliares
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Verificar se usuário quer receber notificação de justificativas
 * 
 * IMPORTANTE: Se não há configuração na tabela, assume que o usuário QUER receber (padrão permitir)
 */
function usuarioQuerNotificacaoJustificativa($usuario_id, $conn) {
    // Verificar se tabela existe
    $tabela_existe = $conn->query("SHOW TABLES LIKE 'notificacoes_usuario'")->num_rows > 0;
    
    if (!$tabela_existe) {
        // Tabela não existe, permitir notificação por padrão
        return true;
    }
    
    // Verificar se coluna existe (pode não existir em tabelas antigas)
    $coluna_existe = false;
    $result_cols = $conn->query("SHOW COLUMNS FROM notificacoes_usuario LIKE 'notificar_justificativa_culto'");
    if ($result_cols && $result_cols->num_rows > 0) {
        $coluna_existe = true;
    }
    
    if (!$coluna_existe) {
        // Coluna não existe, permitir por padrão
        return true;
    }
    
    $stmt = $conn->prepare("SELECT notificar_justificativa_culto FROM notificacoes_usuario WHERE id_usuario = ?");
    if (!$stmt) {
        // Erro ao preparar query, permitir por padrão
        return true;
    }
    
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        // Se a coluna existe mas é NULL, assume que quer receber (padrão)
        return $row['notificar_justificativa_culto'] !== null ? (bool)$row['notificar_justificativa_culto'] : true;
    }
    
    $stmt->close();
    // Se não tem configuração, assume que quer receber (padrão)
    return true;
}

/**
 * Enviar notificação de decisão de justificativa
 */
function enviarNotificacaoJustificativa($usuario_id, $decisao, $dados_justificativa, $conn) {
    // Verificar se usuário quer receber notificação
    if (!usuarioQuerNotificacaoJustificativa($usuario_id, $conn)) {
        return ['sucesso' => false, 'mensagem' => 'Usuário não habilitou notificações de justificativas'];
    }
    
    // Buscar dados do usuário
    $stmt = $conn->prepare("SELECT nome, email, telefone FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['sucesso' => false, 'mensagem' => 'Usuário não encontrado'];
    }
    
    $usuario = $result->fetch_assoc();
    $stmt->close();
    
    // Gerar mensagens
    $mensagens = gerarMensagemNotificacaoJustificativa($decisao, $dados_justificativa, $usuario['nome']);

    // Push (em paralelo ao canal principal — silencioso se não configurado).
    PushNotificationService::enviarSilencioso(
        $conn,
        (int) $usuario_id,
        $mensagens['assunto'],
        PushNotificationService::corpoCurto($mensagens['email_texto'] ?? ''),
        ['tipo' => 'justificativa_' . $decisao]
    );

    // Verificar se tem telefone válido usando WhatsAppService
    $telefone_normalizado = WhatsAppService::normalizarTelefone($usuario['telefone']);
    $tem_telefone = !empty($telefone_normalizado);
    $tem_email = !empty($usuario['email']) && filter_var($usuario['email'], FILTER_VALIDATE_EMAIL);
    
    // LÓGICA: Se tem telefone, envia APENAS por WhatsApp
    // Se não tem telefone mas tem email, envia APENAS por email
    // NUNCA envia por ambos
    
    if ($tem_telefone) {
        // Tem telefone: enviar APENAS por WhatsApp usando WhatsAppService
        // Passar dados para gravar no histórico
        $resultado = WhatsAppService::enviarMensagem($usuario['telefone'], $mensagens['whatsapp'], [
            'usuario_id' => $usuario_id,
            'nome_destinatario' => $usuario['nome'],
            'tipo_mensagem' => 'justificativa_culto',
            'tipo_notificacao' => 'justificativa_culto'
        ]);
        
        if ($resultado['sucesso']) {
            return ['sucesso' => true, 'metodo' => 'whatsapp', 'mensagem' => 'Notificação enviada via WhatsApp'];
        } else {
            // WhatsApp falhou - se fallback_email estiver marcado, tentar email
            if (isset($resultado['fallback_email']) && $resultado['fallback_email'] && $tem_email) {
                // Fallback automático para email
                $resultado_email = enviarEmail(
                    $usuario['email'],
                    $usuario['nome'],
                    $mensagens['assunto'],
                    $mensagens['email_html'],
                    $mensagens['email_texto'],
                    $conn
                );
                
                // Gravar notificação de email no histórico
                try {
                    $erro_email = $resultado_email['sucesso'] ? null : $resultado_email['mensagem'];
                    NotificacaoService::gravarEmail(
                        $usuario['email'],
                        $mensagens['assunto'],
                        $mensagens['email_texto'],
                        $resultado_email['sucesso'],
                        $erro_email,
                        $usuario_id,
                        $usuario['nome'],
                        'justificativa_culto'
                    );
                } catch (Exception $e) {
                    error_log("Erro ao gravar notificação de email: " . $e->getMessage());
                }
                
                if ($resultado_email['sucesso']) {
                    return ['sucesso' => true, 'metodo' => 'email', 'mensagem' => 'Notificação enviada via Email (fallback)'];
                }
            }
            
            // WhatsApp falhou e email também falhou ou não está disponível
            return ['sucesso' => false, 'metodo' => 'whatsapp', 'mensagem' => 'Falha ao enviar WhatsApp: ' . $resultado['mensagem']];
        }
    } elseif ($tem_email) {
        // Não tem telefone mas tem email: enviar APENAS por email
        $resultado = enviarEmail(
            $usuario['email'],
            $usuario['nome'],
            $mensagens['assunto'],
            $mensagens['email_html'],
            $mensagens['email_texto'],
            $conn
        );
        
        // Gravar notificação no histórico
        try {
            $erro = $resultado['sucesso'] ? null : $resultado['mensagem'];
            NotificacaoService::gravarEmail(
                $usuario['email'],
                $mensagens['assunto'],
                $mensagens['email_texto'],
                $resultado['sucesso'],
                $erro,
                $usuario_id,
                $usuario['nome'],
                'justificativa_culto'
            );
        } catch (Exception $e) {
            error_log("Erro ao gravar notificação de email: " . $e->getMessage());
        }
        
        if ($resultado['sucesso']) {
            return ['sucesso' => true, 'metodo' => 'email', 'mensagem' => 'Notificação enviada via Email'];
        }
        
        return $resultado;
    } else {
        // Não tem telefone nem email válido
        return ['sucesso' => false, 'mensagem' => 'Usuário não possui telefone nem email cadastrado'];
    }
}

/**
 * Gerar mensagens de notificação baseadas na decisão da justificativa
 */
function gerarMensagemNotificacaoJustificativa($decisao, $dados_justificativa, $nome_usuario) {
    $mensagens = [];
    
    $data_falta = $dados_justificativa['data_falta'] ?? date('d/m/Y');
    $motivo = $dados_justificativa['motivo'] ?? 'Não informado';
    $observacoes = $dados_justificativa['observacoes'] ?? '';
    $observacoes_admin = $dados_justificativa['observacoes_admin'] ?? '';
    $admin_nome = $dados_justificativa['admin_nome'] ?? 'Administrador';
    
    if ($decisao === 'aprovada') {
        $mensagens['assunto'] = '✅ Justificativa de Culto Aprovada';
        $mensagens['whatsapp'] = "✅ *JUSTIFICATIVA APROVADA*\n\n";
        $mensagens['whatsapp'] .= "Olá *{$nome_usuario}*,\n\n";
        $mensagens['whatsapp'] .= "Sua justificativa de falta no culto foi *aprovada*!\n\n";
        $mensagens['whatsapp'] .= "📅 *Data da falta:* {$data_falta}\n";
        $mensagens['whatsapp'] .= "📝 *Motivo:* {$motivo}\n";
        if (!empty($observacoes)) {
            $mensagens['whatsapp'] .= "💬 *Sua observação:* {$observacoes}\n";
        }
        if (!empty($observacoes_admin)) {
            $mensagens['whatsapp'] .= "👨‍💼 *Observação do administrador:* {$observacoes_admin}\n";
        }
        $mensagens['whatsapp'] .= "👨‍💼 *Aprovado por:* {$admin_nome}\n";
        $mensagens['whatsapp'] .= "\nSua presença foi marcada como presente.\n\n";
        $mensagens['whatsapp'] .= "Obrigado por utilizar nosso sistema!\n\n";
        $mensagens['whatsapp'] .= "🤖 *Sistema de Presença AOM*";
        
        $mensagens['email_html'] = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #198754 0%, #20c997 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                <h2 style='margin: 0;'>✅ Justificativa Aprovada!</h2>
            </div>
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;'>
                <p style='font-size: 16px; color: #333;'>Olá <strong>{$nome_usuario}</strong>,</p>
                <p style='font-size: 16px; color: #333;'>Sua justificativa de falta no culto foi <strong>aprovada</strong>!</p>
                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                    <p style='margin: 10px 0;'><strong>📅 Data da falta:</strong> {$data_falta}</p>
                    <p style='margin: 10px 0;'><strong>📝 Motivo:</strong> {$motivo}</p>";
        if (!empty($observacoes)) {
            $mensagens['email_html'] .= "<p style='margin: 10px 0;'><strong>💬 Sua observação:</strong> {$observacoes}</p>";
        }
        if (!empty($observacoes_admin)) {
            $mensagens['email_html'] .= "<p style='margin: 10px 0;'><strong>👨‍💼 Observação do administrador:</strong> {$observacoes_admin}</p>";
        }
        $mensagens['email_html'] .= "
                    <p style='margin: 10px 0;'><strong>👨‍💼 Aprovado por:</strong> {$admin_nome}</p>
                </div>
                <p style='font-size: 14px; color: #333; background: #d1e7dd; padding: 15px; border-radius: 8px; border-left: 4px solid #198754;'>
                    <strong>✓</strong> Sua presença foi marcada como presente.
                </p>
                <p style='font-size: 14px; color: #666; text-align: center; margin-top: 30px;'>
                    Obrigado por utilizar nosso sistema!<br>
                    <strong>Sistema de Presença AOM</strong>
                </p>
            </div>
        </div>";
        
        $mensagens['email_texto'] = "Olá {$nome_usuario},\n\nSua justificativa de falta no culto foi aprovada!\n\nData da falta: {$data_falta}\nMotivo: {$motivo}\n";
        if (!empty($observacoes)) {
            $mensagens['email_texto'] .= "Sua observação: {$observacoes}\n";
        }
        if (!empty($observacoes_admin)) {
            $mensagens['email_texto'] .= "Observação do administrador: {$observacoes_admin}\n";
        }
        $mensagens['email_texto'] .= "Aprovado por: {$admin_nome}\n\nSua presença foi marcada como presente.\n\nObrigado!\nSistema de Presença AOM";
        
    } else { // rejeitada
        $mensagens['assunto'] = '❌ Justificativa de Culto Rejeitada';
        $mensagens['whatsapp'] = "❌ *JUSTIFICATIVA REJEITADA*\n\n";
        $mensagens['whatsapp'] .= "Olá *{$nome_usuario}*,\n\n";
        $mensagens['whatsapp'] .= "Infelizmente sua justificativa de falta no culto foi *rejeitada*.\n\n";
        $mensagens['whatsapp'] .= "📅 *Data da falta:* {$data_falta}\n";
        $mensagens['whatsapp'] .= "📝 *Motivo informado:* {$motivo}\n";
        if (!empty($observacoes)) {
            $mensagens['whatsapp'] .= "💬 *Sua observação:* {$observacoes}\n";
        }
        if (!empty($observacoes_admin)) {
            $mensagens['whatsapp'] .= "👨‍💼 *Observação do administrador:* {$observacoes_admin}\n";
        }
        $mensagens['whatsapp'] .= "👨‍💼 *Rejeitada por:* {$admin_nome}\n";
        $mensagens['whatsapp'] .= "\nEm caso de dúvidas, entre em contato com a administração.\n\n";
        $mensagens['whatsapp'] .= "Obrigado por utilizar nosso sistema!\n\n";
        $mensagens['whatsapp'] .= "🤖 *Sistema de Presença AOM*";
        
        $mensagens['email_html'] = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                <h2 style='margin: 0;'>❌ Justificativa Rejeitada</h2>
            </div>
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;'>
                <p style='font-size: 16px; color: #333;'>Olá <strong>{$nome_usuario}</strong>,</p>
                <p style='font-size: 16px; color: #333;'>Infelizmente sua justificativa de falta no culto foi <strong>rejeitada</strong>.</p>
                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                    <p style='margin: 10px 0;'><strong>📅 Data da falta:</strong> {$data_falta}</p>
                    <p style='margin: 10px 0;'><strong>📝 Motivo informado:</strong> {$motivo}</p>";
        if (!empty($observacoes)) {
            $mensagens['email_html'] .= "<p style='margin: 10px 0;'><strong>💬 Sua observação:</strong> {$observacoes}</p>";
        }
        if (!empty($observacoes_admin)) {
            $mensagens['email_html'] .= "<p style='margin: 10px 0;'><strong>👨‍💼 Observação do administrador:</strong> {$observacoes_admin}</p>";
        }
        $mensagens['email_html'] .= "
                    <p style='margin: 10px 0;'><strong>👨‍💼 Rejeitada por:</strong> {$admin_nome}</p>
                </div>
                <p style='font-size: 14px; color: #333; background: #f8d7da; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545;'>
                    <strong>⚠</strong> Em caso de dúvidas, entre em contato com a administração.
                </p>
                <p style='font-size: 14px; color: #666; text-align: center; margin-top: 30px;'>
                    Obrigado por utilizar nosso sistema!<br>
                    <strong>Sistema de Presença AOM</strong>
                </p>
            </div>
        </div>";
        
        $mensagens['email_texto'] = "Olá {$nome_usuario},\n\nInfelizmente sua justificativa de falta no culto foi rejeitada.\n\nData da falta: {$data_falta}\nMotivo informado: {$motivo}\n";
        if (!empty($observacoes)) {
            $mensagens['email_texto'] .= "Sua observação: {$observacoes}\n";
        }
        if (!empty($observacoes_admin)) {
            $mensagens['email_texto'] .= "Observação do administrador: {$observacoes_admin}\n";
        }
        $mensagens['email_texto'] .= "Rejeitada por: {$admin_nome}\n\nEm caso de dúvidas, entre em contato com a administração.\n\nObrigado!\nSistema de Presença AOM";
    }
    
    return $mensagens;
}

?>

