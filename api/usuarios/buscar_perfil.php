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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

$id_usuario = $_SESSION['usuario_id'];

try {
    $stmt = $conn->prepare("
        SELECT u.id, u.nome, u.email, u.telefone, u.foto_base64, u.id_valor, u.entidade_id,
               gv.descricao AS grupo_nome, e.entidade_nome
        FROM usuarios u
        LEFT JOIN grupo_valor gv ON u.id_valor = gv.id
        LEFT JOIN entidade e ON u.entidade_id = e.entidade_id
        WHERE u.id = ?
    ");
    
    if (!$stmt) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro no prepare: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
        echo json_encode([
            'status' => 'ok',
            'usuario' => $usuario
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não encontrado']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro: ' . $e->getMessage()]);
}

$conn->close();
?>