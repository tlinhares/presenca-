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

require_once __DIR__ . '/../conexao.php';

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

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

$reserva_id = (int)($input_data['id'] ?? 0);

try {
    if ($reserva_id <= 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID da reserva inválido']);
        exit;
    }

    // Verificar se a reserva pertence ao usuário (através do dependente)
    $stmt = $conn->prepare("SELECT ra.id FROM reservas_adicionais ra 
                           INNER JOIN dependentes d ON ra.id_dependente = d.id 
                           WHERE ra.id = ? AND d.id_usuario = ?");
    $stmt->bind_param("ii", $reserva_id, $_SESSION['usuario_id']);
    $stmt->execute();
    
    if (!$stmt->get_result()->fetch_row()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Reserva não encontrada']);
        exit;
    }
    $stmt->close();

    // Excluir reserva adicional
    $stmt = $conn->prepare("DELETE FROM reservas_adicionais WHERE id = ?");
    $stmt->bind_param("i", $reserva_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Reserva adicional excluída com sucesso'
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao excluir reserva adicional']);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao excluir reserva adicional: ' . $e->getMessage()
    ]);
}
?>
