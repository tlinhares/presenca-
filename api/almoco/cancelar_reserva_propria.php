<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once(__DIR__ . '/../conexao.php');
include_once(__DIR__ . '/../../utils/config.php');

// Inicia sessão ANTES do middleware (compatível com web)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Middleware mobile: converte Bearer Token em sessão PHP se necessário
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

// Verifica autenticação (web ou mobile)
if (!isset($_SESSION['usuario_id'])) {
    // Tenta autenticar via token mobile
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
        exit;
    }
}

$id_usuario = $_SESSION['usuario_id'];

// Aceita tanto JSON (mobile) quanto form-data (web)
$input_data = [];
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($content_type, 'application/json') !== false) {
    // Requisição JSON (mobile)
    $input = file_get_contents('php://input');
    $input_data = json_decode($input, true) ?? [];
} else {
    // Requisição form-data (web)
    $input_data = $_POST;
}

$data = $input_data['data'] ?? '';

if (empty($data)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Data não informada']);
    exit;
}

// Validar formato da data
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Formato de data inválido']);
    exit;
}

// Verificar se a reserva existe
$stmt = $conn->prepare("SELECT id FROM reservas_almoco WHERE id_usuario = ? AND data = ?");
$stmt->bind_param("is", $id_usuario, $data);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Reserva não encontrada']);
    exit;
}

// Verificar se pode cancelar (mesma lógica do listar)
$hoje = date('Y-m-d');
$hora_limite = get_config('hora_limite', '09:00');
$hora_atual = date('H:i');

$pode_cancelar = false;
if ($data == $hoje) {
    // Para hoje, verificar se ainda está dentro do horário limite
    $pode_cancelar = ($hora_atual <= $hora_limite);
} elseif ($data > $hoje) {
    // Para datas futuras, sempre pode cancelar
    $pode_cancelar = true;
}

if (!$pode_cancelar) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Não é mais possível cancelar esta reserva']);
    exit;
}

// Cancelar a reserva
$stmt = $conn->prepare("DELETE FROM reservas_almoco WHERE id_usuario = ? AND data = ?");
$stmt->bind_param("is", $id_usuario, $data);

if ($stmt->execute()) {
    // Sinaliza fila facial: cancela pendentes e marca sincronizados para remoção.
    require_once __DIR__ . '/../../core/services/FacialService.php';
    FacialService::onReservaCancelada($conn, (int) $id_usuario, 'usuario', $data);

    // Enviar notificação se habilitada
    require_once __DIR__ . '/../notificacao/enviar_notificacao_reserva.php';
    $horario_atual = date('H:i');
    $dados_notificacao = [
        'data' => date('d/m/Y', strtotime($data)),
        'horario' => $horario_atual,
        'tipo_reserva' => 'própria'
    ];
    enviarNotificacaoReserva($id_usuario, 'cancelada', $dados_notificacao, $conn);

    echo json_encode(['status' => 'ok', 'mensagem' => 'Reserva cancelada com sucesso']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao cancelar reserva']);
}

$stmt->close();
$conn->close();
?>
