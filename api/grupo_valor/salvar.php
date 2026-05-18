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

$dados = json_decode(file_get_contents('php://input'), true);

$id = intval($dados['id'] ?? 0);
$descricao = trim($dados['descricao'] ?? '');
$valor = floatval($dados['valor'] ?? 0);

if (empty($descricao)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Descrição é obrigatória']);
    exit;
}

if ($valor < 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Valor não pode ser negativo']);
    exit;
}

try {
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE grupo_valor SET descricao = ?, valor = ? WHERE id = ?");
        $stmt->bind_param("sdi", $descricao, $valor, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO grupo_valor (descricao, valor) VALUES (?, ?)");
        $stmt->bind_param("sd", $descricao, $valor);
    }

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => $id > 0 ? 'Valor atualizado com sucesso!' : 'Valor cadastrado com sucesso!'
        ]);
    } else {
        if ($conn->errno == 1062) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe um registro com esta descrição']);
        } else {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao salvar: ' . $conn->error]);
        }
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao salvar valor de refeição: ' . $e->getMessage()
    ]);
}
?>
