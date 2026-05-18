<?php
header('Content-Type: application/json; charset=UTF-8');
require_once '../conexao.php';

$email = isset($_POST['email']) ? trim($_POST['email']) : '';

if ($email === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Digite seu e-mail.']);
    exit;
}

// Verificar se o e-mail existe e está ativo
$stmt = $conn->prepare("SELECT id, nome, email FROM usuarios WHERE email = ? AND ativo = 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'E-mail não encontrado ou usuário inativo.']);
    exit;
}

$usuario = $result->fetch_assoc();
$stmt->close();

// Gerar token único
$token = bin2hex(random_bytes(32));
$expiracao = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Criar tabela de tokens se não existir
$conn->query("CREATE TABLE IF NOT EXISTS tokens_senha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expiracao DATETIME NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
)");

// Deletar tokens antigos do usuário
$conn->query("DELETE FROM tokens_senha WHERE id_usuario = {$usuario['id']}");

// Inserir novo token
$stmt = $conn->prepare("INSERT INTO tokens_senha (id_usuario, token, expiracao) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $usuario['id'], $token, $expiracao);

if (!$stmt->execute()) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao gerar token de recuperação.']);
    exit;
}

$stmt->close();

// Buscar configurações de e-mail
$config = [];
$result = $conn->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('smtp_email', 'port_email', 'email_notificacoes', 'senha_email', 'nome_remetente_email')");
while ($row = $result->fetch_assoc()) {
    $config[$row['chave']] = $row['valor'];
}

// Configurar PHPMailer
require_once '../../vendor/autoload.php';
require_once __DIR__ . '/../../core/services/NotificacaoService.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Configurações do servidor
    $mail->isSMTP();
    $mail->Host = $config['smtp_email'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['email_notificacoes'];
    $mail->Password = $config['senha_email'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)$config['port_email'];
    $mail->CharSet = 'UTF-8';
    $mail->SMTPDebug = 0; // Desabilitar debug em produção

    // Remetente e destinatário
    $mail->setFrom($config['email_notificacoes'], $config['nome_remetente_email'] ?? 'Sistema de Presença AOM');
    $mail->addAddress($email, $usuario['nome']);

    // Conteúdo do e-mail
    $mail->isHTML(true);
    $mail->Subject = 'Recuperação de Senha - Sistema Presença AOM';
    
    $linkRecuperacao = "https://presenca.aom.org.br/redefinir_senha.php?token=" . $token;
    
    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
            .button { display: inline-block; background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Recuperação de Senha</h2>
            </div>
            <div class='content'>
                <p>Olá <strong>{$usuario['nome']}</strong>,</p>
                
                <p>Você solicitou a recuperação de senha para sua conta no Sistema Presença AOM.</p>
                
                <p>Clique no botão abaixo para redefinir sua senha:</p>
                
                <div style='text-align: center;'>
                    <a href='{$linkRecuperacao}' class='button'>Redefinir Senha</a>
                </div>
                
                <p><strong>Importante:</strong></p>
                <ul>
                    <li>Este link expira em 1 hora</li>
                    <li>Se você não solicitou esta recuperação, ignore este e-mail</li>
                    <li>Por segurança, não compartilhe este link</li>
                </ul>
                
                <p>Se o botão não funcionar, copie e cole o link abaixo no seu navegador:</p>
                <p style='word-break: break-all; background: #e9ecef; padding: 10px; border-radius: 4px; font-family: monospace;'>{$linkRecuperacao}</p>
            </div>
            <div class='footer'>
                <p>Sistema Presença AOM - Recuperação de Senha</p>
                <p>Este é um e-mail automático, não responda.</p>
            </div>
        </div>
    </body>
    </html>";

    $mail->send();
    
    // Gravar notificação de email no histórico
    try {
        $assunto = 'Recuperação de Senha - Sistema Presença AOM';
        $mensagem_texto = strip_tags($mail->Body);
        NotificacaoService::gravarEmail(
            $email,
            $assunto,
            $mensagem_texto,
            true,
            null,
            $usuario['id'],
            $usuario['nome'],
            'recuperacao_senha'
        );
    } catch (Exception $e_gravacao) {
        error_log("Erro ao gravar notificação de recuperação de senha: " . $e_gravacao->getMessage());
    }
    
    echo json_encode(['status' => 'ok', 'mensagem' => 'E-mail de recuperação enviado com sucesso! Verifique sua caixa de entrada.']);
    
} catch (Exception $e) {
    // Se falhar ao enviar e-mail, deletar o token
    $conn->query("DELETE FROM tokens_senha WHERE token = '{$token}'");
    
    // Gravar notificação de falha no histórico
    try {
        $assunto = 'Recuperação de Senha - Sistema Presença AOM';
        $mensagem_texto = "Falha ao enviar email de recuperação de senha";
        NotificacaoService::gravarEmail(
            $email,
            $assunto,
            $mensagem_texto,
            false,
            $e->getMessage(),
            $usuario['id'],
            $usuario['nome'],
            'recuperacao_senha'
        );
    } catch (Exception $e_gravacao) {
        error_log("Erro ao gravar notificação de falha de recuperação de senha: " . $e_gravacao->getMessage());
    }
    
    // Log do erro para debug
    error_log("Erro PHPMailer: " . $e->getMessage());
    
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao enviar e-mail de recuperação. Verifique as configurações de e-mail.']);
}

$conn->close();
?>