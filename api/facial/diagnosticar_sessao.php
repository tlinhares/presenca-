<?php
// api/facial/diagnosticar_sessao.php
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Função para exibir informações de sessão de maneira segura
function exibirInfoSessao() {
    // Iniciar a sessão se ainda não estiver ativa
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    // Coletar informações da sessão
    $info_sessao = [
        'status' => 'ok',
        'session_id' => session_id(),
        'session_path' => session_save_path(),
        'session_name' => session_name(),
        'session_active' => (session_status() === PHP_SESSION_ACTIVE),
        'session_variables' => []
    ];
    
    // Coletar variáveis de sessão (sem exibir senhas ou tokens)
    foreach ($_SESSION as $key => $value) {
        // Excluir informações sensíveis
        if (stripos($key, 'senha') !== false || 
            stripos($key, 'password') !== false || 
            stripos($key, 'token') !== false || 
            stripos($key, 'secret') !== false) {
            $info_sessao['session_variables'][$key] = "[CONTEÚDO SENSÍVEL OMITIDO]";
        } else {
            // Para arrays e objetos, apenas indicar o tipo
            if (is_array($value)) {
                $info_sessao['session_variables'][$key] = "[ARRAY]";
            } elseif (is_object($value)) {
                $info_sessao['session_variables'][$key] = "[OBJETO]";
            } else {
                $info_sessao['session_variables'][$key] = $value;
            }
        }
    }
    
    // Adicionar informações do servidor
    $info_sessao['server_info'] = [
        'php_version' => PHP_VERSION,
        'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'N/A',
        'remote_addr' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'N/A',
        'request_time' => date('Y-m-d H:i:s', isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time()),
        'document_root' => isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'N/A',
        'script_filename' => isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : 'N/A'
    ];
    
    // Verificar se o usuário está autenticado como admin
    $is_admin = false;
    $auth_variables = [];
    
    // Verificar diferentes variáveis de sessão possíveis
    if (isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin') {
        $is_admin = true;
        $auth_variables[] = 'usuario_categoria = admin';
    } 
    if (isset($_SESSION['categoria']) && $_SESSION['categoria'] === 'admin') {
        $is_admin = true;
        $auth_variables[] = 'categoria = admin';
    } 
    if (isset($_SESSION['nivel']) && $_SESSION['nivel'] === 'admin') {
        $is_admin = true;
        $auth_variables[] = 'nivel = admin';
    } 
    // Alguns sistemas usam números para níveis de acesso (1 = admin)
    if (isset($_SESSION['nivel']) && $_SESSION['nivel'] === '1') {
        $is_admin = true;
        $auth_variables[] = 'nivel = 1';
    } 
    if (isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === '1') {
        $is_admin = true;
        $auth_variables[] = 'usuario_nivel = 1';
    }
    
    $info_sessao['auth_status'] = [
        'is_admin' => $is_admin,
        'auth_variables' => $auth_variables
    ];
    
    // Registrar diagnóstico em log
    $logs_dir = __DIR__ . '/../../logs';
    if (!file_exists($logs_dir)) {
        @mkdir($logs_dir, 0777, true);
    }
    $log_file = $logs_dir . '/diagnostico_sessao_' . date('Y-m-d') . '.log';
    $log_data = date('Y-m-d H:i:s') . ' - Diagnóstico de sessão - Admin: ' . ($is_admin ? 'SIM' : 'NÃO') . 
                ' - Session ID: ' . session_id() . PHP_EOL;
    @file_put_contents($log_file, $log_data, FILE_APPEND);
    
    return $info_sessao;
}

// Executar diagnóstico e retornar resultado
$resultado = exibirInfoSessao();
echo json_encode($resultado, JSON_PRETTY_PRINT);
?> 