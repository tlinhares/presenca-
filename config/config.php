<?php
// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'presenca_aom');
define('DB_USER', 'root');
define('DB_PASS', '@Arcs2901');

// Configurações do Sistema
define('SITE_NAME', 'Sistema de Presença AOM');
define('SITE_URL', 'http://presenca.aom.com.br');
define('TIMEZONE', 'America/Sao_Paulo');

// Configurações de Email
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'seu-email@gmail.com');
define('SMTP_PASS', 'sua-senha');

// Configurações do Dispositivo Intelbras
define('DEVICE_IP', '10.144.129.69');
define('DEVICE_USER', 'admin');
define('DEVICE_PASS', 'Arcs2901');

// Configurações de Upload
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Configurações de Sessão
define('SESSION_LIFETIME', 3600); // 1 hora
define('SESSION_NAME', 'presenca_session');

// Configurações de Cache
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 3600); // 1 hora
define('CACHE_PATH', __DIR__ . '/../cache/');

// Configurações de Log
define('LOG_PATH', __DIR__ . '/../logs/');
define('LOG_LEVEL', 'debug'); // debug, info, warning, error

// Configurações de Notificação
define('NOTIFICATION_EMAIL', true);
define('NOTIFICATION_SMS', false);
define('NOTIFICATION_PUSH', false);

// Configurações de API
define('API_KEY', 'sua-chave-api');
define('API_RATE_LIMIT', 100); // requisições por hora

// Configurações de Segurança
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_SPECIAL', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_UPPERCASE', true);

// Configurações de Interface
define('THEME', 'light'); // light ou dark
define('ITEMS_PER_PAGE', 10);
define('DATE_FORMAT', 'd/m/Y');
define('TIME_FORMAT', 'H:i:s');

// Configurações de Backup
define('BACKUP_ENABLED', true);
define('BACKUP_PATH', __DIR__ . '/../backups/');
define('BACKUP_FREQUENCY', 'daily'); // daily, weekly, monthly

// Configurações de Monitoramento
define('MONITORING_ENABLED', true);
define('ALERT_EMAIL', 'admin@exemplo.com');
define('PERFORMANCE_THRESHOLD', 80); // percentual

// Função para carregar configurações
function getConfig($key) {
    return defined($key) ? constant($key) : null;
}

// Função para verificar se uma configuração existe
function hasConfig($key) {
    return defined($key);
}

// Função para definir uma configuração dinamicamente
function setConfig($key, $value) {
    if (!defined($key)) {
        define($key, $value);
        return true;
    }
    return false;
} 