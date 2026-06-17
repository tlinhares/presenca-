<?php
/**
 * Função para enviar notificações de reserva
 * Verifica se o usuário tem notificações habilitadas e envia via WhatsApp ou Email
 */

if (!defined('NOTIFICACAO_LOADED')) {
    define('NOTIFICACAO_LOADED', true);
    
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../includes/phpmailer/src/Exception.php';
require_once __DIR__ . '/../../includes/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../includes/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../../core/services/WhatsAppService.php';
require_once __DIR__ . '/../../core/services/NotificacaoService.php';
require_once __DIR__ . '/../../core/services/PushNotificationService.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Funções antigas removidas - usando WhatsAppService agora
// Todas as funções de envio foram migradas para WhatsAppService

/**
 * Enviar Email
 */
function enviarEmail($email, $nome, $assunto, $corpo_html, $corpo_texto, $conn) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['sucesso' => false, 'mensagem' => 'Email inválido'];
    }
    
    // Buscar configurações de email
    $config = [];
    $campos = ['email_notificacoes', 'smtp_email', 'imap_email', 'port_email', 'senha_email', 'nome_remetente_email'];
    $campos_escaped = array_map(function($campo) use ($conn) {
        return "'" . $conn->real_escape_string($campo) . "'";
    }, $campos);
    $sql = "SELECT chave, valor FROM configuracoes WHERE chave IN (" . implode(',', $campos_escaped) . ")";
    $result = $conn->query($sql);
    
    while ($row = $result->fetch_assoc()) {
        $config[$row['chave']] = $row['valor'];
    }
    
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['smtp_email'] ?? '';
        $mail->SMTPAuth = true;
        $mail->Username = $config['email_notificacoes'] ?? '';
        $mail->Password = $config['senha_email'] ?? '';
        $mail->SMTPSecure = 'tls';
        $mail->Port = (int)($config['port_email'] ?? 587);
        $mail->CharSet = 'UTF-8';
        
        $mail->setFrom($config['email_notificacoes'] ?? '', $config['nome_remetente_email'] ?? 'Sistema de Presença AOM');
        $mail->addAddress($email, $nome);
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body = $corpo_html;
        $mail->AltBody = $corpo_texto;
        
        $mail->send();
        return ['sucesso' => true, 'mensagem' => 'Email enviado com sucesso'];
        
    } catch (Exception $e) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao enviar email: ' . $mail->ErrorInfo];
    }
}

/**
 * Verificar se usuário quer receber notificação do tipo especificado
 * 
 * IMPORTANTE: Se não há configuração na tabela, assume que o usuário QUER receber (padrão permitir)
 * Isso garante que usuários sem configuração explícita ainda recebam notificações
 */
function usuarioQuerNotificacao($usuario_id, $tipo_notificacao, $conn) {
    // Verificar se tabela existe
    $tabela_existe = $conn->query("SHOW TABLES LIKE 'notificacoes_usuario'")->num_rows > 0;
    
    if (!$tabela_existe) {
        // Tabela não existe, permitir notificação por padrão
        return true;
    }
    
    $stmt = $conn->prepare("SELECT {$tipo_notificacao} FROM notificacoes_usuario WHERE id_usuario = ?");
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
        // Se tem configuração, respeitar a escolha do usuário
        return (bool)$row[$tipo_notificacao];
    }

    $stmt->close();
    // Se não tem configuração, PERMITIR notificação por padrão (mudança importante!)
    return true;
}

/**
 * Retorna os 3 canais escolhidos pelo usuário. Default = todos ligados
 * (backward-compat: usuários sem linha em notificacoes_usuario continuam
 * recebendo por todos os canais habilitados globalmente).
 *
 * @return array{canal_email:bool, canal_whatsapp:bool, canal_push:bool}
 */
