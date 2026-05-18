<?php
// api/config/listar_config.php
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);

// Incluir arquivos necessários
require_once __DIR__ . '/../../api/conexao.php';
require_once __DIR__ . '/../../auth/verifica_sessao.php';



try {
    // Verificar se a tabela existe
    $result = $conn->query("SHOW TABLES LIKE 'configuracoes'");
    
    if ($result->num_rows == 0) {
        // Criar tabela se não existir
        $sql = "CREATE TABLE configuracoes (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            chave VARCHAR(255) NOT NULL UNIQUE,
            valor TEXT,
            descricao TEXT,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        if (!$conn->query($sql)) {
            throw new Exception("Erro ao criar tabela de configurações: " . $conn->error);
        }
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Tabela de configurações criada com sucesso.',
            'configuracoes' => []
        ]);
        exit;
    }
    
    // Listar todas as configurações
    $sql = "SELECT id, chave, valor, descricao, data_criacao, data_atualizacao FROM configuracoes ORDER BY chave";
    $result = $conn->query($sql);
    
    $configuracoes = [];
    while ($row = $result->fetch_assoc()) {
        $configuracoes[] = [
            'id' => $row['id'],
            'chave' => $row['chave'],
            'valor' => $row['valor'],
            'descricao' => $row['descricao'],
            'data_criacao' => $row['data_criacao'],
            'data_atualizacao' => $row['data_atualizacao']
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'configuracoes' => $configuracoes,
        'total' => count($configuracoes)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao listar configurações: ' . $e->getMessage()
    ]);
}
?> 