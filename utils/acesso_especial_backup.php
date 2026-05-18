<?php
/**
 * Utilitário para verificar acesso especial
 * Permite que o usuário tlinhares@gmail.com tenha acesso especial além de admin
 */

/**
 * Verifica se o usuário atual tem acesso especial
 * @return bool
 */
function tem_acesso_especial() {
    static $resultado_cache = null;
    
    if ($resultado_cache !== null) {
        return $resultado_cache;
    }
    
    if (!isset($_SESSION['usuario_id'])) {
        $resultado_cache = false;
        return false;
    }
    
    try {
        // Verificar se o email do usuário é tlinhares@gmail.com
        $email_especial = 'tlinhares@gmail.com';
        
        // Usar conexão global se disponível
        global $conn;
        
        // Se não há conexão global, incluir o arquivo
        if (!isset($conn) || !$conn) {
            require_once __DIR__ . '/../api/conexao.php';
        }
        
        // Verificar se a conexão foi estabelecida
        if (!isset($conn) || !$conn) {
            error_log("Erro: Conexão com banco não estabelecida");
            $resultado_cache = false;
            return false;
        }
        
        $stmt = $conn->prepare("SELECT email FROM usuarios WHERE id = ?");
        if (!$stmt) {
            error_log("Erro ao preparar statement: " . $conn->error);
            $resultado_cache = false;
            return false;
        }
        
        $stmt->bind_param("i", $_SESSION['usuario_id']);
        $stmt->execute();
        $stmt->bind_result($email);
        $stmt->fetch();
        $stmt->close();
        
        $resultado_cache = ($email === $email_especial);
        return $resultado_cache;
    } catch (Exception $e) {
        error_log("Erro em tem_acesso_especial: " . $e->getMessage());
        $resultado_cache = false;
        return false;
    }
}

/**
 * Verifica se o usuário pode acessar funcionalidades especiais
 * @return bool
 */
function pode_acessar_especial() {
    if (!isset($_SESSION['usuario_categoria']) || $_SESSION['usuario_categoria'] !== 'admin') {
        return false;
    }
    
    return tem_acesso_especial();
}

/**
 * Redireciona se não tiver acesso especial
 */
function verificar_acesso_especial() {
    if (!pode_acessar_especial()) {
        header('Location: ../painel/index.php');
        exit;
    }
}
?>
