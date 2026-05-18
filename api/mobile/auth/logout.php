<?php
/**
 * API Mobile - Logout
 * 
 * Endpoint: POST /api/mobile/auth/logout.php
 * Authorization: Bearer <token>
 * 
 * Nota: Como JWT é stateless, o logout é principalmente do lado do cliente.
 * Este endpoint pode ser usado para invalidar tokens em uma blacklist (futuro)
 * ou apenas confirmar o logout.
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Logout realizado com sucesso"
 * }
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../core/services/TokenService.php';
require_once __DIR__ . '/../utils/response.php';

// Aceita apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(MobileResponse::error('Método não permitido', 405));
    exit;
}

try {
    // Valida token (opcional, mas útil para logging)
    $token = TokenService::extractTokenFromHeader();
    if ($token) {
        $payload = TokenService::validateToken($token);
        if ($payload) {
            // Aqui poderia adicionar o token a uma blacklist se necessário
            // Por enquanto, apenas confirma o logout
            error_log("Logout mobile - Usuário ID: " . $payload['user_id']);
        }
    }
    
    echo json_encode(MobileResponse::success(null, 'Logout realizado com sucesso'));
    
} catch (Exception $e) {
    error_log("Erro no logout mobile: " . $e->getMessage());
    echo json_encode(MobileResponse::error('Erro interno do servidor', 500));
}
