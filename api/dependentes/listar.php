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
        echo json_encode([
            'status' => 'erro', 
            'mensagem' => 'Usuário não autenticado. Token inválido ou ausente.'
        ]);
        exit;
    }
}

// Verificar se foi passado usuario_id via GET ou usar da sessão
$id_usuario = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 0);

if (!$id_usuario) {
    echo json_encode([
        'status' => 'erro', 
        'mensagem' => 'ID do usuário não fornecido'
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT id, nome, parentesco, nascimento, foto_base64 FROM dependentes WHERE id_usuario = ? AND ativo = 1 ORDER BY nome");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$stmt->bind_result($id, $nome, $parentesco, $nascimento, $foto_base64);

$dependentes = [];
while ($stmt->fetch()) {
    // Calcular idade
    $idade = '';
    if ($nascimento) {
        $nascimento_date = new DateTime($nascimento);
        $hoje = new DateTime();
        $idade = $nascimento_date->diff($hoje)->y;
    }
    
    $dependentes[] = [
        'id' => $id,
        'nome' => $nome,
        'parentesco' => $parentesco,
        'data_nascimento' => $nascimento,
        'idade' => $idade,
        'foto_base64' => $foto_base64
    ];
}

echo json_encode([
    'status' => 'ok',
    'dados' => $dependentes
]);

$stmt->close();
$conn->close();
?>

