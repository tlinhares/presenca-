<?php
// api/facial/obter_logs.php
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Autenticação
session_start();
if (!isset($_SESSION['usuario']) || !isset($_SESSION['nivel']) || $_SESSION['nivel'] != 'admin') {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Acesso negado. Você precisa ser um administrador para acessar este recurso.'
    ]);
    exit;
}

try {
    // Verificar parâmetro
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
    
    if (empty($tipo)) {
        throw new Exception("Tipo de log não especificado");
    }
    
    // Definir o diretório de logs
    $logs_dir = __DIR__ . '/../../logs';
    if (!file_exists($logs_dir)) {
        $logs_dir = sys_get_temp_dir();
    }
    
    // Definir arquivo de log com base no tipo
    $log_file = '';
    $data_hoje = date('Y-m-d');
    
    switch ($tipo) {
        case 'sincronizacao':
            $log_file = $logs_dir . '/sincronizacao_' . $data_hoje . '.log';
            break;
        case 'preparacao':
            $log_file = $logs_dir . '/preparar_sync_' . $data_hoje . '.log';
            break;
        case 'execucao':
            $log_file = $logs_dir . '/sync_output_' . $data_hoje . '.log';
            break;
        case 'php':
            $log_file = $logs_dir . '/php_errors.log';
            break;
        case 'status':
            $log_file = $logs_dir . '/sync_status_' . $data_hoje . '.log';
            break;
        default:
            throw new Exception("Tipo de log desconhecido: $tipo");
    }
    
    // Verificar se o arquivo existe
    if (!file_exists($log_file)) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Arquivo de log não encontrado',
            'linhas' => []
        ]);
        exit;
    }
    
    // Ler o arquivo (limitado às últimas 1000 linhas para arquivos grandes)
    $linhas = [];
    $max_linhas = 1000;
    
    // Verificar o tamanho do arquivo
    $tamanho_arquivo = filesize($log_file);
    
    if ($tamanho_arquivo > 5 * 1024 * 1024) { // Se for maior que 5MB, lê apenas o final
        $handle = fopen($log_file, 'r');
        
        // Posicionar o ponteiro próximo ao final do arquivo (considerando os últimos ~100KB)
        fseek($handle, -min(100 * 1024, $tamanho_arquivo), SEEK_END);
        
        // Descartar a primeira linha (pode estar incompleta)
        fgets($handle);
        
        // Ler o restante das linhas
        while (($linha = fgets($handle)) !== false && count($linhas) < $max_linhas) {
            $linhas[] = rtrim($linha);
        }
        
        fclose($handle);
    } else {
        // Abrir o arquivo e ler linha a linha
        $handle = fopen($log_file, 'r');
        
        // Se o arquivo tiver muitas linhas, pular as iniciais
        $total_linhas = count(file($log_file));
        
        if ($total_linhas > $max_linhas) {
            $linhas_para_pular = $total_linhas - $max_linhas;
            
            // Pular as primeiras linhas
            for ($i = 0; $i < $linhas_para_pular && !feof($handle); $i++) {
                fgets($handle);
            }
        }
        
        // Ler as linhas restantes
        while (($linha = fgets($handle)) !== false) {
            $linhas[] = rtrim($linha);
        }
        
        fclose($handle);
    }
    
    // Inverter a ordem para mostrar as mais recentes no topo
    $linhas = array_reverse($linhas);
    
    // Gerar resposta
    echo json_encode([
        'status' => 'ok',
        'arquivo' => $log_file,
        'tamanho' => $tamanho_arquivo,
        'total_linhas' => count($linhas),
        'linhas' => $linhas
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao ler logs: ' . $e->getMessage()
    ]);
}
?>