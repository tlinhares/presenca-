<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';

// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../includes/phpmailer/src/Exception.php';
require_once __DIR__ . '/../../includes/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../includes/phpmailer/src/SMTP.php';

// Passar conexão para WhatsAppService antes de incluí-lo
if (isset($conn) && $conn instanceof mysqli) {
    // Tornar conexão globalmente acessível
    $GLOBALS['db_conn'] = $conn;
}

require_once __DIR__ . '/../../core/services/WhatsAppService.php';
require_once __DIR__ . '/../../core/services/NotificacaoService.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$input = json_decode(file_get_contents('php://input'), true);
$usuario_id = isset($input['usuario_id']) ? (int)$input['usuario_id'] : 0;

if (!$usuario_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID do usuário não fornecido']);
    exit;
}

// Buscar dados do usuário
$stmt = $conn->prepare("SELECT id, nome, email, telefone FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não encontrado']);
    exit;
}

$usuario = $result->fetch_assoc();
$stmt->close();

// Funções antigas removidas - usando WhatsAppService agora
// Todas as funções de envio foram migradas para WhatsAppService

/*
function normalizarTelefone($telefone) {
    return WhatsAppService::normalizarTelefone($telefone);
}

function enviarWhatsApp($telefone, $mensagem) {
    $telefone_normalizado = normalizarTelefone($telefone);
    
    if (empty($telefone_normalizado)) {
        error_log("WhatsApp: Telefone inválido ou vazio. Original: " . $telefone);
        return ['sucesso' => false, 'mensagem' => 'Número de telefone inválido'];
    }
    
    // Adicionar sinal de + no início do número (requisito da API do WhatsApp)
    // A função normalizarTelefone remove todos os caracteres não numéricos, então sempre adicionamos o +
    $telefone_com_codigo = '+' . ltrim($telefone_normalizado, '+');
    
    $dados = [
        'phone' => $telefone_com_codigo,
        'isGroup' => false,
        'isNewsletter' => false,
        'isLid' => false,
        'message' => $mensagem
    ];
    
    // GARANTIR que o número sempre tenha o + no início - FORÇAR
    $telefone_com_codigo = '+' . preg_replace('/^\+/', '', $telefone_normalizado);
    
    // Atualizar o array de dados com o número garantido
    $dados['phone'] = $telefone_com_codigo;
    
    // VALIDAÇÃO FINAL - garantir que o + está presente
    if ($dados['phone'][0] !== '+') {
        $dados['phone'] = '+' . $dados['phone'];
        $telefone_com_codigo = $dados['phone'];
    }
    
    error_log("WhatsApp: ========== INÍCIO DO ENVIO ==========");
    error_log("WhatsApp: Telefone original: " . $telefone);
    error_log("WhatsApp: Telefone normalizado: " . $telefone_normalizado);
    error_log("WhatsApp: Telefone com código FINAL: " . $telefone_com_codigo);
    error_log("WhatsApp: Primeiro caractere: '" . substr($telefone_com_codigo, 0, 1) . "'");
    error_log("WhatsApp: Tamanho: " . strlen($telefone_com_codigo));
    
    $url_whatsapp = 'http://10.144.128.34:21465/api/servidor/send-message';
    
    // Usar cURL para melhor confiabilidade
    $ch = curl_init($url_whatsapp);
    $json_payload = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    error_log("WhatsApp: JSON payload que será enviado: " . $json_payload);
    error_log("WhatsApp: Verificação no JSON: " . (strpos($json_payload, '"+5565999793296"') !== false ? 'ENCONTRADO COM +' : 'NÃO ENCONTRADO'));
    error_log("WhatsApp: =====================================");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json_payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer $2b$10$HXuccMTGKs8y7aZuhrrxdOfPBw3DAFheEg6.pdZBBn6_7nPS4XLG2'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $resposta = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($resposta === false || !empty($curl_error)) {
        error_log("WhatsApp: Erro na comunicação com API. cURL Error: " . $curl_error);
        error_log("WhatsApp: HTTP Code: " . $http_code);
        return ['sucesso' => false, 'mensagem' => 'Erro na comunicação com API do WhatsApp: ' . ($curl_error ?: 'Erro desconhecido')];
    }
    
    error_log("WhatsApp: HTTP Code: " . $http_code);
    error_log("WhatsApp: Resposta recebida: " . $resposta);
    
    $resposta_json = json_decode($resposta, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("WhatsApp: Erro ao decodificar JSON. Erro: " . json_last_error_msg());
        return ['sucesso' => false, 'mensagem' => 'Resposta inválida da API do WhatsApp'];
    }
    
    error_log("WhatsApp: Resposta decodificada: " . json_encode($resposta_json));
    
    // Verificar diferentes formatos de resposta
    if (isset($resposta_json['status']) && ($resposta_json['status'] === 'success' || $resposta_json['status'] === 'ok')) {
        error_log("WhatsApp: Mensagem enviada com sucesso");
        return ['sucesso' => true, 'mensagem' => 'WhatsApp enviado com sucesso'];
    } elseif (isset($resposta_json['success']) && $resposta_json['success'] === true) {
        error_log("WhatsApp: Mensagem enviada com sucesso (formato alternativo)");
        return ['sucesso' => true, 'mensagem' => 'WhatsApp enviado com sucesso'];
    } else {
        $erro_detalhado = $resposta_json['message'] ?? $resposta_json['error'] ?? $resposta_json['msg'] ?? 'Erro desconhecido';
        error_log("WhatsApp: Erro no envio. Detalhes: " . $erro_detalhado);
        return ['sucesso' => false, 'mensagem' => 'Erro no envio: ' . $erro_detalhado];
    }
}
*/

