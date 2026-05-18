<?php
/**
 * API para Excluir Manutenção
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../conexao.php';

if (!MenuPermissaoService::podeAcessar('frota_admin_manutencoes')) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID não informado']);
    exit;
}

try {
    $sql = "DELETE FROM frota_manutencoes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Manutenção excluída com sucesso!'
        ]);
    } else {
        throw new Exception($stmt->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao excluir manutenção: ' . $e->getMessage()
    ]);
}
?>



