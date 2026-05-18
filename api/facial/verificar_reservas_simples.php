<?php
// api/facial/verificar_reservas_simples.php
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Incluir arquivos necessários
require_once __DIR__ . '/../../api/conexao.php';
require_once 'verificar_sessao_admin.php';

// Verificar se o usuário é administrador
verificarAdmin(basename(__FILE__));

// Configurar tratamento de erros personalizado
function gerenciarErros($errno, $errstr, $errfile, $errline) {
    $erro = "Erro #$errno: $errstr em $errfile:$errline";
    error_log($erro);
    
    // Não enviar detalhes do erro para o cliente
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Ocorreu um erro interno no servidor. Verifique os logs para mais detalhes.'
        ]);
    }
    exit;
}
set_error_handler('gerenciarErros', E_ALL & ~E_NOTICE & ~E_WARNING);

try {
    // Usar a data de hoje
    $data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
    
    // Registrar logs para depuração
    $logs_dir = __DIR__ . '/../../logs';
    if (!file_exists($logs_dir)) {
        @mkdir($logs_dir, 0777, true);
    }
    $log_file = $logs_dir . '/verificacao_reservas_' . date('Y-m-d') . '.log';
    $time = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$time] Verificando reservas para a data: $data" . PHP_EOL, FILE_APPEND);
    
    // Verificar se o banco de dados está conectado
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Erro na conexão com o banco de dados: " . ($conn ? $conn->connect_error : "Conexão não estabelecida"));
    }
    
    // Verificar se existem reservas para hoje
    $sql = "SELECT COUNT(*) AS total FROM reservas_almoco WHERE data = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $data);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_reservas = $row['total'];
    
    @file_put_contents($log_file, "[$time] Total de reservas: $total_reservas" . PHP_EOL, FILE_APPEND);
    
    // Verificar registros na tabela facial_sync
    $sql_sync = "SELECT COUNT(*) AS total FROM facial_sync WHERE data = ?";
    $stmt_sync = $conn->prepare($sql_sync);
    
    if (!$stmt_sync) {
        // Se a tabela não existir, criá-la
        if (strpos($conn->error, "Table") !== false && strpos($conn->error, "doesn't exist") !== false) {
            $sql_criar = "CREATE TABLE facial_sync (
                id INT(11) NOT NULL AUTO_INCREMENT,
                id_usuario INT(11) NOT NULL,
                data DATE NOT NULL,
                status ENUM('pendente', 'sincronizado', 'falha') NOT NULL DEFAULT 'pendente',
                horario_sync DATETIME DEFAULT NULL,
                detalhes TEXT,
                PRIMARY KEY (id),
                KEY idx_usuario_data (id_usuario, data)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            if (!$conn->query($sql_criar)) {
                throw new Exception("Erro ao criar tabela facial_sync: " . $conn->error);
            }
            
            @file_put_contents($log_file, "[$time] Tabela facial_sync criada com sucesso" . PHP_EOL, FILE_APPEND);
            $total_sync = 0;
        } else {
            throw new Exception("Erro ao preparar consulta para facial_sync: " . $conn->error);
        }
    } else {
        $stmt_sync->bind_param("s", $data);
        $stmt_sync->execute();
        $result_sync = $stmt_sync->get_result();
        $row_sync = $result_sync->fetch_assoc();
        $total_sync = $row_sync['total'];
    }
    
    @file_put_contents($log_file, "[$time] Total de registros facial_sync: $total_sync" . PHP_EOL, FILE_APPEND);
    
    // Obter detalhes por status se houver registros
    $status_details = [
        'pendentes' => 0,
        'sincronizados' => 0,
        'falhas' => 0
    ];
    
    if ($total_sync > 0) {
        $sql_stats = "SELECT status, COUNT(*) as qtd FROM facial_sync WHERE data = ? GROUP BY status";
        $stmt_stats = $conn->prepare($sql_stats);
        $stmt_stats->bind_param("s", $data);
        $stmt_stats->execute();
        $result_stats = $stmt_stats->get_result();
        
        while ($row_stats = $result_stats->fetch_assoc()) {
            $status_details[$row_stats['status']] = (int)$row_stats['qtd'];
        }
    }
    
    // Responder com o resultado
    echo json_encode([
        'status' => 'ok',
        'data' => $data,
        'total_reservas' => $total_reservas,
        'total_sync' => $total_sync,
        'status_details' => $status_details,
        'acao_recomendada' => ($total_reservas > $total_sync) 
            ? "Execute o script verificar_e_preparar.php para criar os registros de sincronização faltantes" 
            : "Tudo parece estar em ordem. Você pode executar a sincronização se houver registros pendentes."
    ]);
    
    @file_put_contents($log_file, "[$time] Verificação concluída com sucesso" . PHP_EOL, FILE_APPEND);
    
} catch (Exception $e) {
    // Registrar erro
    error_log("Erro em verificar_reservas_simples.php: " . $e->getMessage());
    
    // Registrar em arquivo de log específico
    $logs_dir = __DIR__ . '/../../logs';
    $log_file = $logs_dir . '/verificacao_reservas_error_' . date('Y-m-d') . '.log';
    $time = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$time] ERRO: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    
    // Responder ao cliente
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Ocorreu um erro: ' . $e->getMessage()
    ]);
}
?> 