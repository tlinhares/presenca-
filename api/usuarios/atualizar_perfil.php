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

$nome = trim($input_data['nome'] ?? '');
$email = trim($input_data['email'] ?? '');
$telefone = trim($input_data['telefone'] ?? '');
$nova_senha = $input_data['senha'] ?? '';
$confirmar_senha = $input_data['senha_confirma'] ?? '';
$foto_base64 = $input_data['foto_base64'] ?? '';

if (empty($nome) || empty($email)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Nome e email são obrigatórios']);
    exit;
}

// Validar email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Email inválido']);
    exit;
}

// Verificar se email já existe para outro usuário
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
$stmt->bind_param("si", $email, $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Email já está sendo usado por outro usuário']);
    exit;
}
$stmt->close();

try {
    // Construir query dinamicamente
    $sql = "UPDATE usuarios SET nome = ?, email = ?, telefone = ?";
    $params = [$nome, $email, $telefone];
    $types = "sss";
    
    // Adicionar senha se fornecida
    if (!empty($nova_senha)) {
        if ($nova_senha !== $confirmar_senha) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'As senhas não coincidem']);
            exit;
        }
        if (strlen($nova_senha) < 6) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'A senha deve ter pelo menos 6 caracteres']);
            exit;
        }
        $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $sql .= ", senha = ?";
        $params[] = $senha_hash;
        $types .= "s";
    }
    
    // Adicionar foto apenas se fornecida
    if (!empty($foto_base64)) {
        // Remover prefixo data:image se existir
        if (strpos($foto_base64, 'data:image/') === 0) {
            $foto_base64 = substr($foto_base64, strpos($foto_base64, ',') + 1);
        }
        
        $sql .= ", foto_base64 = ?";
        $params[] = $foto_base64;
        $types .= "s";
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $id_usuario;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao preparar query: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'ok', 'mensagem' => 'Perfil atualizado com sucesso']);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar perfil: ' . $stmt->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro: ' . $e->getMessage()]);
}

$conn->close();
?>
