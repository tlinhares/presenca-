<?php
header('Content-Type: application/json; charset=UTF-8');

// Verificar se o usuário está logado (opcional para monitoramento)
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();



require_once __DIR__ . '/../conexao.php';

$logFile = __DIR__ . '/../../logs/leitura_facial_culto_' . date('Y-m-d') . '.log';

// Função para classificar o tipo de log
function classificarLog($mensagem) {
    if (strpos($mensagem, 'ERRO:') !== false || strpos($mensagem, 'Falha') !== false) {
        return 'error';
    } elseif (strpos($mensagem, 'Presença registrada') !== false || strpos($mensagem, 'Nova presença') !== false) {
        return 'success';
    } elseif (strpos($mensagem, 'Recebida leitura') !== false || strpos($mensagem, 'Processando') !== false) {
        return 'info';
    } elseif (strpos($mensagem, 'Aviso') !== false || strpos($mensagem, 'Warning') !== false) {
        return 'warning';
    }
    return 'info';
}

// Função para extrair timestamp da linha
function extrairTimestamp($linha) {
    if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $linha, $matches)) {
        return $matches[1];
    }
    return date('Y-m-d H:i:s');
}

// Função para extrair mensagem da linha
function extrairMensagem($linha) {
    if (preg_match('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] (.+)/', $linha, $matches)) {
        return $matches[1];
    }
    return $linha;
}

// Obter estatísticas do banco de dados
$estatisticas = [
    'total_leituras' => 0,
    'dispositivos_ativos' => 0,
    'ultima_leitura' => 'Nenhuma',
    'status_sistema' => 'Online'
];

try {
    // Total de leituras hoje
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM presencas_culto 
        WHERE DATE(data) = CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $estatisticas['total_leituras'] = $row['total'];
    }
    
    // Dispositivos ativos
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM dispositivos_faciais 
        WHERE tipo_dispositivo = 'culto' AND ativo = 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $estatisticas['dispositivos_ativos'] = $row['total'];
    }
    
    // Última leitura
    $stmt = $conn->prepare("
        SELECT pc.horario_confirmacao, u.nome 
        FROM presencas_culto pc
        JOIN usuarios u ON pc.id_usuario = u.id
        WHERE DATE(pc.data) = CURDATE()
        ORDER BY pc.horario_confirmacao DESC
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $estatisticas['ultima_leitura'] = $row['nome'] . ' às ' . $row['horario_confirmacao'];
    }
    
} catch (Exception $e) {
    // Em caso de erro, usar valores padrão
}

// Processar logs
$logs = [];

if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $recentLines = array_slice($lines, -100); // Últimas 100 linhas
    
    foreach ($recentLines as $line) {
        $timestamp = extrairTimestamp($line);
        $mensagem = extrairMensagem($line);
        $tipo = classificarLog($mensagem);
        
        $logs[] = [
            'timestamp' => $timestamp,
            'mensagem' => htmlspecialchars($mensagem),
            'tipo' => $tipo
        ];
    }
} else {
    $logs[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'mensagem' => 'Nenhum log encontrado para hoje.',
        'tipo' => 'info'
    ];
}

echo json_encode([
    'success' => true,
    'logs' => $logs,
    'estatisticas' => $estatisticas,
    'total_lines' => file_exists($logFile) ? count(file($logFile)) : 0,
    'showing' => count($logs)
]);
?>