// Função para gerar mensagem de notificação (retorna HTML e texto)
function gerarMensagemNotificacao($nome, $linkSenha) {
    $mensagem_html = "
      Olá <b>{$nome}</b>,<br><br>
      Você foi cadastrado no <b>Sistema de Refeições AOM</b>.<br><br>
      A partir de agora, você pode acessar o sistema e reservar suas refeições.<br><br>
      
      🔗 <a href='http://presenca.aom.org.br/'>Clique aqui para acessar o sistema</a><br><br>
      
      <b>Se você não lembra da sua senha:</b><br>
      <a href='{$linkSenha}'>Clique aqui para visualizar sua senha</a><br><br>
      
      Atenciosamente,<br>
      <b>Equipe Presença AOM</b>
    ";
    
    // Converter HTML para texto plano (WhatsApp)
    $mensagem_texto = "Olá *{$nome}*,\n\n";
    $mensagem_texto .= "Você foi cadastrado no *Sistema de Refeições AOM*.\n\n";
    $mensagem_texto .= "A partir de agora, você pode acessar o sistema e reservar suas refeições.\n\n";
    $mensagem_texto .= "🔗 Acesse: http://presenca.aom.org.br/\n\n";
    $mensagem_texto .= "*Se você não lembra da sua senha:*\n";
    $mensagem_texto .= "Clique aqui para visualizar sua senha: {$linkSenha}\n\n";
    $mensagem_texto .= "Atenciosamente,\n*Equipe Presença AOM*";
    
    return [
        'html' => $mensagem_html,
        'texto' => $mensagem_texto
    ];
}

// Função para enviar email
function enviarEmail($email, $nome, $id_usuario, $conn, $token_existente = null) {
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
    
    // Usar token existente ou gerar novo
    if ($token_existente) {
        $token = $token_existente;
    } else {
        // Gerar token de visualização da senha
        $token = bin2hex(random_bytes(16));
        $stmt = $conn->prepare("INSERT INTO tokens_senha (id_usuario, token, expiracao) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
        $stmt->bind_param("is", $id_usuario, $token);
        $stmt->execute();
        $stmt->close();
    }
    
    $linkSenha = "http://presenca.aom.org.br/ver_senha.php?token={$token}";
    
    // Gerar mensagem
    $mensagens = gerarMensagemNotificacao($nome, $linkSenha);
    
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
        $mail->Subject = 'Acesso ao Sistema de Refeições AOM';
        $mail->Body = $mensagens['html'];
        
        $mail->send();
        
        // Atualizar campo notificado_em
        $stmt = $conn->prepare("UPDATE usuarios SET notificado_em = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $stmt->close();
        
        return ['sucesso' => true, 'mensagem' => 'Email enviado com sucesso', 'link_senha' => $linkSenha];
        
    } catch (Exception $e) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao enviar email: ' . $mail->ErrorInfo];
    }
}

