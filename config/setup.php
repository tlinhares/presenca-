<?php
// Configurações para conexão
$db_user = 'root';
$db_pass = '@Arcs2901';

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