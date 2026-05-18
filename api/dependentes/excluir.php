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

// Verificar se é admin
$isAdmin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
$usuarioLogadoId = intval($_SESSION['usuario_id']);

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

$id = $input_data['id'] ?? '';

try {
    // Validar dados
    if (empty($id) || !is_numeric($id)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID do dependente inválido']);
        exit;
    }

    // Verificar se o dependente existe e obter o id_usuario para verificação de permissão
    $stmt = $conn->prepare("SELECT id, id_usuario FROM dependentes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dependente não encontrado']);
        exit;
    }
    
    $depRow = $result->fetch_assoc();
    $stmt->close();
    
    // Verificar permissão: admin pode excluir qualquer dependente, usuário normal só os seus
    if (!$isAdmin && intval($depRow['id_usuario']) !== $usuarioLogadoId) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado - você só pode excluir seus próprios dependentes']);
        exit;
    }

    // Excluir dependente (soft delete - marcar como inativo)
    $stmt = $conn->prepare("UPDATE dependentes SET ativo = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'status' => 'ok',
                'mensagem' => 'Dependente excluído com sucesso'
            ]);
        } else {
            echo json_encode([
                'status' => 'erro',
                'mensagem' => 'Erro ao excluir dependente'
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao excluir dependente'
        ]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao excluir dependente: ' . $e->getMessage()
    ]);
}

?>