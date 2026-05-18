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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
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
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Usuário não autenticado. Token inválido ou ausente.'
        ]);
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

$foto = $input_data['foto'] ?? $input_data['foto_base64'] ?? '';

try {
    if (empty($foto)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Foto não fornecida']);
        exit;
    }

    // Processar foto base64
    $foto_base64 = $foto;
    if (strpos($foto, 'data:image/') === 0) {
        $foto_base64 = substr($foto, strpos($foto, ',') + 1);
    }

    // Atualizar foto no banco
    $stmt = $conn->prepare("UPDATE usuarios SET foto_base64 = ? WHERE id = ?");
    $stmt->bind_param("si", $foto_base64, $_SESSION['usuario_id']);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Foto atualizada com sucesso',
            'foto_base64' => $foto_base64
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar foto']);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao atualizar foto: ' . $e->getMessage()
    ]);
}
?>
