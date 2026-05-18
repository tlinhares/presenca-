<?php
/**
 * API simples para monitor de leitura facial
 * Funciona independentemente da tabela dispositivos_faciais
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';
require_once '../../config/timezone.php';

function logLeituraFacial($mensagem) {
    $logFile = __DIR__ . '/../../logs/leitura_facial_culto_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $mensagem" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

try {
    $data_hoje = date('Y-m-d');
    
    // Buscar estatísticas reais do banco de dados
    // Total de leituras faciais hoje
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM presencas_culto WHERE data = ? AND tipo_confirmacao = 'facial'");
    if ($stmt) {
        $stmt->bind_param("s", $data_hoje);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $row = $resultado->fetch_assoc();
        $total_leituras = $row['total'];
        $stmt->close();
    } else {
        $total_leituras = 0;
        logLeituraFacial("ERRO: Falha ao buscar total de leituras: " . $conn->error);
    }
    
    // Última leitura facial
    $stmt = $conn->prepare("SELECT horario_confirmacao FROM presencas_culto WHERE data = ? AND tipo_confirmacao = 'facial' ORDER BY data_cadastro DESC LIMIT 1");
    $ultima_leitura = 'Nenhuma';
    if ($stmt) {
        $stmt->bind_param("s", $data_hoje);
        $stmt->execute();
        $resultado = $stmt->get_result();
        if ($resultado->num_rows > 0) {
            $row = $resultado->fetch_assoc();
            $ultima_leitura = $row['horario_confirmacao'];
        }
        $stmt->close();
    } else {
        logLeituraFacial("ERRO: Falha ao buscar última leitura: " . $conn->error);
    }
    
    // Buscar logs do arquivo
    $logs = [];
    $logFile = __DIR__ . '/../../logs/leitura_facial_culto_' . $data_hoje . '.log';
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $logLines = explode("\n", trim($logContent));
        
        // Pegar as últimas 20 linhas
        $logLines = array_slice($logLines, -20);
        
        foreach ($logLines as $line) {
            if (trim($line)) {
                // Extrair timestamp e mensagem
                if (preg_match('/^\[([^\]]+)\]\s*(.+)$/', $line, $matches)) {
                    $logs[] = [
                        'timestamp' => $matches[1],
                        'mensagem' => $matches[2],
                        'tipo' => strpos($matches[2], 'ERRO') !== false ? 'error' : 
                                 (strpos($matches[2], 'sucesso') !== false ? 'success' : 'info')
                    ];
                }
            }
        }
    }
    
    $estatisticas = [
        'total_leituras' => $total_leituras,
        'dispositivos_ativos' => 1,
        'ultima_leitura' => $ultima_leitura,
        'status_sistema' => 'Online'
    ];
    
    // Log da consulta
    logLeituraFacial("Monitor consultado - Total de leituras: $total_leituras");
    
    echo json_encode([
        'success' => true,
        'message' => 'Monitor funcionando',
        'estatisticas' => $estatisticas,
        'logs' => $logs,
        'eventos' => [],
        'leituras_processadas' => 0,
        'used' => [
            'method' => 'GET',
            'timezone_format' => 'local',
            'name' => 'presencas_culto'
        ]
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    logLeituraFacial("ERRO: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
}
?>
