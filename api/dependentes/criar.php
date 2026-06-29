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

if (!$isAdmin) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado']);
    exit;
}

try {
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
    
    $usuario_id = $input_data['usuario_id'] ?? $_SESSION['usuario_id'] ?? null;
    $nome = $input_data['nome'] ?? null;
    $parentesco = $input_data['parentesco'] ?? null;
    $nascimento = $input_data['nascimento_dependente'] ?? $input_data['nascimento'] ?? null;
    
    if (!$usuario_id || !$nome || !$parentesco || !$nascimento) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dados obrigatórios não fornecidos']);
        exit;
    }
    
    // Processar foto se fornecida (pode vir como base64 do mobile ou como arquivo do web)
    $foto_base64 = null;
    
    // Verificar se veio como base64 (mobile)
    if (isset($input_data['foto_base64']) && !empty($input_data['foto_base64'])) {
        $foto_base64 = $input_data['foto_base64'];
        // Remover prefixo data:image se existir
        if (strpos($foto_base64, 'data:image/') === 0) {
            $foto_base64 = substr($foto_base64, strpos($foto_base64, ',') + 1);
        }
    }
    // Verificar se veio como arquivo (web)
    elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $foto_tmp = $_FILES['foto']['tmp_name'];
        $foto_data = file_get_contents($foto_tmp);
        $foto_base64 = base64_encode($foto_data);
    }
    
    // Regra centralizada em DependenteService — idade-limite vem da config
    // 'idade_isencao_dependente' (default 12). Nunca duplicar regra hardcoded.
    require_once __DIR__ . '/../../core/services/DependenteService.php';
    $cobrar = DependenteService::calcularCobrar($conn, $nascimento) ?? 0;
    
    // Inserir dependente
    $sql = "INSERT INTO dependentes (id_usuario, nome, parentesco, nascimento, foto_base64, cobrar) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssi", $usuario_id, $nome, $parentesco, $nascimento, $foto_base64, $cobrar);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Dependente criado com sucesso',
            'id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao criar dependente: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}

    ?>
