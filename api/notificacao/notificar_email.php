<?php
header('Content-Type: text/html; charset=UTF-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../includes/phpmailer/src/Exception.php';
require_once __DIR__ . '/../../includes/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../includes/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../core/services/NotificacaoService.php';

// Passar conexão para serviços
if (isset($conn) && $conn instanceof mysqli) {
    $GLOBALS['db_conn'] = $conn;
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['usuarios']) || !is_array($input['usuarios'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos']);
    exit;
}

$usuarios = $input['usuarios'];

$config = [];
$campos = ['email_notificacoes', 'smtp_email', 'imap_email', 'port_email', 'senha_email', 'nome_remetente_email'];
$res = $conn->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('" . implode("','", $campos) . "')");
while ($row = $res->fetch_assoc()) {
    $config[$row['chave']] = $row['valor'];
}

$sucesso = 0;
$falhas = [];

foreach ($usuarios as $usuario) {
    $email = $usuario['email'];
    $nome = $usuario['nome'];
    $id = (int)$usuario['id'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $falhas[] = "{$nome} (email inválido)";
        continue;
    }

    // Gerar token de visualização da senha
    $token = bin2hex(random_bytes(16));
    $conn->query("INSERT INTO tokens_senha (id_usuario, token, expiracao) VALUES ({$id}, '{$token}', DATE_ADD(NOW(), INTERVAL 24 HOUR))");

    $linkSenha = "http://presenca.aom.org.br/ver_senha.php?token={$token}";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $config['smtp_email'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['email_notificacoes'];
        $mail->Password = $config['senha_email'];
        $mail->SMTPSecure = 'tls';
        $mail->Port = (int)$config['port_email'];
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($config['email_notificacoes'], $config['nome_remetente_email'] ?? 'Sistema de Presença AOM');
        $mail->addAddress($email, $nome);
        $mail->isHTML(true);
        $mail->Subject = 'Acesso ao Sistema de Refeições AOM';
        $mail->Body = "
          Olá <b>{$nome}</b>,<br><br>
          Você foi cadastrado no <b>Sistema de Refeições AOM</b>.<br><br>
          A partir de agora, você pode acessar o sistema e reservar suas refeições.<br><br>

          🔗 <a href='http://presenca.aom.org.br/'>Clique aqui para acessar o sistema</a><br><br>

          <b>Se você não lembra da sua senha:</b><br>
          <a href='{$linkSenha}'>Clique aqui para visualizar sua senha</a><br><br>

          Atenciosamente,<br>
          <b>Equipe Presença AOM</b>
        ";

        $mail->send();
        
        // Gravar notificação de email no histórico
        try {
            $assunto = 'Acesso ao Sistema de Refeições AOM';
            $mensagem_texto = strip_tags($mail->Body);
            NotificacaoService::gravarEmail(
                $email,
                $assunto,
                $mensagem_texto,
                true,
                null,
                $id,
                $nome,
                'cadastro_usuario'
            );
        } catch (Exception $e_gravacao) {
            error_log("Erro ao gravar notificação de email: " . $e_gravacao->getMessage());
        }
        
        $sucesso++;
    } catch (Exception $e) {
        // Gravar notificação de falha no histórico
        try {
            $assunto = 'Acesso ao Sistema de Refeições AOM';
            $mensagem_texto = strip_tags($mail->Body ?? '');
            NotificacaoService::gravarEmail(
                $email,
                $assunto,
                $mensagem_texto,
                false,
                $mail->ErrorInfo ?? $e->getMessage(),
                $id,
                $nome,
                'cadastro_usuario'
            );
        } catch (Exception $e_gravacao) {
            error_log("Erro ao gravar notificação de falha de email: " . $e_gravacao->getMessage());
        }
        
        $falhas[] = "{$nome} ({$email}) - Erro: " . $mail->ErrorInfo;
    }
}

echo json_encode([
    'status' => 'ok',
    'mensagem' => "Notificações enviadas com sucesso para {$sucesso} de " . count($usuarios) . " usuários.",
    'falhas' => $falhas
]);
?>
