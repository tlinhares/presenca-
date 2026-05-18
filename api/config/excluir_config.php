<?php
// api/config/excluir_config.php
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);

// Incluir arquivos necessários
require_once __DIR__ . '/../../api/conexao.php';
require_once __DIR__ . '/../../auth/verifica_sessao.php';



// Verificar se o método é DELETE ou POST com parâmetro de exclusão
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && !($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_method']) && $_POST['_method'] === 'DELETE')) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Método não permitido. Use DELETE para excluir configurações.'
    ]);
    exit;
}

// Obter dados
$dados = [];
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str(file_get_contents('php://input'), $dados);
} else {
    $dados = $_POST;
}

// Verificar se os campos obrigatórios estão presentes
if ((!isset($dados['id']) || empty($dados['id'])) && (!isset($dados['chave']) || empty($dados['chave']))) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'É necessário fornecer o ID ou a chave da configuração a ser excluída.'
    ]);
    exit;
}

try {
    // Verificar se a tabela existe
    $result = $conn->query("SHOW TABLES LIKE 'configuracoes'");
    
    if ($result->num_rows == 0) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'A tabela de configurações não existe.'
        ]);
        exit;
    }
    
    if (isset($dados['id']) && !empty($dados['id'])) {
        // Exclusão por ID
        $stmt = $conn->prepare("DELETE FROM configuracoes WHERE id = ?");
        $stmt->bind_param("i", $dados['id']);
    } else {
        // Exclusão por chave
        $stmt = $conn->prepare("DELETE FROM configuracoes WHERE chave = ?");
        $stmt->bind_param("s", $dados['chave']);
    }
    
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Configuração excluída com sucesso.'
        ]);
    } else {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Configuração não encontrada ou já excluída.'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao excluir configuração: ' . $e->getMessage()
    ]);
}
?> 