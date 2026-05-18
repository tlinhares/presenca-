<?php
/**
 * API - Listar Configurações de Notificações
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';

// Apenas admin pode acessar
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../../../api/conexao.php';

try {
    // Verificar se tabelas existem
    $result_check = $conn->query("SHOW TABLES LIKE 'whatsapp_config_notificacoes'");
    if (!$result_check || $result_check->num_rows === 0) {
        echo json_encode([
            'status' => 'ok',
            'configs' => [],
            'apis' => [],
            'mensagem' => 'Tabelas não existem. Execute o script de inicialização.'
        ]);
        exit;
    }
    
    // Buscar todas as configurações
    $sql = "SELECT 
                id,
                tipo_notificacao,
                modo_selecao,
                id_api_especifica,
                ids_apis_sorteio,
                tentativas_maximas,
                desabilitar_whatsapp,
                observacoes,
                atualizado_em
            FROM whatsapp_config_notificacoes
            ORDER BY tipo_notificacao ASC";
    
    $result = $conn->query($sql);
    
    $configs = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Decodificar JSON se existir
            if ($row['ids_apis_sorteio']) {
                $row['ids_apis_sorteio'] = json_decode($row['ids_apis_sorteio'], true);
            } else {
                $row['ids_apis_sorteio'] = [];
            }
            
            // Garantir que desabilitar_whatsapp seja um inteiro (0 ou 1)
            $row['desabilitar_whatsapp'] = isset($row['desabilitar_whatsapp']) ? intval($row['desabilitar_whatsapp']) : 0;
            
            // Garantir que id_api_especifica seja null ou inteiro
            $row['id_api_especifica'] = isset($row['id_api_especifica']) && $row['id_api_especifica'] > 0 ? intval($row['id_api_especifica']) : null;
            
            // Garantir que tentativas_maximas seja um inteiro
            $row['tentativas_maximas'] = isset($row['tentativas_maximas']) ? intval($row['tentativas_maximas']) : 3;
            
            $configs[] = $row;
        }
    }
    
    // Buscar todas as APIs ativas para referência
    $sql_apis = "SELECT id, nome FROM whatsapp_apis WHERE ativo = 1 ORDER BY nome ASC";
    $result_apis = $conn->query($sql_apis);
    
    $apis = [];
    if ($result_apis) {
        while ($row = $result_apis->fetch_assoc()) {
            $apis[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'configs' => $configs,
        'apis' => $apis
    ]);
    
} catch (Exception $e) {
    error_log("Erro em whatsapp_apis/config/listar.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao listar configurações: ' . $e->getMessage()
    ]);
}

$conn->close();
