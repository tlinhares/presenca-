<?php
/**
 * MensageriaService — envio de mensagem com fallback automático de canal.
 *
 * Contexto: os números de WhatsApp vêm sofrendo bloqueio e o canal está
 * ficando instável. Regra do sistema: SEMPRE que um envio de WhatsApp
 * falhar e o destinatário tiver e-mail, a mesma mensagem sai por e-mail.
 *
 * Uso típico (substitui a chamada direta ao WhatsAppService nos crons/APIs):
 *
 *   $r = MensageriaService::enviarComFallback($conn, [
 *       'usuario_id' => 123,
 *       'nome'       => 'Fulano',
 *       'telefone'   => '659999...',      // pode ser vazio → vai direto pro e-mail
 *       'email'      => 'fulano@x.com',   // pode ser vazio → sem fallback
 *   ], $mensagem, [
 *       'assunto' => 'Aviso do refeitório',   // usado no e-mail
 *       'tipo'    => 'notificacao_diaria',    // tag do histórico
 *   ]);
 *   // $r = ['sucesso' => bool, 'canal' => 'whatsapp'|'email'|null, 'detalhe' => string]
 *
 * Histórico: grava via NotificacaoService nos dois canais (o e-mail de
 * fallback é gravado com tipo "<tipo>_fallback" pra ser auditável).
 */

require_once __DIR__ . '/WhatsAppService.php';
require_once __DIR__ . '/NotificacaoService.php';

class MensageriaService
{
    /**
     * Tenta WhatsApp; se falhar (ou não houver telefone), cai pro e-mail.
     */
    public static function enviarComFallback(mysqli $conn, array $dest, string $mensagem, array $opts = []): array
    {
        $usuarioId = (int) ($dest['usuario_id'] ?? 0);
        $nome      = trim((string) ($dest['nome'] ?? ''));
        $telefone  = trim((string) ($dest['telefone'] ?? ''));
        $email     = trim((string) ($dest['email'] ?? ''));
        $assunto   = trim((string) ($opts['assunto'] ?? 'Notificação — Intranet AOM'));
        $tipo      = trim((string) ($opts['tipo'] ?? 'mensageria'));

        $temTelefone = strlen(preg_replace('/\D/', '', $telefone)) >= 10;
        $temEmail    = $email !== '' && strpos($email, '@') !== false;

        // 1) WhatsApp
        $erroWhats = null;
        if ($temTelefone) {
            $r = WhatsAppService::enviarMensagem($telefone, $mensagem);
            $ok = !empty($r['sucesso']);

            try {
                NotificacaoService::gravarWhatsApp($telefone, $mensagem, $r, $usuarioId ?: null, $nome ?: null, $tipo);
            } catch (Throwable $e) {
                error_log('MensageriaService: falha ao gravar histórico whatsapp: ' . $e->getMessage());
            }

            if ($ok) {
                return ['sucesso' => true, 'canal' => 'whatsapp', 'detalhe' => 'Enviado via WhatsApp'];
            }
            $erroWhats = (string) ($r['mensagem'] ?? 'falha desconhecida');
        }

        // 2) Fallback: e-mail
        if ($temEmail) {
            $rEmail = self::enviarEmail($conn, $email, $nome, $assunto, $mensagem);
            $tipoHist = $temTelefone ? ($tipo . '_fallback') : $tipo;

            try {
                NotificacaoService::gravarEmail(
                    $email, $assunto, $mensagem,
                    !empty($rEmail['sucesso']),
                    !empty($rEmail['sucesso']) ? null : ($rEmail['mensagem'] ?? 'erro'),
                    $usuarioId ?: null, $nome ?: null, $tipoHist
                );
            } catch (Throwable $e) {
                error_log('MensageriaService: falha ao gravar histórico email: ' . $e->getMessage());
            }

            if (!empty($rEmail['sucesso'])) {
                return [
                    'sucesso' => true,
                    'canal'   => 'email',
                    'detalhe' => $temTelefone
                        ? 'WhatsApp falhou (' . $erroWhats . ') — enviado por e-mail'
                        : 'Enviado por e-mail (sem telefone)',
                ];
            }
            return [
                'sucesso' => false,
                'canal'   => null,
                'detalhe' => 'WhatsApp: ' . ($erroWhats ?? 'sem telefone') . ' | E-mail: ' . ($rEmail['mensagem'] ?? 'erro'),
            ];
        }

        return [
            'sucesso' => false,
            'canal'   => null,
            'detalhe' => $temTelefone
                ? 'WhatsApp falhou (' . $erroWhats . ') e usuário não tem e-mail pra fallback'
                : 'Usuário sem telefone e sem e-mail',
        ];
    }

