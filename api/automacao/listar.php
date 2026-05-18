<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();


include_once(__DIR__ . '/../conexao.php');

// Verificar se a conexão foi estabelecida
if (!isset($conn) || !$conn) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro de conexão com o banco de dados']);
    exit;
}

// Verificar se a sessão está ativa
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

// Verificar se é admin


try {
    $sql = "SELECT * FROM automacoes_relatorios ORDER BY criado_em DESC";
    $result = $conn->query($sql);
    
    $automacoes = [];
    while ($row = $result->fetch_assoc()) {
        $automacoes[] = $row;
    }
    
    echo json_encode([
        'status' => 'sucesso',
        'dados' => $automacoes,
        'total' => count($automacoes)
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}
?>
