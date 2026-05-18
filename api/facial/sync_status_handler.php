<?php
// api/facial/sync_status_handler.php
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

/**
 * Script para verificar e gerenciar o status das sincronizações faciais
 * Este arquivo centraliza funções para verificar, corrigir e reportar problemas
 * de sincronização facial.
 */

// Incluir arquivos necessários
require_once __DIR__ . '/../../api/conexao.php';

// Função de log para registrar operações
function registrarLog($mensagem) {
    $logs_dir = __DIR__ . '/../../logs';
    
    // Verificar se o diretório de logs existe e tentar criá-lo se não existir
    if (!file_exists($logs_dir)) {
        if (!@mkdir($logs_dir, 0777, true)) {
            // Se não conseguir criar o diretório, usar diretório temporário do sistema
            $logs_dir = sys_get_temp_dir();
        }
    }
    
    // Verificar permissões de escrita no diretório
    if (!is_writable($logs_dir)) {
        // Tentar corrigir permissões
        @chmod($logs_dir, 0777);
        
        // Se ainda não for gravável, usar diretório temporário do sistema
        if (!is_writable($logs_dir)) {
            $logs_dir = sys_get_temp_dir();
        }
    }
    
    $log_file = $logs_dir . '/sync_status_' . date('Y-m-d') . '.log';
    $time = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$time] $mensagem" . PHP_EOL, FILE_APPEND);
}

// Iniciar o registro de log
registrarLog("======== INÍCIO DA VERIFICAÇÃO DE STATUS DE SINCRONIZAÇÃO ========");

try {
    // Iniciar a sessão manualmente (se necessário)
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    // Verificar parâmetros
    $action = isset($_GET['action']) ? $_GET['action'] : 'status';
    $data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
    
    registrarLog("Ação solicitada: $action, Data: $data");
    
    // Verificar se a tabela existe
    $result = $conn->query("SHOW TABLES LIKE 'facial_sync'");
    if ($result->num_rows == 0) {
        throw new Exception("Tabela facial_sync não existe. Execute o script preparar_sincronizacao.php primeiro.");
    }
    
    // Executar a ação solicitada
    switch ($action) {
        case 'status':
            // Obter estatísticas de sincronização para a data
            $stats = obterEstatisticas($conn, $data);
            echo json_encode([
                'status' => 'ok',
                'data' => $data,
                'estatisticas' => $stats
            ]);
            break;
            
        case 'retry_failed':
            // Redefinir registros com falha para pendentes
            $atualizados = resetarFalhas($conn, $data);
            echo json_encode([
                'status' => 'ok',
                'data' => $data,
                'mensagem' => "Foram redefinidos $atualizados registros com falha para tentativa novamente.",
                'atualizados' => $atualizados
            ]);
            break;
            
        case 'fix_permissions':
            // Verificar e corrigir permissões do diretório de logs
            $resultado = verificarECorrigirPermissoes();
            echo json_encode([
                'status' => 'ok',
                'mensagem' => $resultado['mensagem'],
                'sucesso' => $resultado['sucesso']
            ]);
            break;
            
        default:
            throw new Exception("Ação desconhecida: $action");
    }
    
    registrarLog("======== FIM DA VERIFICAÇÃO DE STATUS DE SINCRONIZAÇÃO ========");
    
} catch (Exception $e) {
    registrarLog("ERRO: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Ocorreu um erro: ' . $e->getMessage()
    ]);
    
    registrarLog("======== FIM DO PROCESSO COM ERRO ========");
}

/**
 * Obtém estatísticas de sincronização para uma data específica
 */
