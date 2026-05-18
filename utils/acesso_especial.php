<?php
// utils/acesso_especial.php

/**
 * Verifica se o usuário pode acessar funcionalidades especiais
 * @return bool
 */
function pode_acessar_especial() {
    // Verificar se a sessão está ativa
    if (!isset($_SESSION['usuario_id'])) {
        return false;
    }
    
    // Verificar se é administrador
    if (!isset($_SESSION['usuario_categoria']) || $_SESSION['usuario_categoria'] !== 'admin') {
        return false;
    }
    
    // Verificar se o email do usuário está na lista de emails_acesso_especial
    try {
        global $conn;
        
        // Se não há conexão global, incluir o arquivo
        if (!isset($conn) || !$conn) {
            require_once __DIR__ . '/../api/conexao.php';
        }
        
        // Buscar emails com acesso especial
        $stmt = $conn->prepare("SELECT valor FROM configuracoes WHERE chave = 'emails_acesso_especial'");
        if (!$stmt) {
            return false;
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return false;
        }
        
        $config = $result->fetch_assoc();
        $stmt->close();
        
        $emails_especiais = explode(',', $config['valor']);
        $emails_especiais = array_map('trim', $emails_especiais);
        
        // Buscar email do usuário atual
        $stmt = $conn->prepare("SELECT email FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['usuario_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return false;
        }
        
        $usuario = $result->fetch_assoc();
        $stmt->close();
        
        return in_array($usuario['email'], $emails_especiais);
        
    } catch (Exception $e) {
        error_log("Erro em pode_acessar_especial: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica se o usuário tem permissão para acessar uma funcionalidade específica
 * @param string $funcionalidade
 * @return bool
 */
function tem_permissao($funcionalidade) {
    if (!pode_acessar_especial()) {
        return false;
    }
    
    // Lista de funcionalidades especiais
    $funcionalidades_especiais = [
        'acesso_especial',
        'gerenciar_usuarios',
        'configuracoes_avancadas',
        'relatorios_completos'
    ];
    
    return in_array($funcionalidade, $funcionalidades_especiais);
}
?>

