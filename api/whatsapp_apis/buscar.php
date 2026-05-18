<?php
/**
 * API - Buscar API de WhatsApp por ID (inclui token)
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

// Apenas admin pode acessar
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../../api/conexao.php';

try {
    // Verificar se tabela existe
    $result_check = $conn->query("SHOW TABLES LIKE 'whatsapp_apis'");
    if (!$result_check || $result_check->num_rows === 0) {
        throw new Exception('Tabela whatsapp_apis não existe. Execute o script de inicialização primeiro.');
    }
    
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    $sql = "SELECT 
                id,
                nome,
                url_mensagem,
                url_arquivo,
                token,
                numero_whatsapp,
                ativo,
                prioridade,
                observacoes
            FROM whatsapp_apis
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('API não encontrada');
    }
    
    $api = $result->fetch_assoc();
    $stmt->close();
    
    // Garantir tipos corretos
    $api['id'] = intval($api['id']);
    $api['ativo'] = intval($api['ativo']);
    $api['prioridade'] = intval($api['prioridade']);
    
    echo json_encode([
        'status' => 'ok',
        'api' => $api
    ]);
    
} catch (Exception $e) {
    error_log("Erro em whatsapp_apis/buscar.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}

$conn->close();
