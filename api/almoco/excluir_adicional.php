<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

include_once(__DIR__ . '/../conexao.php');
include_once(__DIR__ . '/../../utils/config.php');

$id_usuario = $_SESSION['usuario_id'];
$id_reserva = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id_reserva <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido']);
    exit;
}

// Recupera dados da reserva
$sql = "SELECT data FROM reservas_adicionais WHERE id = ? AND id_usuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id_reserva, $id_usuario);
$stmt->execute();
$stmt->bind_result($data_reserva);

if (!$stmt->fetch()) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Reserva não encontrada']);
    exit;
}
$stmt->close();

// Verifica se pode excluir com base no horário
$data_hoje = date('Y-m-d');
$hora_agora = date('H:i');
$hora_limite = get_config('hora_limite', '10:30');
$permitir_atraso = get_config('permitir_reserva_atraso', '0');

if ($data_reserva !== $data_hoje || ($hora_agora > $hora_limite && $permitir_atraso !== '1')) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Não é mais permitido excluir esta reserva.']);
    exit;
}

// Buscar nome do dependente antes de excluir
$stmt_dep = $conn->prepare("SELECT d.nome FROM reservas_adicionais ra 
                           INNER JOIN dependentes d ON ra.id_dependente = d.id 
                           WHERE ra.id = ? AND ra.id_usuario = ?");
if ($stmt_dep) {
    $stmt_dep->bind_param("ii", $id_reserva, $id_usuario);
    $stmt_dep->execute();
    $result_dep = $stmt_dep->get_result();
    $dependente_nome = null;
    if ($result_dep->num_rows > 0) {
        $row_dep = $result_dep->fetch_assoc();
        $dependente_nome = $row_dep['nome'];
    }
    $stmt_dep->close();
} else {
    $dependente_nome = null;
}

// Excluir reserva
$stmt = $conn->prepare("DELETE FROM reservas_adicionais WHERE id = ? AND id_usuario = ?");
$stmt->bind_param("ii", $id_reserva, $id_usuario);

if ($stmt->execute()) {
    // Enviar notificação se habilitada
    require_once __DIR__ . '/../notificacao/enviar_notificacao_reserva.php';
    $horario_atual = date('H:i');
    $dados_notificacao = [
        'data' => date('d/m/Y', strtotime($data_reserva)),
        'horario' => $horario_atual,
        'tipo_reserva' => 'adicional',
        'dependente_nome' => $dependente_nome
    ];
    enviarNotificacaoReserva($id_usuario, 'cancelada', $dados_notificacao, $conn);
    
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao excluir reserva.']);
}
?>
