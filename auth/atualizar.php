<?php
include_once(__DIR__ . '/../conexao.php');
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_categoria'] !== 'admin') {
    echo json_encode(array('status' => 'erro', 'mensagem' => 'Acesso negado.'));
    exit;
}

// Compatível com PHP 5.5
function valor_post($campo, $padrao = '') {
    return isset($_POST[$campo]) ? $_POST[$campo] : $padrao;
}

$dados = array(
    'hora_limite' => valor_post('hora_limite'),
    'valor_refeicao' => valor_post('valor_refeicao'),
    'valor_marmitex' => valor_post('valor_marmitex'),
    'marmitex_habilitado' => isset($_POST['marmitex_habilitado']) ? '1' : '0',
    'fuso_horario' => valor_post('fuso_horario'),
    'qrcode_auto_gerado' => isset($_POST['qrcode_auto_gerado']) ? '1' : '0',
    'limite_reservas_dia' => valor_post('limite_reservas_dia'),
    'email_notificacoes' => valor_post('email_notificacoes'),
    'permitir_reserva_atraso' => isset($_POST['permitir_reserva_atraso']) ? '1' : '0',
    'mensagem_inicio' => valor_post('mensagem_inicio')
);

$ok = true;
foreach ($dados as $chave => $valor) {
    $stmt = $conn->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    $stmt->bind_param("ss", $chave, $valor);
    if (!$stmt->execute()) {
        $ok = false;
        break;
    }
}

echo json_encode($ok
    ? array('status' => 'ok')
    : array('status' => 'erro', 'mensagem' => 'Erro ao salvar configurações.'));
?>
