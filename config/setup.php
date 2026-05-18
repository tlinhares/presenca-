<?php
// Configurações para conexão (lê do .env)
require_once __DIR__ . '/../utils/env.php';
$db_user = env('DB_USER', 'root');
$db_pass = env('DB_PASS', '');

try {
    // Conectar ao MySQL
    $pdo = new PDO("mysql:host=localhost", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ler e executar o script SQL
    $sql = file_get_contents(__DIR__ . '/setup_database.sql');
    
    // Dividir o script em comandos individuais
    $commands = array_filter(array_map('trim', explode(';', $sql)));
    
    // Executar cada comando separadamente
    foreach ($commands as $command) {
        if (!empty($command)) {
            $pdo->exec($command);
        }
    }

    echo "Configuração do banco de dados concluída com sucesso!\n";
} catch (PDOException $e) {
    die("Erro na configuração do banco de dados: " . $e->getMessage() . "\n");
} 