    /**
     * Helper de retrofit pros pontos que JÁ chamaram o WhatsAppService por
     * conta própria e só precisam do fallback na falha: envia o e-mail e
     * grava o histórico (tipo "<tipo>_fallback") em uma chamada. Retorna
     * true se o e-mail saiu.
     */
    public static function fallbackEmail(
        mysqli $conn,
        string $email,
        string $nome,
        string $assunto,
        string $mensagem,
        ?int $usuarioId = null,
        string $tipo = 'mensageria',
        ?string $anexo = null
    ): bool {
        $email = trim($email);
        if ($email === '' || strpos($email, '@') === false) {
            return false;
        }

        $r = self::enviarEmail($conn, $email, $nome, $assunto, $mensagem, $anexo);

        try {
            NotificacaoService::gravarEmail(
                $email, $assunto, $mensagem,
                !empty($r['sucesso']),
                !empty($r['sucesso']) ? null : ($r['mensagem'] ?? 'erro'),
                $usuarioId, $nome ?: null, $tipo . '_fallback'
            );
        } catch (Throwable $e) {
            error_log('MensageriaService: falha ao gravar histórico email: ' . $e->getMessage());
        }

        return !empty($r['sucesso']);
    }

    /**
     * Descobre o e-mail de um destinatário a partir do telefone (últimos 8
     * dígitos batidos contra usuarios.telefone). Usado como fallback quando
     * o destino é um número avulso (ex.: automações) sem e-mail configurado.
     */
    public static function emailPorTelefone(mysqli $conn, string $telefone): ?string
    {
        $dig = preg_replace('/\D/', '', $telefone);
        if (strlen($dig) < 8) return null;
        $sufixo = substr($dig, -8);
        $stmt = $conn->prepare(
            "SELECT email FROM usuarios
              WHERE ativo = 1 AND email LIKE '%@%'
                AND RIGHT(REGEXP_REPLACE(telefone, '[^0-9]', ''), 8) = ?
              LIMIT 1"
        );
        if (!$stmt) return null;
        $stmt->bind_param('s', $sufixo);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $r['email'] ?? null;
    }

    /**
     * Envio de e-mail genérico (SMTP das configuracoes) com o layout padrão
     * do sistema. Texto simples é convertido pra HTML (nl2br + links).
     * $anexo: caminho de arquivo opcional (ex.: PDF do relatório diário).
     */
    public static function enviarEmail(mysqli $conn, string $email, string $nome, string $assunto, string $mensagem, ?string $anexo = null): array
    {
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
            $mail->Timeout    = 15;

            $mail->setFrom($config['email_notificacoes'] ?? '', $config['nome_remetente_email'] ?? 'Intranet AOM');
            $mail->addAddress($email, $nome ?: $email);
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            if ($anexo !== null && is_readable($anexo)) {
                $mail->addAttachment($anexo);
            }

            // WhatsApp usa *negrito* — converte pro e-mail também
            $corpoHtml = nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'));
            $corpoHtml = preg_replace('/\*([^*\n]+)\*/', '<strong>$1</strong>', $corpoHtml);
            $corpoHtml = preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#1d4e8f;">$1</a>', $corpoHtml);

            $mail->Body = "
            <!DOCTYPE html><html><head><meta charset='UTF-8'></head>
            <body style='font-family: Arial, sans-serif; line-height:1.6; color:#333;'>
                <div style='max-width:600px; margin:0 auto; padding:20px;'>
                    <div style='background:#1d4e8f; color:#fff; padding:16px; text-align:center; border-radius:8px 8px 0 0;'>
                        <h2 style='margin:0; font-size:19px;'>" . htmlspecialchars($assunto, ENT_QUOTES, 'UTF-8') . "</h2>
                    </div>
                    <div style='background:#f8f9fa; padding:24px; border-radius:0 0 8px 8px;'>
                        {$corpoHtml}
                    </div>
                    <div style='text-align:center; margin-top:16px; color:#666; font-size:12px;'>
                        <p>Este é um e-mail automático, não responda.</p>
                    </div>
                </div>
            </body></html>";
            $mail->AltBody = $mensagem;

            $mail->send();
            return ['sucesso' => true, 'mensagem' => 'enviado'];
        } catch (\Exception $e) {
            error_log('MensageriaService::enviarEmail: ' . $e->getMessage());
            return ['sucesso' => false, 'mensagem' => $e->getMessage()];
        }
    }
}
