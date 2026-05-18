<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('gerenciar_valores_refeicoes');

include_once(__DIR__ . '/../conexao.php');

if (!isset($conn) || !$conn) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro de conexão com o banco de dados']);
    exit;
}

try {
    $sql = "SELECT * FROM grupo_valor ORDER BY descricao ASC";
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception('Erro na consulta: ' . $conn->error);
    }

    $itens = [];
    while ($row = $result->fetch_assoc()) {
        $itens[] = $row;
    }

    echo json_encode([
        'status' => 'ok',
        'itens' => $itens
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao listar valores de refeições: ' . $e->getMessage()
    ]);
}
?>
