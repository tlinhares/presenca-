<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../conexao.php';
include_once(__DIR__ . '/../../auth/verifica_sessao.php');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não logado']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT chave, valor FROM configuracoes");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $configuracoes = [];
    while ($row = $result->fetch_assoc()) {
        $configuracoes[$row['chave']] = $row['valor'];
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'sucesso',
        'data' => $configuracoes
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar configurações: ' . $e->getMessage()
    ]);
}
?>