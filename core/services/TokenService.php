<?php
/**
 * TokenService - Gerenciamento de tokens JWT para autenticação mobile
 * 
 * Este serviço gera e valida tokens JWT para autenticação via Bearer Token
 * no aplicativo mobile Flutter.
 * 
 * @version 1.0
 * @author Sistema de Presença AOM
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TokenService {
    
    /**
     * Chave secreta para assinatura dos tokens
     * IMPORTANTE: Em produção, deve ser uma chave forte e única
     */
    private static $secretKey = null;
    
    /**
     * Algoritmo de assinatura
     */
    private static $algorithm = 'HS256';
    
    /**
     * Tempo de expiração do token em segundos (padrão: 24 horas)
     */
    private static $expirationTime = 86400; // 24 horas
    
    /**
     * Tempo de expiração do refresh token em segundos (padrão: 7 dias)
     */
    private static $refreshExpirationTime = 604800; // 7 dias
    
    /**
     * Obtém a chave secreta
     * Tenta obter de variável de ambiente ou usa uma padrão
     */
    private static function getSecretKey() {
        if (self::$secretKey !== null) {
            return self::$secretKey;
        }
        
        // Tenta obter de variável de ambiente
        $envKey = getenv('JWT_SECRET_KEY');
        if ($envKey !== false && !empty($envKey)) {
            self::$secretKey = $envKey;
            return self::$secretKey;
        }
        
        // Usa uma chave padrão baseada no domínio (não recomendado para produção)
        // Em produção, defina JWT_SECRET_KEY no ambiente
        $domain = $_SERVER['HTTP_HOST'] ?? 'presenca.aom.org.br';
        self::$secretKey = hash('sha256', 'presenca_aom_jwt_' . $domain . '_2025');
        
        return self::$secretKey;
    }
    
    /**
     * Gera um token JWT para o usuário
     * 
     * @param int $userId ID do usuário
     * @param string $nome Nome do usuário
     * @param string $categoria Categoria do usuário (admin, funcionario, etc)
     * @param string $email Email do usuário
     * @return array Array com 'token' e 'refresh_token'
     */
    public static function generateToken($userId, $nome, $categoria, $email) {
        $now = time();
        $expiration = $now + self::$expirationTime;
        $refreshExpiration = $now + self::$refreshExpirationTime;
        
        // Payload do token principal
        $payload = [
            'iat' => $now, // Issued at
            'exp' => $expiration, // Expiration
            'user_id' => (int)$userId,
            'nome' => $nome,
            'categoria' => $categoria,
            'email' => $email,
            'type' => 'access'
        ];
        
        // Payload do refresh token
        $refreshPayload = [
            'iat' => $now,
            'exp' => $refreshExpiration,
            'user_id' => (int)$userId,
            'type' => 'refresh'
        ];
        
        try {
            $token = JWT::encode($payload, self::getSecretKey(), self::$algorithm);
            $refreshToken = JWT::encode($refreshPayload, self::getSecretKey(), self::$algorithm);
            
            return [
                'token' => $token,
                'refresh_token' => $refreshToken,
                'expires_in' => self::$expirationTime,
                'token_type' => 'Bearer'
            ];
        } catch (Exception $e) {
            error_log("Erro ao gerar token JWT: " . $e->getMessage());
            throw new Exception("Erro ao gerar token de autenticação");
        }
    }
    
    /**
     * Valida e decodifica um token JWT
     * 
     * @param string $token Token JWT
     * @return array|false Payload decodificado ou false se inválido
     */
    public static function validateToken($token) {
        try {
            error_log("TokenService::validateToken - Iniciando validação do token");
            error_log("TokenService::validateToken - Token recebido: " . substr($token, 0, 50) . "...");
            
            $secretKey = self::getSecretKey();
            error_log("TokenService::validateToken - Secret key obtida: " . (strlen($secretKey) > 0 ? 'SIM (' . strlen($secretKey) . ' chars)' : 'NÃO'));
            
            $decoded = JWT::decode($token, new Key($secretKey, self::$algorithm));
            error_log("TokenService::validateToken - Token decodificado com sucesso");
            
            // Converte objeto para array
            $payload = json_decode(json_encode($decoded), true);
            error_log("TokenService::validateToken - Payload: " . json_encode($payload));
            
            // Verifica se o token não expirou (JWT já faz isso, mas garantimos)
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                error_log("TokenService::validateToken - Token expirado. Exp: " . $payload['exp'] . ", Now: " . time());
                return false;
            }
            
            error_log("TokenService::validateToken - Token válido para usuário ID: " . ($payload['user_id'] ?? 'N/A'));
            return $payload;
        } catch (Exception $e) {
            error_log("TokenService::validateToken - ERRO: " . $e->getMessage());
            error_log("TokenService::validateToken - Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Valida um refresh token e gera um novo token de acesso
     * 
     * @param string $refreshToken Refresh token
     * @param mysqli $conn Conexão com banco de dados
     * @return array|false Novo token ou false se inválido
     */
    public static function refreshAccessToken($refreshToken, $conn) {
        $payload = self::validateToken($refreshToken);
        
        if (!$payload || $payload['type'] !== 'refresh') {
            return false;
        }
        
        // Busca dados atualizados do usuário
        $userId = $payload['user_id'];
        $stmt = $conn->prepare("SELECT id, nome, categoria, email FROM usuarios WHERE id = ? AND ativo = 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Gera novo token
        return self::generateToken(
            $user['id'],
            $user['nome'],
            $user['categoria'],
            $user['email']
        );
    }
    
    /**
     * Extrai o token do header Authorization
     * 
     * @return string|false Token ou false se não encontrado
     */
    public static function extractTokenFromHeader() {
        $headers = null;
        
        // Debug: Log todos os headers disponíveis
        error_log("TokenService::extractTokenFromHeader - Verificando headers...");
        
        // Método 1: Verifica $_SERVER['Authorization'] (alguns servidores)
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
            error_log("Token encontrado em _SERVER['Authorization']: " . substr($headers, 0, 20) . "...");
        }
        // Método 2: Verifica $_SERVER['HTTP_AUTHORIZATION'] (Apache com mod_rewrite)
        elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
            error_log("Token encontrado em _SERVER['HTTP_AUTHORIZATION']: " . substr($headers, 0, 20) . "...");
        }
        // Método 3: Usa apache_request_headers() se disponível
        elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if ($requestHeaders) {
                error_log("apache_request_headers disponível. Headers: " . json_encode(array_keys($requestHeaders)));
                
                // Tenta diferentes variações do nome do header
                if (isset($requestHeaders['Authorization'])) {
                    $headers = trim($requestHeaders['Authorization']);
                    error_log("Token encontrado em apache_request_headers['Authorization']: " . substr($headers, 0, 20) . "...");
                } elseif (isset($requestHeaders['authorization'])) {
                    $headers = trim($requestHeaders['authorization']);
                    error_log("Token encontrado em apache_request_headers['authorization']: " . substr($headers, 0, 20) . "...");
                } elseif (isset($requestHeaders['AUTHORIZATION'])) {
                    $headers = trim($requestHeaders['AUTHORIZATION']);
                    error_log("Token encontrado em apache_request_headers['AUTHORIZATION']: " . substr($headers, 0, 20) . "...");
                }
            }
        }
        // Método 4: Tenta obter via getallheaders() (função auxiliar)
        elseif (function_exists('getallheaders')) {
            $allHeaders = getallheaders();
            if ($allHeaders) {
                error_log("getallheaders disponível. Headers: " . json_encode(array_keys($allHeaders)));
                
                // Tenta diferentes variações do nome do header
                foreach (['Authorization', 'authorization', 'AUTHORIZATION'] as $headerName) {
                    if (isset($allHeaders[$headerName])) {
                        $headers = trim($allHeaders[$headerName]);
                        error_log("Token encontrado em getallheaders['$headerName']: " . substr($headers, 0, 20) . "...");
                        break;
                    }
                }
            }
        }
        // Método 5: Tenta obter via REDIRECT_HTTP_AUTHORIZATION (Apache com mod_rewrite)
        elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
            error_log("Token encontrado em _SERVER['REDIRECT_HTTP_AUTHORIZATION']: " . substr($headers, 0, 20) . "...");
        }
        
        if (!$headers) {
            error_log("TokenService::extractTokenFromHeader - NENHUM HEADER Authorization encontrado!");
            error_log("_SERVER keys relacionados: " . json_encode(array_filter(array_keys($_SERVER), function($k) {
                return stripos($k, 'auth') !== false || stripos($k, 'authorization') !== false;
            })));
            return false;
        }
        
        // Verifica se é Bearer token
        if (preg_match('/Bearer\s+(.*)$/i', $headers, $matches)) {
            $token = trim($matches[1]);
            error_log("Token Bearer extraído com sucesso! Tamanho: " . strlen($token) . " chars");
            return $token;
        }
        
        error_log("TokenService::extractTokenFromHeader - Header encontrado mas não é Bearer token: " . substr($headers, 0, 50));
        return false;
    }
    
    /**
     * Cria uma sessão PHP a partir de um token válido
     * 
     * @param string $token Token JWT
     * @return bool True se sessão foi criada com sucesso
     */
    public static function createSessionFromToken($token) {
        $payload = self::validateToken($token);
        
        if (!$payload || $payload['type'] !== 'access') {
            return false;
        }
        
        // Inicia sessão se não estiver ativa
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Cria variáveis de sessão compatíveis com o sistema atual
        $_SESSION['usuario_id'] = $payload['user_id'];
        $_SESSION['usuario_nome'] = $payload['nome'];
        $_SESSION['usuario_categoria'] = $payload['categoria'];
        $_SESSION['usuario_email'] = $payload['email'];
        $_SESSION['mobile_token'] = true; // Flag para identificar que veio de token mobile
        
        return true;
    }
    
    /**
     * Define o tempo de expiração do token
     * 
     * @param int $seconds Segundos até expiração
     */
    public static function setExpirationTime($seconds) {
        self::$expirationTime = $seconds;
    }
    
    /**
     * Define o tempo de expiração do refresh token
     * 
     * @param int $seconds Segundos até expiração
     */
    public static function setRefreshExpirationTime($seconds) {
        self::$refreshExpirationTime = $seconds;
    }
}
