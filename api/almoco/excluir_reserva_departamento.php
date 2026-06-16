<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/../conexao.php');
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

// Aceita JSON (mobile) ou form-data (web)
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($content_type, 'application/json') !== false) {
    $_POST = json_decode(file_get_contents('php://input'), true) ?: [];
}

if (!isset($_SESSION['usuario_id'])) {
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
        exit;
    }
}

// Verificar se é admin
$isAdmin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';

if (!$isAdmin) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado - apenas administradores podem excluir reservas de departamento']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

try {
    $id = $_POST['id'] ?? '';
    
    if (empty($id) || !is_numeric($id)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID da reserva inválido']);
        exit;
    }
    
    // Verificar se a reserva existe
    $stmt_check = $conn->prepare("SELECT id FROM reservas_departamento WHERE id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Reserva não encontrada']);
        exit;
    }
    
    $stmt_check->close();
    
    // Excluir reserva (soft delete - marcar como inativo)
    $stmt = $conn->prepare("UPDATE reservas_departamento SET status = 'inativo' WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'status' => 'ok',
                'mensagem' => 'Reserva excluída com sucesso'
            ]);
        } else {
            echo json_encode([
                'status' => 'erro',
                'mensagem' => 'Erro ao excluir reserva'
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao excluir reserva: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
