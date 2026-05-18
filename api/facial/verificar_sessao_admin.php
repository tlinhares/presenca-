<?php
// api/facial/verificar_sessao_admin.php
// Arquivo auxiliar para verificar a sessão de administrador e garantir consistência

// Iniciar a sessão se ainda não estiver ativa
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Função para registrar tentativas de acesso não autorizadas
function registrarTentativaAcesso($arquivo, $mensagem = '') {
    $logs_dir = __DIR__ . '/../../logs';
    
    // Verificar se o diretório de logs existe e tentar criá-lo se não existir
    if (!file_exists($logs_dir)) {
        if (!@mkdir($logs_dir, 0777, true)) {
            // Se não conseguir criar o diretório, usar diretório temporário do sistema
            $logs_dir = sys_get_temp_dir();
        }
    }
    
    $log_file = $logs_dir . '/acesso_' . date('Y-m-d') . '.log';
    $time = date('Y-m-d H:i:s');
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'desconhecido';
    
    // Buscar ID do usuário em diferentes variáveis possíveis de sessão
    $usuario_id = 'não autenticado';
    if (isset($_SESSION['id_usuario'])) {
        $usuario_id = $_SESSION['id_usuario'];
    } elseif (isset($_SESSION['usuario_id'])) {
        $usuario_id = $_SESSION['usuario_id'];
    } elseif (isset($_SESSION['id'])) {
        $usuario_id = $_SESSION['id'];
    }
    
    // Buscar nome do usuário em diferentes variáveis possíveis de sessão
    $usuario_nome = 'não autenticado';
    if (isset($_SESSION['usuario_nome'])) {
        $usuario_nome = $_SESSION['usuario_nome'];
    } elseif (isset($_SESSION['nome'])) {
        $usuario_nome = $_SESSION['nome'];
    } elseif (isset($_SESSION['usuario'])) {
        $usuario_nome = $_SESSION['usuario'];
    }
    
    // Buscar categoria do usuário
    $usuario_categoria = 'não definido';
    if (isset($_SESSION['usuario_categoria'])) {
        $usuario_categoria = $_SESSION['usuario_categoria'];
    } elseif (isset($_SESSION['categoria'])) {
        $usuario_categoria = $_SESSION['categoria'];
    } elseif (isset($_SESSION['nivel'])) {
        $usuario_categoria = $_SESSION['nivel'];
    }
    
    // Registrar outras variáveis de sessão para depuração
    $debug_session = '';
    if (count($_SESSION) > 0) {
        $debug_session = ' | Variáveis de sessão: ';
        foreach ($_SESSION as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $debug_session .= "$key=$value; ";
            }
        }
    }
    
    $log_mensagem = "[$time] IP: $ip | Arquivo: $arquivo | Usuário ID: $usuario_id | Nome: $usuario_nome | Categoria: $usuario_categoria$debug_session | $mensagem";
    @file_put_contents($log_file, $log_mensagem . PHP_EOL, FILE_APPEND);
}

// Verificar se o usuário está autenticado e é um administrador
function verificarAdmin($arquivo_origem) {
    // Verificar se a sessão está ativa e o usuário é administrador
    $is_admin = false;
    
    // Verificar diferentes variáveis de sessão possíveis
    if (isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin') {
        $is_admin = true;
    } elseif (isset($_SESSION['categoria']) && $_SESSION['categoria'] === 'admin') {
        $is_admin = true;
    } elseif (isset($_SESSION['nivel']) && $_SESSION['nivel'] === 'admin') {
        $is_admin = true;
    } 
    // Alguns sistemas usam números para níveis de acesso (1 = admin)
    elseif (isset($_SESSION['nivel']) && $_SESSION['nivel'] === '1') {
        $is_admin = true;
    } elseif (isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === '1') {
        $is_admin = true;
    }
    
    if (!$is_admin) {
        // Registrar a tentativa de acesso não autorizada
        registrarTentativaAcesso($arquivo_origem, 'Tentativa de acesso não autorizado');
        
        // Enviar resposta de erro e encerrar o script
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Acesso permitido apenas para administradores'
        ]);
        exit;
    }
    
    // Registrar acesso autorizado
    registrarTentativaAcesso($arquivo_origem, 'Acesso autorizado');
    
    return true;
}

// Função para verificar acesso, permitindo uso por cron ou chave API
function verificarAcessoSistema($arquivo_origem) {
    // Verificar se é um administrador
    if (isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin') {
        registrarTentativaAcesso($arquivo_origem, 'Acesso autorizado (admin)');
        return true;
    }
    
    // Verificar se é uma chamada do cron
    $is_cron = isset($_GET['cron']) && $_GET['cron'] == '1';
    if ($is_cron) {
        registrarTentativaAcesso($arquivo_origem, 'Acesso autorizado (cron)');
        return true;
    }
    
    // Verificar se tem uma chave de API válida (implementar conforme necessário)
    $api_key = isset($_GET['api_key']) ? $_GET['api_key'] : '';
    $valid_key = ($api_key === 'chave_secreta_aqui'); // Substitua por uma verificação real
    
    if ($valid_key) {
        registrarTentativaAcesso($arquivo_origem, 'Acesso autorizado (API key)');
        return true;
    }
    
    // Se chegou aqui, não tem autorização
    registrarTentativaAcesso($arquivo_origem, 'Tentativa de acesso não autorizado');
    
    // Enviar resposta de erro e encerrar o script
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Acesso não autorizado'
    ]);
    exit;
}
?> 