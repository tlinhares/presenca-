<?php
/**
 * API para salvar (criar/atualizar) dia fechado
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('gerenciamento_dias_fechado');

include_once(__DIR__ . '/../conexao.php');

// Verificar se a conexão foi estabelecida
if (!isset($conn) || !$conn) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro de conexão com o banco de dados']);
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true);

$id = intval($dados['id'] ?? 0);
$data = trim($dados['data'] ?? '');
$motivo = trim($dados['motivo'] ?? '');
$observacoes = trim($dados['observacoes'] ?? '');
$ativo = intval($dados['ativo'] ?? 1);
$usuario_id = $_SESSION['usuario_id'] ?? null;

if (empty($data)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Data é obrigatória']);
    exit;
}

try {
    // Validar formato da data
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    if (!$data_obj) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Data inválida']);
        exit;
    }

    if ($id > 0) {
        // Atualizar
        $stmt = $conn->prepare("
            UPDATE dias_fechado 
            SET data = ?, motivo = ?, observacoes = ?, ativo = ?, atualizado_em = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("sssii", $data, $motivo, $observacoes, $ativo, $id);
    } else {
        // Criar
        $stmt = $conn->prepare("
            INSERT INTO dias_fechado (data, motivo, observacoes, ativo, criado_por, criado_em)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("sssii", $data, $motivo, $observacoes, $ativo, $usuario_id);
    }

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => $id > 0 ? 'Dia fechado atualizado com sucesso!' : 'Dia fechado criado com sucesso!'
        ]);
    } else {
        if ($conn->errno == 1062) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe um registro para esta data']);
        } else {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao salvar: ' . $conn->error]);
        }
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao salvar dia fechado: ' . $e->getMessage()
    ]);
}
?>

