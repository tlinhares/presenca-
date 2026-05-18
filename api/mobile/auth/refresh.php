<?php
/**
 * API Mobile - Renovação de Token
 * 
 * Endpoint: POST /api/mobile/auth/refresh.php
 * Authorization: Bearer <refresh_token>
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
 *     "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
 *     "expires_in": 86400,
 *     "token_type": "Bearer"
 *   },
 *   "message": "Token renovado com sucesso"
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

require_once __DIR__ . '/../../../api/conexao.php';
require_once __DIR__ . '/../../../core/services/TokenService.php';
require_once __DIR__ . '/../utils/response.php';

// Aceita apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(MobileResponse::error('Método não permitido', 405));
    exit;
}

try {
    // Obtém refresh token do header ou body
    $refreshToken = null;
    
    // Tenta obter do header Authorization
    $authHeader = TokenService::extractTokenFromHeader();
    if ($authHeader) {
        $refreshToken = $authHeader;
    } else {
        // Tenta obter do body JSON
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if ($data && isset($data['refresh_token'])) {
            $refreshToken = $data['refresh_token'];
        }
    }
    
    if (!$refreshToken) {
        echo json_encode(MobileResponse::error('Refresh token não fornecido', 401));
        exit;
    }
    
    // Renova o token
    $tokens = TokenService::refreshAccessToken($refreshToken, $conn);
    
    if (!$tokens) {
        echo json_encode(MobileResponse::error('Refresh token inválido ou expirado', 401));
        exit;
    }
    
    echo json_encode(MobileResponse::success($tokens, 'Token renovado com sucesso'));
    
} catch (Exception $e) {
    error_log("Erro ao renovar token: " . $e->getMessage());
    echo json_encode(MobileResponse::error('Erro interno do servidor', 500));
}

$conn->close();
