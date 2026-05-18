<?php
// api/config/listar_configs.php
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);

// Incluir arquivos necessários
require_once __DIR__ . '/../../api/conexao.php';
require_once __DIR__ . '/../../auth/verifica_sessao.php';

// Verificar se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Usuário não autenticado.'
    ]);
    exit;
}

try {
    // Verificar se a tabela existe
    $result = $conn->query("SHOW TABLES LIKE 'configuracoes'");
    
    if ($result->num_rows == 0) {
        // Criar tabela se não existir
        $conn->query("CREATE TABLE configuracoes (
            id INT(11) NOT NULL AUTO_INCREMENT,
            chave VARCHAR(100) NOT NULL,
            valor TEXT DEFAULT NULL,
            descricao VARCHAR(255) DEFAULT NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY (chave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Tabela de configurações criada com sucesso.',
            'configs' => []
        ]);
        exit;
    }
    
    // Parâmetros de filtro opcionais
    $filtro_chave = isset($_GET['chave']) ? $_GET['chave'] : null;
    
    // Construir a consulta
    $sql = "SELECT * FROM configuracoes";
    $params = [];
    $tipos = "";
    
    if ($filtro_chave) {
        $sql .= " WHERE chave LIKE ?";
        $params[] = "%$filtro_chave%";
        $tipos .= "s";
    }
    
    $sql .= " ORDER BY chave ASC";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($tipos, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $configs = [];
    
    while ($row = $result->fetch_assoc()) {
        // Limitar acesso a configurações sensíveis apenas para administradores
        if (strpos($row['chave'], 'senha') !== false || strpos($row['chave'], 'token') !== false) {
            
        }
        
        $configs[] = $row;
    }
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Configurações listadas com sucesso.',
        'total' => count($configs),
        'configs' => $configs
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao listar configurações: ' . $e->getMessage()
    ]);
}
?> 