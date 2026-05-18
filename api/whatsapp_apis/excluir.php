<?php
/**
 * API - Excluir API de WhatsApp
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

// Apenas admin pode acessar
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../../api/conexao.php';

try {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Verificar se API existe
    $sql_check = "SELECT nome FROM whatsapp_apis WHERE id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('API não encontrada');
    }
    
    $api = $result_check->fetch_assoc();
    $stmt_check->close();
    
    // Verificar se está sendo usada em alguma configuração
    $sql_config = "SELECT COUNT(*) as total FROM whatsapp_config_notificacoes 
                   WHERE id_api_especifica = ? OR JSON_CONTAINS(ids_apis_sorteio, CAST(? AS JSON))";
    $id_json = json_encode($id);
    $stmt_config = $conn->prepare($sql_config);
    $stmt_config->bind_param("is", $id, $id_json);
    $stmt_config->execute();
    $result_config = $stmt_config->get_result();
    $config = $result_config->fetch_assoc();
    $stmt_config->close();
    
    if ($config['total'] > 0) {
        throw new Exception('Esta API está sendo usada em configurações de notificação. Remova as configurações antes de excluir.');
    }
    
    // Excluir
    $sql = "DELETE FROM whatsapp_apis WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('Erro ao excluir API');
    }
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'API "' . $api['nome'] . '" excluída com sucesso'
    ]);
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Erro em whatsapp_apis/excluir.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}

$conn->close();