// Verificar se tem telefone válido
$telefone_original = $usuario['telefone'] ?? '';
$telefone_normalizado = WhatsAppService::normalizarTelefone($telefone_original);
$tem_telefone = !empty($telefone_normalizado);

error_log("Notificação: Usuário ID {$usuario_id}, Nome: {$usuario['nome']}");
error_log("Notificação: Telefone original: " . ($telefone_original ?: 'vazio'));
error_log("Notificação: Telefone normalizado: " . ($telefone_normalizado ?: 'vazio'));
error_log("Notificação: Tem telefone válido: " . ($tem_telefone ? 'SIM' : 'NÃO'));
error_log("Notificação: Email: " . ($usuario['email'] ?: 'vazio'));

// Gerar token de visualização da senha (será usado tanto no WhatsApp quanto no email)
$token = bin2hex(random_bytes(16));
$stmt = $conn->prepare("INSERT INTO tokens_senha (id_usuario, token, expiracao) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
$stmt->bind_param("is", $usuario_id, $token);
$stmt->execute();
$stmt->close();

$linkSenha = "http://presenca.aom.org.br/ver_senha.php?token={$token}";

// Gerar mensagem (mesmo conteúdo do email, convertido para texto)
$mensagens = gerarMensagemNotificacao($usuario['nome'], $linkSenha);
$mensagem_whatsapp = $mensagens['texto'];

// Decidir método de envio
// LÓGICA: Se tem telefone, envia APENAS por WhatsApp
// Se não tem telefone mas tem email, envia APENAS por email
// NUNCA envia por ambos
if ($tem_telefone) {
    error_log("Notificação: Tentando enviar via WhatsApp");
    // Enviar por WhatsApp (mesmo conteúdo do email) usando WhatsAppService
    // Passar dados adicionais para gravar no histórico
    $resultado = WhatsAppService::enviarMensagem($usuario['telefone'], $mensagem_whatsapp, [
        'log_callback' => function($msg) {
            error_log("WhatsApp: $msg");
        },
        'usuario_id' => $usuario_id,
        'nome_destinatario' => $usuario['nome'],
        'tipo_mensagem' => 'cadastro_usuario',
        'tipo_notificacao' => 'cadastro_usuario'
    ]);
    
    if ($resultado['sucesso']) {
        // Atualizar campo notificado_em
        $stmt = $conn->prepare("UPDATE usuarios SET notificado_em = NOW() WHERE id = ?");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Notificação enviada com sucesso via WhatsApp',
            'metodo' => 'whatsapp'
        ]);
    } else {
        // Se falhar WhatsApp, retornar erro (NÃO tentar email como fallback)
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Falha ao enviar WhatsApp: ' . $resultado['mensagem'],
            'metodo' => 'whatsapp'
        ]);
    }
} else {
    error_log("Notificação: Não tem telefone válido, tentando enviar por email");
    // Enviar por email (usar o mesmo token já gerado)
    if (empty($usuario['email'])) {
        error_log("Notificação: Usuário não possui telefone nem email cadastrado");
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Usuário não possui telefone nem email cadastrado'
        ]);
        exit;
    }
    
    error_log("Notificação: Enviando email para: " . $usuario['email']);
    $resultado = enviarEmail($usuario['email'], $usuario['nome'], $usuario_id, $conn, $token);
    
    // Gravar notificação no histórico
    try {
        $assunto = 'Acesso ao Sistema de Refeições AOM';
        $mensagem_email_texto = strip_tags($mensagens['html']);
        $erro = $resultado['sucesso'] ? null : $resultado['mensagem'];
        NotificacaoService::gravarEmail(
            $usuario['email'],
            $assunto,
            $mensagem_email_texto,
            $resultado['sucesso'],
            $erro,
            $usuario_id,
            $usuario['nome'],
            'cadastro_usuario'
        );
    } catch (Exception $e) {
        error_log("Erro ao gravar notificação de email: " . $e->getMessage());
    }
    
    if ($resultado['sucesso']) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Notificação enviada com sucesso via Email',
            'metodo' => 'email'
        ]);
    } else {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => $resultado['mensagem']
        ]);
    }
}

$conn->close();
?>

