<?php
/**
 * API para testar notificação para um usuário específico
 */
header('Content-Type: application/json; charset=UTF-8');
include_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../core/services/WhatsAppService.php';
require_once __DIR__ . '/../../core/services/NotificacaoService.php';

// Passar conexão para serviços
if (isset($conn) && $conn instanceof mysqli) {
    $GLOBALS['db_conn'] = $conn;
}

$response = ['status' => 'erro', 'mensagem' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = isset($_POST['usuario_id']) ? intval($_POST['usuario_id']) : 0;
    
    if ($usuario_id) {
        try {
            $data_hoje = date('Y-m-d');
            
            // Verificar se usuário tem reserva para hoje
            $sql_reserva = "SELECT id FROM reservas_almoco WHERE id_usuario = ? AND data = ?";
            $stmt = $conn->prepare($sql_reserva);
            $stmt->bind_param("is", $usuario_id, $data_hoje);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            
            if ($result->num_rows > 0) {
                $response['mensagem'] = 'Usuário já possui reserva para hoje';
                echo json_encode($response);
                exit;
            }
            
            // Buscar dados do usuário
            $sql_usuario = "SELECT nome, email, telefone FROM usuarios WHERE id = ? AND ativo = 1";
            $stmt = $conn->prepare($sql_usuario);
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            
            if ($result->num_rows === 0) {
                $response['mensagem'] = 'Usuário não encontrado ou inativo';
                echo json_encode($response);
                exit;
            }
            
            $usuario = $result->fetch_assoc();
            
            // Buscar configurações
            $config = [];
            $stmt = $conn->prepare("SELECT chave, valor FROM configuracoes WHERE chave IN ('template_email_notificacao', 'hora_limite', 'assunto_email_notificacao', 'smtp_email', 'port_email', 'senha_email', 'email_notificacoes', 'nome_remetente_email')");
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            
            while ($row = $result->fetch_assoc()) {
                $config[$row['chave']] = $row['valor'];
            }
            
            $horario_limite = $config['hora_limite'] ?? '13:00';
            $template = $config['template_email_notificacao'] ?? 'Olá {nome}, você ainda não fez sua reserva de almoço para hoje. Horário limite: {horario_limite}';
            
            // Garantir UTF-8
            $template = mb_convert_encoding($template, 'UTF-8', 'auto');
            
            // Preparar mensagem
            $mensagem = str_replace(
                ['{nome}', '{horario_limite}'],
                [$usuario['nome'], $horario_limite],
                $template
            );
            
            // Garantir UTF-8 na mensagem
            $mensagem = mb_convert_encoding($mensagem, 'UTF-8', 'auto');
            
            $enviado = false;
            $metodo = '';
            
            // Tentar WhatsApp primeiro (se tiver telefone)
            if (!empty($usuario['telefone'])) {
                $resultado = WhatsAppService::enviarMensagem($usuario['telefone'], $mensagem, [
                    'log_callback' => function($msg) {
                        error_log("WhatsApp Test: $msg");
                    },
                    'usuario_id' => $usuario_id,
                    'nome_destinatario' => $usuario['nome'],
                    'tipo_mensagem' => 'teste_notificacao',
                    'tipo_notificacao' => 'teste_notificacao'
                ]);
                if ($resultado['sucesso']) {
                    $enviado = true;
                    $metodo = 'WhatsApp';
                }
            }
            
            // Se não conseguiu WhatsApp ou não tem telefone, tentar email
            if (!$enviado && !empty($usuario['email'])) {
                $assunto = $config['assunto_email_notificacao'] ?? 'Lembrete: Faça sua reserva de almoço';
                $enviado = enviarEmail($usuario['email'], $usuario['nome'], $mensagem, $config);
                
                // Gravar notificação de email no histórico
                try {
                    $erro = $enviado ? null : 'Falha ao enviar email';
                    NotificacaoService::gravarEmail(
                        $usuario['email'],
                        $assunto,
                        $mensagem,
                        $enviado,
                        $erro,
                        $usuario_id,
                        $usuario['nome'],
                        'teste_notificacao'
                    );
                } catch (Exception $e) {
                    error_log("Erro ao gravar notificação de email: " . $e->getMessage());
                }
                
                if ($enviado) {
                    $metodo = 'Email';
                }
            }
            
            if ($enviado) {
                $response['status'] = 'sucesso';
                $response['mensagem'] = "Notificação enviada via $metodo para {$usuario['nome']}";
            } else {
                $response['mensagem'] = 'Falha ao enviar notificação (usuário sem telefone ou email válido)';
            }
            
        } catch (Exception $e) {
            $response['mensagem'] = 'Erro ao enviar notificação: ' . $e->getMessage();
        }
    } else {
        $response['mensagem'] = 'ID do usuário inválido';
    }
} else {
    $response['mensagem'] = 'Método de requisição inválido';
}

echo json_encode($response);

/**
 * Enviar email usando PHPMailer
 */
function enviarEmail($email, $nome, $mensagem, $config) {
    try {
        require_once __DIR__ . '/../../vendor/autoload.php';
        
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
        error_log("Erro ao enviar email: " . $e->getMessage());
        return false;
    }
}
?>

