<?php
// api/config/atualizar_config.php
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);

// Incluir arquivos necessários
require_once __DIR__ . '/../../api/conexao.php';
require_once __DIR__ . '/../../auth/verifica_sessao.php';



// Verificar se o método é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Método não permitido. Use POST para atualizar configurações.'
    ]);
    exit;
}

// Obter dados do corpo da requisição
$dados = json_decode(file_get_contents('php://input'), true);

// Verificar se os campos obrigatórios estão presentes
if (!isset($dados['chave']) || empty($dados['chave'])) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'A chave da configuração é obrigatória.'
    ]);
    exit;
}

// Limpar e validar os dados
$chave = trim($dados['chave']);
$valor = isset($dados['valor']) ? $dados['valor'] : '';
$descricao = isset($dados['descricao']) ? trim($dados['descricao']) : '';

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
    }
    
    // Verificar se a configuração já existe
    $stmt = $conn->prepare("SELECT id FROM configuracoes WHERE chave = ?");
    $stmt->bind_param("s", $chave);
    $stmt->execute();
    $resultCheck = $stmt->get_result();
    
    if ($resultCheck->num_rows > 0) {
        // Atualizar configuração existente
        $stmt = $conn->prepare("UPDATE configuracoes SET valor = ?, descricao = ? WHERE chave = ?");
        $stmt->bind_param("sss", $valor, $descricao, $chave);
        
        if (!$stmt->execute()) {
            throw new Exception("Erro ao atualizar configuração: " . $stmt->error);
        }
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Configuração atualizada com sucesso.',
            'chave' => $chave,
            'valor' => $valor,
            'descricao' => $descricao
        ]);
    } else {
        // Inserir nova configuração
        $stmt = $conn->prepare("INSERT INTO configuracoes (chave, valor, descricao) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $chave, $valor, $descricao);
        
        if (!$stmt->execute()) {
            throw new Exception("Erro ao inserir configuração: " . $stmt->error);
        }
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Configuração adicionada com sucesso.',
            'chave' => $chave,
            'valor' => $valor,
            'descricao' => $descricao,
            'id' => $conn->insert_id
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao atualizar configuração: ' . $e->getMessage()
    ]);
}
?> 