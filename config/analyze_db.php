<?php
require_once __DIR__ . '/../utils/env.php';
try {
    $pdo = new PDO(
        "mysql:host=" . env('DB_HOST', 'localhost') . ";dbname=" . env('DB_NAME', 'presenca_aom'),
        env('DB_USER', 'root'),
        env('DB_PASS', '')
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Listar todas as tabelas
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tabelas encontradas:\n";
    foreach ($tables as $table) {
        echo "\nTabela: $table\n";
        echo "----------------------------------------\n";
        
        // Obter estrutura da tabela
        $columns = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "Campo: {$column['Field']}\n";
            echo "Tipo: {$column['Type']}\n";
            echo "Nulo: {$column['Null']}\n";
            echo "Chave: {$column['Key']}\n";
            echo "Default: {$column['Default']}\n";
            echo "Extra: {$column['Extra']}\n";
            echo "----------------------------------------\n";
        }
    }
} catch (PDOException $e) {
    die("Erro ao analisar banco de dados: " . $e->getMessage() . "\n");
} 