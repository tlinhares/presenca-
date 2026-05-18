<?php
/**
 * Endpoint de teste para verificar se o token está sendo recebido e processado corretamente
 * 
 * Uso: GET /api/test_token.php
 * Headers: Authorization: Bearer <token>
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/middleware/mobile_auth.php';
require_once __DIR__ . '/../core/services/TokenService.php';

$debug = [];

// 1. Verificar headers recebidos
$debug['headers_recebidos'] = getallheaders();
$debug['server_authorization'] = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : 'NÃO ENCONTRADO';
$debug['server_authorization_direct'] = isset($_SERVER['Authorization']) ? $_SERVER['Authorization'] : 'NÃO ENCONTRADO';

// 2. Tentar extrair token
$token = TokenService::extractTokenFromHeader();
$debug['token_extraido'] = $token ? 'SIM (' . strlen($token) . ' chars)' : 'NÃO';
if ($token) {
    $debug['token_preview'] = substr($token, 0, 50) . '...';
}

// 3. Verificar sessão atual
$debug['sessao_atual'] = [
    'status' => session_status() === PHP_SESSION_ACTIVE ? 'ATIVA' : 'INATIVA',
    'usuario_id' => $_SESSION['usuario_id'] ?? 'NÃO DEFINIDO',
];

// 4. Tentar autenticar via middleware
$debug['middleware_result'] = MobileAuthMiddleware::handle() ? 'SUCESSO' : 'FALHOU';

// 5. Verificar sessão após middleware
$debug['sessao_apos_middleware'] = [
    'usuario_id' => $_SESSION['usuario_id'] ?? 'NÃO DEFINIDO',
    'usuario_nome' => $_SESSION['usuario_nome'] ?? 'NÃO DEFINIDO',
];

// 6. Se token foi extraído, tentar validar
if ($token) {
    $payload = TokenService::validateToken($token);
    $debug['validacao_token'] = $payload ? 'VÁLIDO' : 'INVÁLIDO';
    if ($payload) {
        $debug['payload'] = $payload;
    }
}

// 7. Verificar secret key
$debug['secret_key'] = [
    'existe' => strlen(TokenService::getSecretKey()) > 0 ? 'SIM' : 'NÃO',
    'tamanho' => strlen(TokenService::getSecretKey()),
];

echo json_encode([
    'status' => 'ok',
    'debug' => $debug,
    'mensagem' => 'Endpoint de teste executado. Verifique o campo debug para detalhes.',
    'autenticado' => isset($_SESSION['usuario_id']) && $_SESSION['usuario_id'] > 0
], JSON_PRETTY_PRINT);

?>
