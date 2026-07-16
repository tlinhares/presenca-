<?php
/**
 * DivulgacaoService — envio da divulgação do aplicativo (WhatsApp + e-mail).
 *
 * Usado pela tela /painel/divulgar_app.php (teste imediato) e pelo cron
 * cron/processar_divulgacao.php (fila em massa). Centraliza a renderização
 * dos placeholders e o envio por canal, gravando o histórico via
 * NotificacaoService.
 */
class DivulgacaoService
{
    /**
     * Substitui placeholders da mensagem: {nome}, {link_android}, {link_ios}.
     * {nome} usa só o primeiro nome.
     */
    public static function render(string $template, string $nome, string $linkAndroid, string $linkIos): string
    {
        $primeiroNome = trim(explode(' ', trim($nome))[0] ?? '');
        return strtr($template, [
            '{nome}'         => $primeiroNome !== '' ? $primeiroNome : 'colega',
            '{link_android}' => $linkAndroid !== '' ? $linkAndroid : '(link em breve)',
            '{link_ios}'     => $linkIos !== '' ? $linkIos : '(link em breve)',
        ]);
    }

    /**
     * Envia WhatsApp e grava histórico. Retorna ['sucesso' => bool, 'mensagem' => string].
     */
    public static function enviarWhatsapp(mysqli $conn, string $telefone, string $mensagem, int $usuarioId, string $nome): array
    {
        require_once __DIR__ . '/WhatsAppService.php';
        require_once __DIR__ . '/NotificacaoService.php';

        $r = WhatsAppService::enviarMensagem($telefone, $mensagem);
        $ok = !empty($r['sucesso']);

        try {
            NotificacaoService::gravarWhatsApp($telefone, $mensagem, $r, $usuarioId, $nome, 'divulgacao_app');
        } catch (Throwable $e) {
            error_log('DivulgacaoService: falha ao gravar histórico whatsapp: ' . $e->getMessage());
        }

        return ['sucesso' => $ok, 'mensagem' => $r['mensagem'] ?? ($ok ? 'enviado' : 'erro desconhecido')];
    }

    /**
     * Envia e-mail (HTML simples gerado da mensagem) e grava histórico.
     */
    public static function enviarEmail(mysqli $conn, string $email, string $assunto, string $mensagem, int $usuarioId, string $nome): array
    {
        require_once __DIR__ . '/NotificacaoService.php';
        require_once __DIR__ . '/../../vendor/autoload.php';

        $config = [];
        $res = $conn->query("SELECT chave, valor FROM configuracoes
                              WHERE chave IN ('smtp_email','port_email','email_notificacoes','senha_email','nome_remetente_email')");
        while ($row = $res->fetch_assoc()) $config[$row['chave']] = $row['valor'];

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

            $mail->setFrom($config['email_notificacoes'] ?? '', $config['nome_remetente_email'] ?? 'Intranet AOM');
            $mail->addAddress($email, $nome);
            $mail->isHTML(true);
            $mail->Subject = $assunto;

            // Mensagem em texto → HTML: escapa, quebra linhas e transforma URLs em botões/links.
            $corpoHtml = nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'));
            $corpoHtml = preg_replace(
                '~(https?://[^\s<]+)~',
                '<a href="$1" style="color:#1d4e8f; font-weight:bold;">$1</a>',
                $corpoHtml
            );

            $mail->Body = "
            <!DOCTYPE html><html><head><meta charset='UTF-8'></head>
            <body style='font-family: Arial, sans-serif; line-height:1.6; color:#333;'>
                <div style='max-width:600px; margin:0 auto; padding:20px;'>
                    <div style='background:#1d4e8f; color:#fff; padding:18px; text-align:center; border-radius:8px 8px 0 0;'>
                        <h2 style='margin:0;'>Intranet AOM</h2>
                    </div>
                    <div style='background:#f8f9fa; padding:26px; border-radius:0 0 8px 8px;'>
                        {$corpoHtml}
                    </div>
                    <div style='text-align:center; margin-top:20px; color:#666; font-size:12px;'>
                        <p>Este é um e-mail automático, não responda.</p>
                    </div>
                </div>
            </body></html>";
            $mail->AltBody = $mensagem;

            $mail->send();

            try {
                NotificacaoService::gravarEmail($email, $assunto, $mensagem, true, null, $usuarioId, $nome, 'divulgacao_app');
            } catch (Throwable $e) {
                error_log('DivulgacaoService: falha ao gravar histórico email: ' . $e->getMessage());
            }
            return ['sucesso' => true, 'mensagem' => 'enviado'];

        } catch (\Exception $e) {
            try {
                NotificacaoService::gravarEmail($email, $assunto, $mensagem, false, $e->getMessage(), $usuarioId, $nome, 'divulgacao_app');
            } catch (Throwable $e2) {
                error_log('DivulgacaoService: falha ao gravar histórico email (erro): ' . $e2->getMessage());
            }
            return ['sucesso' => false, 'mensagem' => $e->getMessage()];
        }
    }
}
