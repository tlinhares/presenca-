<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once(__DIR__ . '/../conexao.php');

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
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não logado']);
        exit;
    }
}

$id_usuario = $_SESSION['usuario_id'];
$dataHoje = date('Y-m-d');
$horaAtual = date('H:i');

// Verificar se já reservou hoje
$stmt = $conn->prepare("SELECT id FROM reservas_almoco WHERE id_usuario = ? AND data = ?");
$stmt->bind_param("is", $id_usuario, $dataHoje);
$stmt->execute();
$stmt->store_result();

$reservou = $stmt->num_rows > 0;

// Verificar horário limite (apenas para informação, não bloqueia mais)
$hora_limite = '09:00';
$res = $conn->query("SELECT valor FROM configuracoes WHERE chave = 'hora_limite' LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $hora_limite = $row['valor'];
}
$hora_excedida = ($horaAtual > $hora_limite);

echo json_encode([
    'reservou_hoje' => $reservou,
    'hora_excedida' => $hora_excedida,
    'hora_atual' => $horaAtual,
    'hora_limite' => $hora_limite
]);
?>
