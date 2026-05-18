<?php
/**
 * Helper de Autenticação Mobile
 * 
 * Este helper padroniza a autenticação mobile em todas as APIs.
 * Use este helper para garantir que todas as APIs suportem Bearer Token.
 * 
 * Uso:
 *   require_once __DIR__ . '/../../mobile/utils/auth_helper.php';
 *   mobile_require_auth();
 */

require_once __DIR__ . '/../../../core/middleware/mobile_auth.php';

/**
 * Exige autenticação (web ou mobile)
 * Retorna JSON de erro se não autenticado
 * 
 * @return bool True se autenticado, false caso contrário (já retornou JSON)
 */
function mobile_require_auth() {
    // Inicia sessão se necessário
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verifica se já está autenticado (sessão web)
    if (isset($_SESSION['usuario_id'])) {
        return true;
    }
    
    // Tenta autenticar via token mobile
    if (!MobileAuthMiddleware::handle()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(401);
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Usuário não autenticado. Token inválido ou ausente.'
        ]);
        exit;
    }
    
    return true;
}

/**
 * Obtém o ID do usuário autenticado
 * 
 * @return int ID do usuário ou 0 se não autenticado
 */
function mobile_get_user_id() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    return $_SESSION['usuario_id'] ?? 0;
}

/**
 * Configura headers CORS padrão
 */
function mobile_set_cors_headers() {
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    // Trata requisições OPTIONS (CORS preflight)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Obtém dados da requisição (suporta JSON e form-data)
 * 
 * @return array Dados da requisição
 */
function mobile_get_request_data() {
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($content_type, 'application/json') !== false) {
        // Requisição JSON (mobile)
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    } else {
        // Requisição form-data (web)
        return $_POST;
    }
}

?>
