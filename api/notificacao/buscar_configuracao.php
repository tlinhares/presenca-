<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

if (!isset($_SESSION['usuario_id'])) {
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
        exit;
    }
}

$usuario_id = (int) $_SESSION['usuario_id'];

// Default quando o usuário ainda não tem linha na tabela.
$default = [
    'notificar_reserva_propria'    => false,
    'notificar_reserva_adicional'  => false,
    'notificar_reserva_multipla'   => false,
    'notificar_reserva_cancelada'  => false,
    'notificar_lembrete_diario'    => true,
    'notificar_justificativa_culto'=> true,
    'canal_email'                  => true,
    'canal_whatsapp'               => true,
    'canal_push'                   => true,
];

$tabela_existe = $conn->query("SHOW TABLES LIKE 'notificacoes_usuario'")->num_rows > 0;

if (!$tabela_existe) {
    echo json_encode(['status' => 'ok', 'configurado' => false, 'dados' => $default]);
    $conn->close();
    exit;
}

$stmt = $conn->prepare(
    "SELECT notificar_reserva_propria, notificar_reserva_adicional, notificar_reserva_multipla,
            notificar_reserva_cancelada, notificar_lembrete_diario, notificar_justificativa_culto,
            canal_email, canal_whatsapp, canal_push
     FROM notificacoes_usuario WHERE id_usuario = ?"
);

if (!$stmt) {
    echo json_encode(['status' => 'ok', 'configurado' => false, 'dados' => $default]);
    $conn->close();
    exit;
}

$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $config = $result->fetch_assoc();
    echo json_encode([
        'status' => 'ok',
        'configurado' => true,
        'dados' => [
            'notificar_reserva_propria'    => (bool) $config['notificar_reserva_propria'],
            'notificar_reserva_adicional'  => (bool) $config['notificar_reserva_adicional'],
            'notificar_reserva_multipla'   => (bool) $config['notificar_reserva_multipla'],
            'notificar_reserva_cancelada'  => (bool) $config['notificar_reserva_cancelada'],
            'notificar_lembrete_diario'    => isset($config['notificar_lembrete_diario'])   ? (bool) $config['notificar_lembrete_diario']    : true,
            'notificar_justificativa_culto'=> isset($config['notificar_justificativa_culto'])? (bool) $config['notificar_justificativa_culto']: true,
            'canal_email'                  => isset($config['canal_email'])    ? (bool) $config['canal_email']    : true,
            'canal_whatsapp'               => isset($config['canal_whatsapp']) ? (bool) $config['canal_whatsapp'] : true,
            'canal_push'                   => isset($config['canal_push'])     ? (bool) $config['canal_push']     : true,
        ],
    ]);
} else {
    echo json_encode(['status' => 'ok', 'configurado' => false, 'dados' => $default]);
}

$stmt->close();
$conn->close();
