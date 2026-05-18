<?php
// api/config/salvar_config.php
header('Content-Type: application/json; charset=UTF-8');

// Conexão com banco
require_once __DIR__ . '/../../api/conexao.php';

// Aceita apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Requisição inválida.']);
    exit;
}

// Processar dados JSON recebidos
$dados = json_decode(file_get_contents('php://input'), true);
if (!$dados || !isset($dados['chave'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos ou chave ausente.']);
    exit;
}

$chave = trim($dados['chave']);
$valor = $dados['valor'] ?? '';

// Verificar se a chave já existe
$stmt_verifica = $conn->prepare("SELECT id FROM configuracoes WHERE chave = ?");
$stmt_verifica->bind_param("s", $chave);
$stmt_verifica->execute();
$stmt_verifica->store_result();

if ($stmt_verifica->num_rows > 0) {
    // Atualizar valor existente
    $stmt = $conn->prepare("UPDATE configuracoes SET valor = ? WHERE chave = ?");
    $stmt->bind_param("ss", $valor, $chave);
    $ok = $stmt->execute();
    $stmt->close();
    $acao = 'atualizada';
} else {
    // Inserir nova configuração
    $stmt = $conn->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?)");
    $stmt->bind_param("ss", $chave, $valor);
    $ok = $stmt->execute();
    $acao = 'inserida';
    $stmt->close();
}

$stmt_verifica->close();
$conn->close();

if ($ok) {
    echo json_encode(['status' => 'ok', 'mensagem' => "Configuração $acao com sucesso."]);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao salvar configuração.']);
}
?>
