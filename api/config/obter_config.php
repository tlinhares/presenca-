<?php
// api/config/obter_config.php
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);

// Incluir arquivos necessários
require_once __DIR__ . '/../../api/conexao.php';
require_once __DIR__ . '/../../auth/verifica_sessao.php';

// Verificar se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Usuário não autenticado'
    ]);
    exit;
}

// Verificar parâmetros
if ((!isset($_GET['id']) || empty($_GET['id'])) && (!isset($_GET['chave']) || empty($_GET['chave']))) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'É necessário fornecer o ID ou a chave da configuração.'
    ]);
    exit;
}

try {
    // Verificar se a tabela existe
    $result = $conn->query("SHOW TABLES LIKE 'configuracoes'");
    
    if ($result->num_rows == 0) {
        // Criar a tabela se não existir
        $sql = "CREATE TABLE configuracoes (
            id INT(11) NOT NULL AUTO_INCREMENT,
            chave VARCHAR(255) NOT NULL,
            valor TEXT NOT NULL,
            descricao TEXT,
            categoria VARCHAR(100),
            is_sensivel BOOLEAN DEFAULT FALSE,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY (chave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $conn->query($sql);
    }
    
    // Preparar consulta com base no parâmetro fornecido
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $stmt = $conn->prepare("SELECT * FROM configuracoes WHERE id = ?");
        $stmt->bind_param("i", $_GET['id']);
    } else {
        $stmt = $conn->prepare("SELECT * FROM configuracoes WHERE chave = ?");
        $stmt->bind_param("s", $_GET['chave']);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $config = $result->fetch_assoc();
        
        // Mascarar valores sensíveis para usuários não-admin
        if ($config['is_sensivel'] && (!isset($_SESSION['usuario_categoria']) || $_SESSION['usuario_categoria'] !== 'admin')) {
            $config['valor'] = '********';
        }
        
        echo json_encode([
            'status' => 'ok',
            'configuracao' => $config
        ]);
    } else {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Configuração não encontrada.'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao obter configuração: ' . $e->getMessage()
    ]);
}
?> 