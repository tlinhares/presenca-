<?php
/**
 * Mobile Auth Middleware - Converte Bearer Token em Sessão PHP
 * 
 * Este middleware permite que requisições mobile com Bearer Token
 * sejam convertidas em sessões PHP compatíveis com o sistema atual.
 * 
 * Uso:
 *   require_once __DIR__ . '/../../core/middleware/mobile_auth.php';
 *   MobileAuthMiddleware::handle();
 * 
 * @version 1.0
 * @author Sistema de Presença AOM
 */

require_once __DIR__ . '/../services/TokenService.php';

class MobileAuthMiddleware {
    
    /**
     * Processa a autenticação mobile e cria sessão PHP se necessário
     * 
     * @return bool True se autenticado, false caso contrário
     */
    public static function handle() {
        // Verifica se já existe sessão ativa (requisição web normal)
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['usuario_id'])) {
            error_log("MobileAuthMiddleware::handle - Sessão web já ativa, usuário ID: " . $_SESSION['usuario_id']);
            return true;
        }
        
        // Tenta extrair token do header Authorization
        error_log("MobileAuthMiddleware::handle - Tentando extrair token do header...");
        $token = TokenService::extractTokenFromHeader();
        
        if (!$token) {
            error_log("MobileAuthMiddleware::handle - Token não encontrado no header Authorization");
            return false;
        }
        
        error_log("MobileAuthMiddleware::handle - Token extraído, validando...");
        
        // Valida token e cria sessão
        if (TokenService::createSessionFromToken($token)) {
            error_log("MobileAuthMiddleware::handle - Token válido, sessão criada para usuário ID: " . $_SESSION['usuario_id']);
            return true;
        }
        
        error_log("MobileAuthMiddleware::handle - Token inválido ou expirado");
        return false;
    }
    
    /**
     * Verifica se a requisição é de origem mobile
     * 
     * @return bool
     */
    public static function isMobileRequest() {
        // Verifica header User-Agent comum de apps mobile
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Verifica se tem token Bearer
        $hasToken = TokenService::extractTokenFromHeader() !== false;
        
        // Verifica flag de sessão mobile
        $isMobileSession = isset($_SESSION['mobile_token']) && $_SESSION['mobile_token'] === true;
        
        return $hasToken || $isMobileSession;
    }
    
    /**
     * Exige autenticação mobile
     * Retorna erro JSON se não autenticado
     * 
     * @param bool $returnJson Se true, retorna JSON e encerra. Se false, retorna bool
     * @return bool True se autenticado
     */
    public static function requireAuth($returnJson = true) {
        if (!self::handle()) {
            if ($returnJson) {
                header('Content-Type: application/json; charset=UTF-8');
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Não autorizado. Token inválido ou ausente.',
                    'timestamp' => date('c')
                ]);
                exit;
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtém o ID do usuário autenticado (mobile ou web)
     * 
     * @return int|null
     */
    public static function getUserId() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        return $_SESSION['usuario_id'] ?? null;
    }
    
    /**
     * Obtém dados do usuário autenticado
     * 
     * @return array|null
     */
    public static function getUser() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        if (!isset($_SESSION['usuario_id'])) {
            return null;
        }
        
        return [
            'id' => $_SESSION['usuario_id'],
            'nome' => $_SESSION['usuario_nome'] ?? null,
            'categoria' => $_SESSION['usuario_categoria'] ?? null,
            'email' => $_SESSION['usuario_email'] ?? null
        ];
    }
}
