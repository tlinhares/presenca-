<?php
/**
 * Auditoria de solicitações de recuperação de senha.
 *
 * O access log do Apache só registra o IP do proxy reverso (10.144.128.33),
 * então aqui capturamos o X-Forwarded-For (IP real do cliente), user-agent
 * e contexto completo em logs/recuperacao_senha_audit.log — pra identificar
 * exatamente QUAL dispositivo está pedindo recuperação.
 */
function auditar_recuperacao_senha(string $origem, string $email, string $resultado): void
{
    $linha = json_encode([
        'quando'    => date('Y-m-d H:i:s'),
        'origem'    => $origem,               // 'mobile' | 'web'
        'email'     => $email,
        'resultado' => $resultado,            // 'enviado' | 'nao_encontrado' | erro
        'ip_proxy'  => $_SERVER['REMOTE_ADDR'] ?? '',
        'ip_real'   => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '(sem XFF)',
        'user_agent'=> $_SERVER['HTTP_USER_AGENT'] ?? '(sem UA)',
        'referer'   => $_SERVER['HTTP_REFERER'] ?? '',
        'host'      => $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? ''),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    @file_put_contents(__DIR__ . '/../logs/recuperacao_senha_audit.log', $linha . "\n", FILE_APPEND);
}