function obterEstatisticas($conn, $data) {
    $stats = [
        'total' => 0,
        'pendentes' => 0,
        'sincronizados' => 0,
        'falhas' => 0,
        'detalhes_falhas' => []
    ];
    
    // Total de registros
    $sql = "SELECT COUNT(*) as total FROM facial_sync WHERE data = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $data);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['total'] = (int)$row['total'];
    
    // Contagem por status
    $sql = "
        SELECT status, COUNT(*) as qtd 
        FROM facial_sync 
        WHERE data = ? 
        GROUP BY status
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $data);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] == 'pendente') {
            $stats['pendentes'] = (int)$row['qtd'];
        } elseif ($row['status'] == 'sincronizado') {
            $stats['sincronizados'] = (int)$row['qtd'];
        } elseif ($row['status'] == 'falha') {
            $stats['falhas'] = (int)$row['qtd'];
        }
    }
    
    // Detalhes das falhas
    if ($stats['falhas'] > 0) {
        $sql = "
            SELECT fs.id, fs.id_usuario, u.nome, fs.detalhes, fs.horario_sync
            FROM facial_sync fs
            JOIN usuarios u ON fs.id_usuario = u.id
            WHERE fs.data = ? AND fs.status = 'falha'
            ORDER BY fs.horario_sync DESC
            LIMIT 20
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $data);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $stats['detalhes_falhas'][] = [
                'id' => $row['id'],
                'id_usuario' => $row['id_usuario'],
                'nome' => $row['nome'],
                'detalhes' => $row['detalhes'],
                'horario' => $row['horario_sync']
            ];
        }
    }
    
    return $stats;
}

/**
 * Redefine registros com falha para pendentes para nova tentativa
 */
function resetarFalhas($conn, $data) {
    $sql = "
        UPDATE facial_sync 
        SET status = 'pendente', 
            detalhes = CONCAT('Redefinido para nova tentativa em ', NOW(), '. Erro anterior: ', IFNULL(detalhes, 'Desconhecido'))
        WHERE data = ? AND status = 'falha'
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $data);
    $stmt->execute();
    
    return $stmt->affected_rows;
}

/**
 * Verifica e corrige permissões do diretório de logs
 */
function verificarECorrigirPermissoes() {
    $logs_dir = __DIR__ . '/../../logs';
    $resultado = [
        'sucesso' => false,
        'mensagem' => ''
    ];
    
    // Verificar se o diretório existe
    if (!file_exists($logs_dir)) {
        // Tentar criar o diretório
        if (@mkdir($logs_dir, 0777, true)) {
            $resultado['mensagem'] = "O diretório de logs foi criado com sucesso.";
            $resultado['sucesso'] = true;
        } else {
            $resultado['mensagem'] = "Não foi possível criar o diretório de logs. Verificando diretório temporário...";
            
            // Verificar diretório temporário
            $temp_dir = sys_get_temp_dir();
            if (is_writable($temp_dir)) {
                $resultado['mensagem'] .= " O diretório temporário ($temp_dir) está disponível para uso.";
                $resultado['sucesso'] = true;
            } else {
                $resultado['mensagem'] .= " O diretório temporário também não está disponível.";
            }
        }
    } else {
        // Verificar permissões
        if (is_writable($logs_dir)) {
            $resultado['mensagem'] = "O diretório de logs existe e tem permissões corretas.";
            $resultado['sucesso'] = true;
        } else {
            // Tentar corrigir permissões
            if (@chmod($logs_dir, 0777)) {
                $resultado['mensagem'] = "As permissões do diretório de logs foram corrigidas.";
                $resultado['sucesso'] = true;
            } else {
                $resultado['mensagem'] = "Não foi possível corrigir as permissões do diretório de logs. Talvez o script PHP não tenha permissões suficientes.";
                
                // Verificar proprietário do diretório
                $owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($logs_dir)) : ['name' => 'desconhecido'];
                $resultado['mensagem'] .= " Proprietário atual: " . $owner['name'];
                
                // Verificar diretório temporário
                $temp_dir = sys_get_temp_dir();
                if (is_writable($temp_dir)) {
                    $resultado['mensagem'] .= " O diretório temporário ($temp_dir) está disponível como alternativa.";
                    $resultado['sucesso'] = true;
                }
            }
        }
    }
    
    return $resultado;
}
?> 