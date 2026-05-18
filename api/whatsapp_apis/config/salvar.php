<?php
/**
 * API - Salvar Configurações de Notificações
 * Salva todas as configurações de uma vez
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';

// Apenas admin pode acessar
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../../../api/conexao.php';
require_once __DIR__ . '/../../../core/services/WhatsAppService.php';

try {
    $configs = $_POST['configs'] ?? [];
    
    if (empty($configs) || !is_array($configs)) {
        throw new Exception('Nenhuma configuração fornecida');
    }
    
    $conn->begin_transaction();
    
    try {
        foreach ($configs as $config) {
            $tipo_notificacao = trim($config['tipo_notificacao'] ?? '');
            $modo_selecao = $config['modo_selecao'] ?? 'sorteio';
            $id_api_especifica = isset($config['id_api_especifica']) && $config['id_api_especifica'] > 0 ? intval($config['id_api_especifica']) : null;
            $ids_apis_sorteio = isset($config['ids_apis_sorteio']) && is_array($config['ids_apis_sorteio']) ? json_encode($config['ids_apis_sorteio']) : null;
            $tentativas_maximas = isset($config['tentativas_maximas']) ? intval($config['tentativas_maximas']) : 3;
            $desabilitar_whatsapp = isset($config['desabilitar_whatsapp']) ? intval($config['desabilitar_whatsapp']) : 0;
            $observacoes = trim($config['observacoes'] ?? '');
            $observacoes = empty($observacoes) ? null : $observacoes;
            
            if (empty($tipo_notificacao)) {
                continue; // Pular configurações sem tipo
            }
            
            // Validar modo_selecao
            if (!in_array($modo_selecao, ['especifica', 'sorteio', 'desabilitado'])) {
                $modo_selecao = 'sorteio';
            }
            
            // Validar tentativas_maximas (1-10)
            if ($tentativas_maximas < 1 || $tentativas_maximas > 10) {
                $tentativas_maximas = 3;
            }
            
            // Se modo é 'especifica', validar que API existe
            if ($modo_selecao === 'especifica' && $id_api_especifica) {
                $sql_check = "SELECT id FROM whatsapp_apis WHERE id = ? AND ativo = 1";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bind_param("i", $id_api_especifica);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                if ($result_check->num_rows === 0) {
                    $stmt_check->close();
                    throw new Exception("API ID $id_api_especifica não encontrada ou inativa para tipo $tipo_notificacao");
                }
                $stmt_check->close();
            }
            
            // Se modo é 'sorteio', validar que pelo menos uma API foi selecionada
            if ($modo_selecao === 'sorteio' && $ids_apis_sorteio) {
                $ids_array = json_decode($ids_apis_sorteio, true);
                if (empty($ids_array) || !is_array($ids_array)) {
                    throw new Exception("Selecione pelo menos uma API para sorteio no tipo $tipo_notificacao");
                }
                
                // Validar que todas as APIs existem e estão ativas
                $ids_str = implode(',', array_map('intval', $ids_array));
                $sql_check = "SELECT COUNT(*) as total FROM whatsapp_apis WHERE id IN ($ids_str) AND ativo = 1";
                $result_check = $conn->query($sql_check);
                $check = $result_check->fetch_assoc();
                if ($check['total'] != count($ids_array)) {
                    throw new Exception("Uma ou mais APIs selecionadas não existem ou estão inativas para tipo $tipo_notificacao");
                }
            }
            
            // Usar INSERT ... ON DUPLICATE KEY UPDATE
            $sql = "INSERT INTO whatsapp_config_notificacoes 
                    (tipo_notificacao, modo_selecao, id_api_especifica, ids_apis_sorteio, tentativas_maximas, desabilitar_whatsapp, observacoes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        modo_selecao = VALUES(modo_selecao),
                        id_api_especifica = VALUES(id_api_especifica),
                        ids_apis_sorteio = VALUES(ids_apis_sorteio),
                        tentativas_maximas = VALUES(tentativas_maximas),
                        desabilitar_whatsapp = VALUES(desabilitar_whatsapp),
                        observacoes = VALUES(observacoes)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssisiii", $tipo_notificacao, $modo_selecao, $id_api_especifica, $ids_apis_sorteio, $tentativas_maximas, $desabilitar_whatsapp, $observacoes);
            $stmt->execute();
            $stmt->close();
        }
        
        $conn->commit();
        
        // Limpar cache de configurações do WhatsAppService
        WhatsAppService::limparCache();
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Configurações salvas com sucesso'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Erro em whatsapp_apis/config/salvar.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}

$conn->close();