function obterCanaisNotificacao(int $usuario_id, mysqli $conn): array {
    $default = ['canal_email' => true, 'canal_whatsapp' => true, 'canal_push' => true];
    if (!$conn->query("SHOW COLUMNS FROM notificacoes_usuario LIKE 'canal_email'")->num_rows) {
        return $default; // migração ainda não aplicada
    }
    $stmt = $conn->prepare("SELECT canal_email, canal_whatsapp, canal_push FROM notificacoes_usuario WHERE id_usuario = ?");
    if (!$stmt) return $default;
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return $default;
    return [
        'canal_email'    => (bool) ($row['canal_email']    ?? 1),
        'canal_whatsapp' => (bool) ($row['canal_whatsapp'] ?? 1),
        'canal_push'     => (bool) ($row['canal_push']     ?? 1),
    ];
}

/**
 * Enviar notificação de reserva
 */
function enviarNotificacaoReserva($usuario_id, $tipo_reserva, $dados_reserva, $conn) {
    // Tipo de reserva: 'propria', 'adicional', 'multipla', 'cancelada'
    // Mapear para campo da tabela
    $campos_notificacao = [
        'propria' => 'notificar_reserva_propria',
        'adicional' => 'notificar_reserva_adicional',
        'multipla' => 'notificar_reserva_multipla',
        'cancelada' => 'notificar_reserva_cancelada'
    ];
    
    $campo_notificacao = $campos_notificacao[$tipo_reserva] ?? null;
    if (!$campo_notificacao) {
        return ['sucesso' => false, 'mensagem' => 'Tipo de reserva inválido'];
    }
    
    // Verificar se usuário quer receber notificação
    if (!usuarioQuerNotificacao($usuario_id, $campo_notificacao, $conn)) {
        return ['sucesso' => false, 'mensagem' => 'Usuário não habilitou notificações deste tipo'];
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
    $mensagens = gerarMensagemNotificacao($tipo_reserva, $dados_reserva, $usuario['nome']);

    // Canais escolhidos pelo usuário (default tudo on para retrocompat).
    $canais = obterCanaisNotificacao((int) $usuario_id, $conn);

    // Push (em paralelo aos demais canais — silencioso se canal desligado,
    // se push não estiver configurado ou se o usuário não tiver dispositivos).
    if ($canais['canal_push']) {
        PushNotificationService::enviarSilencioso(
            $conn,
            (int) $usuario_id,
            $mensagens['assunto'],
            PushNotificationService::corpoCurto($mensagens['email_texto'] ?? ''),
            ['tipo' => 'reserva_' . $tipo_reserva]
        );
    }

    // Verificar se tem telefone válido usando WhatsAppService
    $telefone_normalizado = WhatsAppService::normalizarTelefone($usuario['telefone']);
    $tem_telefone = !empty($telefone_normalizado) && $canais['canal_whatsapp'];
    $tem_email    = !empty($usuario['email']) && filter_var($usuario['email'], FILTER_VALIDATE_EMAIL) && $canais['canal_email'];
    
    // LÓGICA: Se tem telefone, envia APENAS por WhatsApp
    // Se não tem telefone mas tem email, envia APENAS por email
    // NUNCA envia por ambos
    
    if ($tem_telefone) {
        // Tem telefone: enviar APENAS por WhatsApp usando WhatsAppService
        // Passar dados para gravar no histórico
        $resultado = WhatsAppService::enviarMensagem($usuario['telefone'], $mensagens['whatsapp'], [
            'usuario_id' => $usuario_id,
            'nome_destinatario' => $usuario['nome'],
            'tipo_mensagem' => $tipo_reserva,
            'tipo_notificacao' => $tipo_reserva
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
                        $tipo_reserva
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
                $tipo_reserva
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
 * Gerar mensagens de notificação baseadas no tipo de reserva
 */
function gerarMensagemNotificacao($tipo_reserva, $dados_reserva, $nome_usuario) {
    $mensagens = [];
    
    switch ($tipo_reserva) {
        case 'propria':
            $data = $dados_reserva['data'] ?? date('d/m/Y');
            $horario = $dados_reserva['horario'] ?? date('H:i');
            $valor = isset($dados_reserva['valor']) ? number_format($dados_reserva['valor'], 2, ',', '.') : '0,00';
            $fora_horario = $dados_reserva['fora_horario'] ?? false;
            
            $mensagens['assunto'] = '✅ Reserva de Almoço Confirmada';
            $mensagens['whatsapp'] = "🍽️ *RESERVA CONFIRMADA*\n\n";
            $mensagens['whatsapp'] .= "Olá *{$nome_usuario}*,\n\n";
            $mensagens['whatsapp'] .= "Sua reserva de almoço foi confirmada com sucesso!\n\n";
            $mensagens['whatsapp'] .= "📅 *Data:* {$data}\n";
            $mensagens['whatsapp'] .= "🕐 *Horário:* {$horario}\n";
            $mensagens['whatsapp'] .= "💰 *Valor:* R$ {$valor}\n";
            if ($fora_horario) {
                $mensagens['whatsapp'] .= "⚠️ *Status:* Reserva fora do horário\n";
            }
            $mensagens['whatsapp'] .= "\nObrigado por utilizar nosso sistema!\n\n";
            $mensagens['whatsapp'] .= "🤖 *Sistema Refeições AOM*";
            
            $mensagens['email_html'] = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                    <h2 style='margin: 0;'>🍽️ Reserva Confirmada!</h2>
                </div>
                <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;'>
                    <p style='font-size: 16px; color: #333;'>Olá <strong>{$nome_usuario}</strong>,</p>
                    <p style='font-size: 16px; color: #333;'>Sua reserva de almoço foi confirmada com sucesso!</p>
                    <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                        <p style='margin: 10px 0;'><strong>📅 Data:</strong> {$data}</p>
                        <p style='margin: 10px 0;'><strong>🕐 Horário:</strong> {$horario}</p>
                        <p style='margin: 10px 0;'><strong>💰 Valor:</strong> R$ {$valor}</p>";
            if ($fora_horario) {
                $mensagens['email_html'] .= "<p style='margin: 10px 0;'><strong>⚠️ Status:</strong> Reserva fora do horário</p>";
            }
            $mensagens['email_html'] .= "
                    </div>
                    <p style='font-size: 14px; color: #666; text-align: center; margin-top: 30px;'>
                        Obrigado por utilizar nosso sistema!<br>
                        <strong>Sistema Refeições AOM</strong>
                    </p>
                </div>
            </div>";
            
            $mensagens['email_texto'] = "Olá {$nome_usuario},\n\nSua reserva de almoço foi confirmada!\n\nData: {$data}\nHorário: {$horario}\nValor: R$ {$valor}\n\nObrigado!\nSistema Refeições AOM";
            break;
            
        case 'adicional':
            $data = $dados_reserva['data'] ?? date('d/m/Y');
            $horario = $dados_reserva['horario'] ?? date('H:i');
            $dependente_nome = $dados_reserva['dependente_nome'] ?? 'Dependente';
            $tipo = $dados_reserva['tipo'] ?? 'presencial';
            $quantidade = $dados_reserva['quantidade'] ?? 1;
            $valor_total = isset($dados_reserva['valor_total']) ? number_format($dados_reserva['valor_total'], 2, ',', '.') : '0,00';
            $fora_horario = $dados_reserva['fora_horario'] ?? false;
            
            $tipo_texto = $tipo === 'presencial' ? 'Presencial' : 'Marmitex';
            
            $mensagens['assunto'] = '✅ Reserva Adicional Confirmada';
            $mensagens['whatsapp'] = "🍽️ *RESERVA ADICIONAL CONFIRMADA*\n\n";
            $mensagens['whatsapp'] .= "Olá *{$nome_usuario}*,\n\n";
            $mensagens['whatsapp'] .= "Sua reserva adicional foi confirmada!\n\n";
            $mensagens['whatsapp'] .= "👤 *Dependente:* {$dependente_nome}\n";
            $mensagens['whatsapp'] .= "📅 *Data:* {$data}\n";
            $mensagens['whatsapp'] .= "🕐 *Horário:* {$horario}\n";
            $mensagens['whatsapp'] .= "📦 *Tipo:* {$tipo_texto}\n";
            $mensagens['whatsapp'] .= "🔢 *Quantidade:* {$quantidade}\n";
            $mensagens['whatsapp'] .= "💰 *Valor Total:* R$ {$valor_total}\n";
            if ($fora_horario) {
                $mensagens['whatsapp'] .= "⚠️ *Status:* Reserva fora do horário\n";
            }
            $mensagens['whatsapp'] .= "\nObrigado por utilizar nosso sistema!\n\n";
            $mensagens['whatsapp'] .= "🤖 *Sistema Refeições AOM*";
            
            $mensagens['email_html'] = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                    <h2 style='margin: 0;'>🍽️ Reserva Adicional Confirmada!</h2>
                </div>
                <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;'>
                    <p style='font-size: 16px; color: #333;'>Olá <strong>{$nome_usuario}</strong>,</p>
                    <p style='font-size: 16px; color: #333;'>Sua reserva adicional foi confirmada com sucesso!</p>
                    <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                        <p style='margin: 10px 0;'><strong>👤 Dependente:</strong> {$dependente_nome}</p>
                        <p style='margin: 10px 0;'><strong>📅 Data:</strong> {$data}</p>
                        <p style='margin: 10px 0;'><strong>🕐 Horário:</strong> {$horario}</p>
                        <p style='margin: 10px 0;'><strong>📦 Tipo:</strong> {$tipo_texto}</p>
                        <p style='margin: 10px 0;'><strong>🔢 Quantidade:</strong> {$quantidade}</p>
                        <p style='margin: 10px 0;'><strong>💰 Valor Total:</strong> R$ {$valor_total}</p>";
            if ($fora_horario) {
                $mensagens['email_html'] .= "<p style='margin: 10px 0;'><strong>⚠️ Status:</strong> Reserva fora do horário</p>";
            }
            $mensagens['email_html'] .= "
                    </div>
                    <p style='font-size: 14px; color: #666; text-align: center; margin-top: 30px;'>
                        Obrigado por utilizar nosso sistema!<br>
                        <strong>Sistema Refeições AOM</strong>
                    </p>
                </div>
            </div>";
            
            $mensagens['email_texto'] = "Olá {$nome_usuario},\n\nSua reserva adicional foi confirmada!\n\nDependente: {$dependente_nome}\nData: {$data}\nHorário: {$horario}\nTipo: {$tipo_texto}\nQuantidade: {$quantidade}\nValor Total: R$ {$valor_total}\n\nObrigado!\nSistema Refeições AOM";
            break;
            
        case 'multipla':
            $data_inicio = $dados_reserva['data_inicio'] ?? date('d/m/Y');
            $data_fim = $dados_reserva['data_fim'] ?? date('d/m/Y');
            $horario = $dados_reserva['horario'] ?? date('H:i');
            $total_reservas = $dados_reserva['total_reservas'] ?? 0;
            $tipo_texto = $dados_reserva['tipo'] ?? 'própria';
            $tipo_texto = $tipo_texto === 'propria' ? 'Própria' : 'Adicional';
            
            $mensagens['assunto'] = '✅ Reservas Múltiplas Confirmadas';
            $mensagens['whatsapp'] = "🍽️ *RESERVAS MÚLTIPLAS CONFIRMADAS*\n\n";
            $mensagens['whatsapp'] .= "Olá *{$nome_usuario}*,\n\n";
            $mensagens['whatsapp'] .= "Suas reservas foram confirmadas com sucesso!\n\n";
            $mensagens['whatsapp'] .= "📅 *Período:* {$data_inicio} a {$data_fim}\n";
            $mensagens['whatsapp'] .= "🕐 *Horário:* {$horario}\n";
            $mensagens['whatsapp'] .= "📦 *Tipo:* {$tipo_texto}\n";
            $mensagens['whatsapp'] .= "🔢 *Total de Reservas:* {$total_reservas}\n";
            $mensagens['whatsapp'] .= "\nObrigado por utilizar nosso sistema!\n\n";
            $mensagens['whatsapp'] .= "🤖 *Sistema Refeições AOM*";
            
            $mensagens['email_html'] = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                    <h2 style='margin: 0;'>🍽️ Reservas Múltiplas Confirmadas!</h2>
                </div>
                <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;'>
                    <p style='font-size: 16px; color: #333;'>Olá <strong>{$nome_usuario}</strong>,</p>
                    <p style='font-size: 16px; color: #333;'>Suas reservas foram confirmadas com sucesso!</p>
                    <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                        <p style='margin: 10px 0;'><strong>📅 Período:</strong> {$data_inicio} a {$data_fim}</p>
                        <p style='margin: 10px 0;'><strong>🕐 Horário:</strong> {$horario}</p>
                        <p style='margin: 10px 0;'><strong>📦 Tipo:</strong> {$tipo_texto}</p>
                        <p style='margin: 10px 0;'><strong>🔢 Total de Reservas:</strong> {$total_reservas}</p>
                    </div>
                    <p style='font-size: 14px; color: #666; text-align: center; margin-top: 30px;'>
                        Obrigado por utilizar nosso sistema!<br>
                        <strong>Sistema Refeições AOM</strong>
                    </p>
                </div>
            </div>";
            
            $mensagens['email_texto'] = "Olá {$nome_usuario},\n\nSuas reservas múltiplas foram confirmadas!\n\nPeríodo: {$data_inicio} a {$data_fim}\nHorário: {$horario}\nTipo: {$tipo_texto}\nTotal: {$total_reservas} reservas\n\nObrigado!\nSistema Refeições AOM";
            break;
            
        case 'cancelada':
            $data = $dados_reserva['data'] ?? date('d/m/Y');
            $horario = $dados_reserva['horario'] ?? date('H:i');
            $tipo_reserva = $dados_reserva['tipo_reserva'] ?? 'própria';
            $dependente_nome = $dados_reserva['dependente_nome'] ?? null;
            $excluida_por_admin = $dados_reserva['excluida_por_admin'] ?? false;
            $admin_nome = $dados_reserva['admin_nome'] ?? null;
            
            $mensagens['assunto'] = $excluida_por_admin ? '⚠️ Reserva Cancelada por Administrador' : '❌ Reserva Cancelada';
            
            if ($excluida_por_admin) {
                $mensagens['whatsapp'] = "⚠️ *RESERVA CANCELADA POR ADMINISTRADOR*\n\n";
                $mensagens['whatsapp'] .= "Olá *{$nome_usuario}*,\n\n";
                $mensagens['whatsapp'] .= "Informamos que sua reserva foi cancelada por um administrador do sistema.\n\n";
            } else {
                $mensagens['whatsapp'] = "❌ *RESERVA CANCELADA*\n\n";
                $mensagens['whatsapp'] .= "Olá *{$nome_usuario}*,\n\n";
                $mensagens['whatsapp'] .= "Sua reserva foi cancelada com sucesso!\n\n";
            }
            
            $mensagens['whatsapp'] .= "📅 *Data:* {$data}\n";
            $mensagens['whatsapp'] .= "🕐 *Horário:* {$horario}\n";
            if ($tipo_reserva === 'adicional' && $dependente_nome) {
                $mensagens['whatsapp'] .= "👤 *Dependente:* {$dependente_nome}\n";
                $mensagens['whatsapp'] .= "📦 *Tipo:* Reserva Adicional\n";
            } else {
                $mensagens['whatsapp'] .= "📦 *Tipo:* Reserva Própria\n";
            }
            
            if ($excluida_por_admin && $admin_nome) {
                $mensagens['whatsapp'] .= "👨‍💼 *Cancelada por:* {$admin_nome}\n";
            }
            
            $mensagens['whatsapp'] .= "\n";
            if ($excluida_por_admin) {
                $mensagens['whatsapp'] .= "Em caso de dúvidas, entre em contato com a administração.\n\n";
            }
            $mensagens['whatsapp'] .= "Obrigado por utilizar nosso sistema!\n\n";
            $mensagens['whatsapp'] .= "🤖 *Sistema Refeições AOM*";
            
            $tipo_texto = ($tipo_reserva === 'adicional' && $dependente_nome) ? "Reserva Adicional - {$dependente_nome}" : "Reserva Própria";
            
            $titulo_email = $excluida_por_admin ? '⚠️ Reserva Cancelada por Administrador' : '❌ Reserva Cancelada';
            $mensagem_email = $excluida_por_admin 
                ? 'Informamos que sua reserva foi cancelada por um administrador do sistema.'
                : 'Sua reserva foi cancelada com sucesso!';
            
            $mensagens['email_html'] = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                    <h2 style='margin: 0;'>{$titulo_email}</h2>
                </div>
                <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;'>
                    <p style='font-size: 16px; color: #333;'>Olá <strong>{$nome_usuario}</strong>,</p>
                    <p style='font-size: 16px; color: #333;'>{$mensagem_email}</p>
                    <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                        <p style='margin: 10px 0;'><strong>📅 Data:</strong> {$data}</p>
                        <p style='margin: 10px 0;'><strong>🕐 Horário:</strong> {$horario}</p>
                        <p style='margin: 10px 0;'><strong>📦 Tipo:</strong> {$tipo_texto}</p>";
            if ($tipo_reserva === 'adicional' && $dependente_nome) {
                $mensagens['email_html'] .= "<p style='margin: 10px 0;'><strong>👤 Dependente:</strong> {$dependente_nome}</p>";
            }
            if ($excluida_por_admin && $admin_nome) {
                $mensagens['email_html'] .= "<p style='margin: 10px 0;'><strong>👨‍💼 Cancelada por:</strong> {$admin_nome}</p>";
            }
            $mensagens['email_html'] .= "
                    </div>";
            if ($excluida_por_admin) {
                $mensagens['email_html'] .= "<p style='font-size: 14px; color: #666; text-align: center; margin-top: 20px;'>Em caso de dúvidas, entre em contato com a administração.</p>";
            }
            $mensagens['email_html'] .= "
                    <p style='font-size: 14px; color: #666; text-align: center; margin-top: 30px;'>
                        Obrigado por utilizar nosso sistema!<br>
                        <strong>Sistema Refeições AOM</strong>
                    </p>
                </div>
            </div>";
            
            $texto_email = "Olá {$nome_usuario},\n\n";
            $texto_email .= $excluida_por_admin 
                ? "Informamos que sua reserva foi cancelada por um administrador do sistema.\n\n"
                : "Sua reserva foi cancelada!\n\n";
            $texto_email .= "Data: {$data}\nHorário: {$horario}\nTipo: {$tipo_texto}\n";
            if ($excluida_por_admin && $admin_nome) {
                $texto_email .= "Cancelada por: {$admin_nome}\n";
            }
            $texto_email .= "\n";
            if ($excluida_por_admin) {
                $texto_email .= "Em caso de dúvidas, entre em contato com a administração.\n\n";
            }
            $texto_email .= "Obrigado!\nSistema Refeições AOM";
            
            $mensagens['email_texto'] = $texto_email;
            break;
            
        default:
            $mensagens['assunto'] = 'Notificação de Reserva';
            $mensagens['whatsapp'] = "Reserva confirmada!";
            $mensagens['email_html'] = "<p>Reserva confirmada!</p>";
            $mensagens['email_texto'] = "Reserva confirmada!";
    }
    
    return $mensagens;
}
?>

