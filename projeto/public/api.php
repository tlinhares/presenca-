<?php
/**
 * API para requisições AJAX
 * 
 * Este arquivo é o ponto de entrada para requisições AJAX,
 * processando as solicitações e retornando respostas em formato JSON.
 */

// Define o diretório raiz do sistema
define('ROOT_DIR', dirname(__DIR__));

// Carrega as configurações
require_once ROOT_DIR . '/src/config/config.php';

// Carrega as classes principais
require_once ROOT_DIR . '/src/core/session.php';
require_once ROOT_DIR . '/src/core/auth.php';
require_once ROOT_DIR . '/src/core/permission.php';
require_once ROOT_DIR . '/src/core/ajax_handler.php';

// Inicializa a sessão
$session = new Session();
$session->start();

// Inicializa a autenticação
$auth = new Auth($session);

// Inicializa o gerenciador de permissões
$permission = new Permission($auth);

// Inicializa o manipulador AJAX
$ajaxHandler = new AjaxHandler($auth, $permission);

// Define o cabeçalho para JSON
header('Content-Type: application/json');

// Verifica se o usuário está autenticado
if (!$auth->isLoggedIn() && $_REQUEST['action'] != 'login') {
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado',
        'redirect' => config('base_url') . 'login'
    ]);
    exit;
}

// Processa a requisição
$action = $_REQUEST['action'] ?? '';
$result = $ajaxHandler->process($action, $_REQUEST);

// Retorna o resultado
echo json_encode($result);
