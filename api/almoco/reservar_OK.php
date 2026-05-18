<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
include_once(__DIR__ . '/../conexao.php');
session_start();

// Verificar se usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}
$id_usuario = $_SESSION['usuario_id'];

$dataHoje = date('Y-m-d');
$horaAtual = date('H:i');

// Buscar hora limite do banco
$hora_limite = '22:00'; // padrão
$conf = $conn->query("SELECT valor FROM configuracoes WHERE chave = 'hora_limite' LIMIT 1");
if ($conf && $row = $conf->fetch_assoc()) {
    $hora_limite = $row['valor'];
}

// Verifica se o usuário já fez reserva hoje
$check = $conn->prepare("SELECT id FROM reservas_almoco WHERE id_usuario = ? AND data = ?");
$check->bind_param("is", $id_usuario, $dataHoje);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Você já confirmou o almoço hoje.']);
    exit;
}

// Verifica se já passou do horário limite
if ($horaAtual > $hora_limite) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Horário limite para reservar já passou.']);
    exit;
}

// Inserir a reserva principal
$stmt = $conn->prepare("INSERT INTO reservas_almoco (id_usuario, data, reservou_conjuge, marmitex, horario_confirmacao)
                        VALUES (?, ?, 0, 0, NOW())");
$stmt->bind_param("is", $id_usuario, $dataHoje);

if ($stmt->execute()) {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao salvar reserva.']);
}
?>
