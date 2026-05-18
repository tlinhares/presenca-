<?php
/**
 * Session - Classe para gerenciamento de sessões
 */
class Session {
    /**
     * Inicia a sessão se ainda não estiver iniciada
     */
    public static function start() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Define um valor na sessão
     * 
     * @param string $key Chave
     * @param mixed $value Valor
     */
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    /**
     * Obtém um valor da sessão
     * 
     * @param string $key Chave
     * @param mixed $default Valor padrão caso a chave não exista
     * @return mixed
     */
    public static function get($key, $default = null) {
        self::start();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }
    
    /**
     * Verifica se uma chave existe na sessão
     * 
     * @param string $key Chave
     * @return bool
     */
    public static function has($key) {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove um valor da sessão
     * 
     * @param string $key Chave
     */
    public static function remove($key) {
        self::start();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * Define uma mensagem flash (disponível apenas para a próxima requisição)
     * 
     * @param string $type Tipo da mensagem (success, error, warning, info)
     * @param string $message Conteúdo da mensagem
     */
    public static function setFlash($type, $message) {
        self::start();
        $_SESSION['flash'][$type] = $message;
    }
    
    /**
     * Obtém e remove mensagens flash
     * 
     * @return array Mensagens flash
     */
    public static function getFlash() {
        self::start();
        $flash = isset($_SESSION['flash']) ? $_SESSION['flash'] : [];
        unset($_SESSION['flash']);
        return $flash;
    }
    
    /**
     * Destrói a sessão atual
     */
    public static function destroy() {
        self::start();
        session_destroy();
        $_SESSION = [];
    }
}
?>
