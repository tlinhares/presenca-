<?php
/**
 * Auth - Classe para autenticação de usuários
 */
class Auth {
    /**
     * Verifica se o usuário está autenticado
     * 
     * @return bool
     */
    public static function isLoggedIn() {
        return Session::has('usuario_id');
    }
    
    /**
     * Autentica um usuário
     * 
     * @param int $id ID do usuário
     * @param string $nome Nome do usuário
     * @param int $perfil_id ID do perfil de acesso
     * @return bool
     */
    public static function login($id, $nome, $perfil_id) {
        Session::set('usuario_id', $id);
        Session::set('usuario_nome', $nome);
        Session::set('usuario_perfil_id', $perfil_id);
        return true;
    }
    
    /**
     * Encerra a sessão do usuário
     * 
     * @return bool
     */
    public static function logout() {
        Session::destroy();
        return true;
    }
    
    /**
     * Obtém o ID do usuário autenticado
     * 
     * @return int|null
     */
    public static function getUserId() {
        return Session::get('usuario_id');
    }
    
    /**
     * Obtém o nome do usuário autenticado
     * 
     * @return string|null
     */
    public static function getUserName() {
        return Session::get('usuario_nome');
    }
    
    /**
     * Obtém o ID do perfil do usuário autenticado
     * 
     * @return int|null
     */
    public static function getUserProfileId() {
        return Session::get('usuario_perfil_id');
    }
    
    /**
     * Verifica se o usuário tem permissão para acessar determinada área
     * 
     * @param array $allowed_profiles Array com IDs de perfis permitidos
     * @return bool
     */
    public static function hasPermission($allowed_profiles) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        $profile_id = self::getUserProfileId();
        return in_array($profile_id, $allowed_profiles);
    }
    
    /**
     * Verifica a senha fornecida com o hash armazenado
     * 
     * @param string $password Senha em texto plano
     * @param string $hash Hash armazenado
     * @return bool
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Gera um hash seguro para uma senha
     * 
     * @param string $password Senha em texto plano
     * @return string Hash da senha
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
?>
