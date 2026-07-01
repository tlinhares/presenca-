<?php
/**
 * API Mobile — Esqueci minha senha
 *
 * Endpoint: POST /api/mobile/auth/esqueci_senha.php
 * Content-Type: application/json
 *
 * Body:
 *   { "email": "usuario@exemplo.com" }
 *
 * Comportamento:
 *   Espelha 1:1 o fluxo do site (api/auth/recuperar_senha.php):
 *     - valida usuario ativo
 *     - gera token 64 chars com validade de 1h
 *     - salva em `tokens_senha`
 *     - envia e-mail via PHPMailer com link para /redefinir_senha.php
 *
 *   O usuário abre o e-mail, clica no link e redefine a senha na página web
 *   (mesma que o botão do site usa). Depois volta pro app pra logar com a
 *   nova senha. Isso mantém uma única superfície de reset, sem duplicar UI
 *   nem endpoints, e sem precisar armazenar códigos OTP.
 *
 * Response (sucesso):
 *   { success: true, message: "E-mail de recuperação enviado ...", data: { email } }
 * Response (erro):
 *   { success: false, message: "..." }  com HTTP code apropriado
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../api/conexao.php';
require_once __DIR__ . '/../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(MobileResponse::error('Método não permitido', 405));
    exit;
}

try {
    $input = file_get_contents('php://input');
    $data  = json_decode($input, true);
    if (!$data || !is_array($data)) {
        echo json_encode(MobileResponse::error('JSON inválido', 400));
        exit;
    }

    $email = isset($data['email']) ? trim((string) $data['email']) : '';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(MobileResponse::error('Informe um e-mail válido.', 400));
        exit;
    }

    // Localiza usuário ativo (mesma consulta do fluxo web)
    $stmt = $conn->prepare("SELECT id, nome, email FROM usuarios WHERE email = ? AND ativo = 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($r->num_rows === 0) {
        $stmt->close();
        echo json_encode(MobileResponse::error('E-mail não encontrado ou usuário inativo.', 404));
        exit;
    }
    $usuario = $r->fetch_assoc();
    $stmt->close();

    // Gera token e persiste (garante tabela — mesmo idempotente do fluxo web)
    $token     = bin2hex(random_bytes(32));
    $expiracao = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $conn->query("CREATE TABLE IF NOT EXISTS tokens_senha (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expiracao DATETIME NOT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
    )");

    // Zera tokens antigos do mesmo usuário — impede link de e-mail anterior
    // continuar valendo depois de nova solicitação.
    $stmt = $conn->prepare("DELETE FROM tokens_senha WHERE id_usuario = ?");
    $stmt->bind_param('i', $usuario['id']);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO tokens_senha (id_usuario, token, expiracao) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $usuario['id'], $token, $expiracao);
    if (!$stmt->execute()) {
        $stmt->close();
        echo json_encode(MobileResponse::serverError('Erro ao gerar token de recuperação.'));
        exit;
    }
    $stmt->close();

    // Configurações SMTP no banco
    $config = [];
    $r = $conn->query("SELECT chave, valor FROM configuracoes
                        WHERE chave IN ('smtp_email','port_email','email_notificacoes','senha_email','nome_remetente_email')");
    while ($row = $r->fetch_assoc()) $config[$row['chave']] = $row['valor'];

    require_once __DIR__ . '/../../../vendor/autoload.php';
    require_once __DIR__ . '/../../../core/services/NotificacaoService.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $config['smtp_email']         ?? '';
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['email_notificacoes'] ?? '';
        $mail->Password   = $config['senha_email']        ?? '';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) ($config['port_email'] ?? 587);
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($config['email_notificacoes'] ?? '',
                       $config['nome_remetente_email'] ?? 'Sistema de Presença AOM');
        $mail->addAddress($email, $usuario['nome']);

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
                    <p>Você solicitou a recuperação de senha para sua conta no aplicativo Intranet AOM.</p>
                    <p>Clique no botão abaixo para redefinir sua senha:</p>
                    <div style='text-align: center;'>
                        <a href='{$linkRecuperacao}' class='button'>Redefinir Senha</a>
                    </div>
                    <p><strong>Importante:</strong></p>
                    <ul>
                        <li>Este link expira em 1 hora</li>
                        <li>Se você não solicitou esta recuperação, ignore este e-mail</li>
                        <li>Por segurança, não compartilhe este link</li>
                        <li>Depois de redefinir, volte ao app para fazer login</li>
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

        try {
            \NotificacaoService::gravarEmail(
                $email,
                'Recuperação de Senha - Sistema Presença AOM',
                strip_tags($mail->Body),
                true,
                null,
                $usuario['id'],
                $usuario['nome'],
                'recuperacao_senha_mobile'
            );
        } catch (\Exception $e_gravacao) {
            error_log('Erro ao gravar notificação de recuperação de senha (mobile): ' . $e_gravacao->getMessage());
        }

        echo json_encode(MobileResponse::success(
            ['email' => $email],
            'E-mail de recuperação enviado com sucesso. Verifique sua caixa de entrada.'
        ));

    } catch (\Exception $e_mail) {
        // E-mail falhou — apaga o token pra não deixar link válido órfão
        $stmt = $conn->prepare("DELETE FROM tokens_senha WHERE token = ?");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->close();

        try {
            \NotificacaoService::gravarEmail(
                $email,
                'Recuperação de Senha - Sistema Presença AOM',
                'Falha ao enviar email de recuperação de senha (mobile)',
                false,
                $e_mail->getMessage(),
                $usuario['id'],
                $usuario['nome'],
                'recuperacao_senha_mobile'
            );
        } catch (\Exception $e_g2) {
            error_log('Erro ao gravar notificação de falha (mobile): ' . $e_g2->getMessage());
        }

        error_log('Erro PHPMailer (esqueci_senha mobile): ' . $e_mail->getMessage());
        echo json_encode(MobileResponse::serverError(
            'Erro ao enviar e-mail de recuperação. Tente novamente em alguns instantes.'
        ));
    }

} catch (\Throwable $e) {
    error_log('Erro em esqueci_senha mobile: ' . $e->getMessage());
    echo json_encode(MobileResponse::serverError('Erro interno do servidor.'));
}

$conn->close();
