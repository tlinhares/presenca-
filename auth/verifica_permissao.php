<?php
/**
 * verifica_permissao.php - Wrapper para verificação de permissões por módulo
 * 
 * IMPORTANTE: Este arquivo deve ser incluído APÓS o verifica_sessao.php
 * 
 * Uso:
 *   require_once '../auth/verifica_sessao.php';
 *   require_once '../auth/verifica_permissao.php';
 *   verificar_permissao_modulo('refeicoes');
 * 
 * @version 1.0
 * @see /docs/SISTEMA_PERMISSOES.md
 */

// Garante que a sessão está ativa
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Inclui o serviço de permissões
require_once __DIR__ . '/../core/services/PermissaoService.php';

/**
 * Verifica se o usuário tem permissão para acessar o módulo
 * Redireciona para index se não tiver acesso
 * 
 * @param string $codigo_modulo Código do módulo (gerenciamento, refeicoes, culto, etc)
 * @param int $nivel_minimo Nível mínimo de permissão (padrão: VISUALIZAR = 1)
 * @param string|null $redirect_url URL para redirecionar se não tiver acesso
 */
function verificar_permissao_modulo($codigo_modulo, $nivel_minimo = 1, $redirect_url = null) {
    // Determina URL de redirecionamento
    if ($redirect_url === null) {
        // Tenta voltar para o painel ou index
        $redirect_url = '../index.php';
        
        // Se estiver em subpasta específica, ajusta o caminho
        $script_path = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($script_path, '/painel/') !== false) {
            $redirect_url = '../index.php';
        } elseif (strpos($script_path, '/culto/') !== false) {
            $redirect_url = '../index.php';
        } elseif (strpos($script_path, '/reservas/') !== false) {
            $redirect_url = '../index.php';
        }
    }
    
    PermissaoService::exigirPermissao($codigo_modulo, $nivel_minimo, $redirect_url);
}

/**
 * Verifica se o usuário pode visualizar o módulo (shortcut)
 */
function pode_visualizar_modulo($codigo_modulo) {
    return PermissaoService::podeVisualizar($codigo_modulo);
}

/**
 * Verifica se o usuário pode editar no módulo (shortcut)
 */
function pode_editar_modulo($codigo_modulo) {
    return PermissaoService::podeEditar($codigo_modulo);
}

/**
 * Verifica se o usuário pode excluir no módulo (shortcut)
 */
function pode_excluir_modulo($codigo_modulo) {
    return PermissaoService::podeExcluir($codigo_modulo);
}

/**
 * Verifica se o usuário pode administrar o módulo (shortcut)
 */
function pode_administrar_modulo($codigo_modulo) {
    return PermissaoService::podeAdministrar($codigo_modulo);
}

/**
 * Verifica se é admin (shortcut)
 */
function is_admin() {
    return PermissaoService::isAdmin();
}

/**
 * Obtém os módulos que o usuário tem acesso
 */
function get_modulos_usuario() {
    return PermissaoService::getModulosDoUsuario();
}

