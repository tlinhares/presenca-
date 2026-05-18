<?php
/**
 * Configurações gerais do sistema
 * 
 * Este arquivo contém as configurações gerais do sistema,
 * incluindo configurações de URL base, diretórios e outras
 * configurações globais.
 */

// Detecta automaticamente o caminho base da aplicação
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $dirName = dirname($scriptName);
    
    // Se estiver na pasta public, volte um nível
    if (basename($dirName) === 'public') {
        $dirName = dirname($dirName);
    }
    
    // Certifique-se de que o caminho termina com uma barra
    if ($dirName !== '/' && substr($dirName, -1) !== '/') {
        $dirName .= '/';
    }
    
    return $protocol . $host . $dirName;
}

// Configurações do sistema
$config = [
    // URL base do sistema
    'base_url' => getBaseUrl(),
    
    // Diretório raiz do sistema
    'root_dir' => dirname(__DIR__),
    
    // Diretório de uploads
    'upload_dir' => dirname(__DIR__) . '/public/uploads/',
    
    // URL para uploads
    'upload_url' => getBaseUrl() . 'public/uploads/',
    
    // Configurações de sessão
    'session' => [
        'name' => 'sistema_construtora',
        'lifetime' => 7200, // 2 horas
    ],
    
    // Configurações de segurança
    'security' => [
        'salt' => 'sua_chave_de_salt_aqui',
        'hash_algo' => 'sha256',
    ],
    
    // Configurações de log
    'log' => [
        'enabled' => true,
        'file' => dirname(__DIR__) . '/logs/app.log',
    ],
    
    // Configurações de debug
    'debug' => true,
];

// Retorna o valor de uma configuração
function config($key, $default = null) {
    global $config;
    
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}
