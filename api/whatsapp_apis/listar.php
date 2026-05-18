<?php
/**
 * API - Listar APIs de WhatsApp
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
        echo json_encode([
            'status' => 'ok',
            'apis' => [],
            'mensagem' => 'Tabela não existe. Execute o script de inicialização.'
        ]);
        exit;
    }
    
    $sql = "SELECT 
                id,
                nome,
                url_mensagem,
                url_arquivo,
                numero_whatsapp,
                ativo,
                prioridade,
                total_envios,
                total_falhas,
                ultima_utilizacao,
                observacoes,
                criado_em,
                atualizado_em
            FROM whatsapp_apis
            ORDER BY prioridade ASC, nome ASC";
    
    $result = $conn->query($sql);
    
    $apis = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Não retornar token por segurança
            unset($row['token']);
            // Garantir que ID seja um inteiro
            $row['id'] = intval($row['id']);
            $row['ativo'] = intval($row['ativo']);
            $row['prioridade'] = intval($row['prioridade']);
            $row['total_envios'] = intval($row['total_envios']);
            $row['total_falhas'] = intval($row['total_falhas']);
            $apis[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'apis' => $apis
    ]);
    
} catch (Exception $e) {
    error_log("Erro em whatsapp_apis/listar.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao listar APIs: ' . $e->getMessage()
    ]);
}

$conn->close();
