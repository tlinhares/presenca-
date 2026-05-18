<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../conexao.php';
include_once(__DIR__ . '/../../auth/verifica_sessao.php');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Verificar se a tabela existe
$tabela_existe = $conn->query("SHOW TABLES LIKE 'notificacoes_usuario'")->num_rows > 0;

if (!$tabela_existe) {
    // Tabela não existe, retornar valores padrão
    echo json_encode([
        'status' => 'ok',
        'configurado' => false,
        'dados' => [
            'notificar_reserva_propria' => false,
            'notificar_reserva_adicional' => false,
            'notificar_reserva_multipla' => false,
            'notificar_reserva_cancelada' => false,
            'notificar_lembrete_diario' => true
        ]
    ]);
    $conn->close();
    exit;
}

$stmt = $conn->prepare("SELECT 
    notificar_reserva_propria,
    notificar_reserva_adicional,
    notificar_reserva_multipla,
    notificar_reserva_cancelada,
    notificar_lembrete_diario
FROM notificacoes_usuario 
WHERE id_usuario = ?");

if (!$stmt) {
    echo json_encode([
        'status' => 'ok',
        'configurado' => false,
        'dados' => [
            'notificar_reserva_propria' => false,
            'notificar_reserva_adicional' => false,
            'notificar_reserva_multipla' => false,
            'notificar_reserva_cancelada' => false,
            'notificar_lembrete_diario' => true
        ]
    ]);
    $conn->close();
    exit;
}

$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $config = $result->fetch_assoc();
    echo json_encode([
        'status' => 'ok',
        'configurado' => true,
        'dados' => [
            'notificar_reserva_propria' => (bool)$config['notificar_reserva_propria'],
            'notificar_reserva_adicional' => (bool)$config['notificar_reserva_adicional'],
            'notificar_reserva_multipla' => (bool)$config['notificar_reserva_multipla'],
            'notificar_reserva_cancelada' => isset($config['notificar_reserva_cancelada']) ? (bool)$config['notificar_reserva_cancelada'] : false,
            'notificar_lembrete_diario' => isset($config['notificar_lembrete_diario']) ? (bool)$config['notificar_lembrete_diario'] : true
        ]
    ]);
} else {
    echo json_encode([
        'status' => 'ok',
        'configurado' => false,
        'dados' => [
            'notificar_reserva_propria' => false,
            'notificar_reserva_adicional' => false,
            'notificar_reserva_multipla' => false,
            'notificar_reserva_cancelada' => false,
            'notificar_lembrete_diario' => true
        ]
    ]);
}

$stmt->close();
$conn->close();
?>

