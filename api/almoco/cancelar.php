<?php
session_start();
include_once(__DIR__ . '/../conexao.php');
include_once(__DIR__ . '/../../auth/verifica_sessao.php');

header('Content-Type: application/json');

$id_usuario = $_SESSION['usuario_id'];
$data = $_POST['data'] ?? date('Y-m-d');
$horaAtual = date('H:i');

// Buscar hora limite do banco
$hora_limite = '09:00';
$res = $conn->query("SELECT valor FROM configuracoes WHERE chave = 'hora_limite' LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $hora_limite = $row['valor'];
}

// Verificar se já passou do horário limite (apenas para data de hoje)
if ($data === date('Y-m-d') && $horaAtual > $hora_limite) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Não é mais possível cancelar.']);
    exit;
}

// Verificar se existe reserva
$check = $conn->prepare("SELECT id FROM reservas_almoco WHERE id_usuario = ? AND data = ?");
$check->bind_param("is", $id_usuario, $data);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Reserva não encontrada.']);
    exit;
}

// Deletar reserva
$del = $conn->prepare("DELETE FROM reservas_almoco WHERE id_usuario = ? AND data = ?");
$del->bind_param("is", $id_usuario, $data);

if ($del->execute()) {
    // Sinaliza fila facial (cancela pendentes + marca sincronizados pra remoção).
    require_once __DIR__ . '/../../core/services/FacialService.php';
    FacialService::onReservaCancelada($conn, (int) $id_usuario, 'usuario', $data);

    // Enviar notificação se habilitada
    require_once __DIR__ . '/../notificacao/enviar_notificacao_reserva.php';
    $dados_notificacao = [
        'data' => date('d/m/Y', strtotime($data)),
        'tipo_reserva' => 'própria'
    ];
    enviarNotificacaoReserva($id_usuario, 'cancelada', $dados_notificacao, $conn);

    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao cancelar.']);
}
?>
