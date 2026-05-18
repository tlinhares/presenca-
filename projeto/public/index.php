<?php
/**
 * Front Controller - Ponto de entrada do sistema
 * 
 * Este arquivo é o ponto de entrada do sistema, responsável
 * por inicializar as configurações, sessão e roteamento.
 */

// Define o diretório raiz do sistema
define('ROOT_DIR', dirname(__DIR__));

// Carrega as configurações
require_once ROOT_DIR . '/src/config/config.php';

// Carrega as classes principais
require_once ROOT_DIR . '/src/core/router.php';
require_once ROOT_DIR . '/src/core/session.php';
require_once ROOT_DIR . '/src/core/auth.php';
require_once ROOT_DIR . '/src/core/permission.php';
require_once ROOT_DIR . '/src/core/ajax_handler.php';

// Inicializa a sessão
$session = new Session();
$session->start();

// Inicializa o roteador
$router = new Router();

// Inicializa a autenticação
$auth = new Auth($session);

// Inicializa o gerenciador de permissões
$permission = new Permission($auth);

// Define as rotas do sistema
$router->get('/', function() use ($auth) {
    if ($auth->isLoggedIn()) {
        // Se o usuário estiver logado, redireciona para o dashboard
        include ROOT_DIR . '/src/views/layouts/header.php';
        include ROOT_DIR . '/src/views/layouts/dashboard.php';
        include ROOT_DIR . '/src/views/layouts/footer.php';
    } else {
        // Se o usuário não estiver logado, redireciona para a página de login
        include ROOT_DIR . '/src/views/layouts/login.php';
    }
});

$router->get('/login', function() {
    include ROOT_DIR . '/src/views/layouts/login.php';
});

$router->post('/login', function() use ($auth, $router) {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    if ($auth->login($email, $senha)) {
        $router->redirect('/');
    } else {
        $router->redirect('/login?erro=1');
    }
});

$router->get('/logout', function() use ($auth, $router) {
    $auth->logout();
    $router->redirect('/login');
});

$router->get('/obras', function() use ($auth, $permission) {
    if (!$auth->isLoggedIn()) {
        header('Location: /login');
        exit;
    }
    
    if (!$permission->hasPermission('obras', 'view')) {
        header('Location: /?erro=permissao');
        exit;
    }
    
    include ROOT_DIR . '/src/views/layouts/header.php';
    include ROOT_DIR . '/src/views/obras/listar.php';
    include ROOT_DIR . '/src/views/layouts/footer.php';
});

$router->get('/obras/nova', function() use ($auth, $permission) {
    if (!$auth->isLoggedIn()) {
        header('Location: /login');
        exit;
    }
    
    if (!$permission->hasPermission('obras', 'create')) {
        header('Location: /?erro=permissao');
        exit;
    }
    
    include ROOT_DIR . '/src/views/layouts/header.php';
    include ROOT_DIR . '/src/views/obras/nova.php';
    include ROOT_DIR . '/src/views/layouts/footer.php';
});

$router->get('/financeiro/notas-fiscais', function() use ($auth, $permission) {
    if (!$auth->isLoggedIn()) {
        header('Location: /login');
        exit;
    }
    
    if (!$permission->hasPermission('financeiro', 'view')) {
        header('Location: /?erro=permissao');
        exit;
    }
    
    include ROOT_DIR . '/src/views/layouts/header.php';
    include ROOT_DIR . '/src/views/financeiro/notas_fiscais.php';
    include ROOT_DIR . '/src/views/layouts/footer.php';
});

// Executa o roteador
$router->run();
