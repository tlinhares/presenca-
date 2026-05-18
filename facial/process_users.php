<?php
// Ativar reportamento de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Função para verificar se o dispositivo está online
function verificarDispositivo($ip, $timeout = 1) {
    $socket = @fsockopen($ip, 80, $errno, $errstr, $timeout);
    if ($socket) {
        fclose($socket);
        return true;
    }
    return false;
}

try {
    // Verificar se a extensão cURL está disponível
    if (!function_exists('curl_init')) {
        throw new Exception("A extensão cURL não está disponível no servidor.");
    }

    // Configurações do dispositivo
    $deviceIp = '10.144.129.69';
    $deviceUser = 'admin';
    $devicePass = 'Arcs2901';
    
    // Verificar se o dispositivo está online
    $deviceDisponivel = verificarDispositivo($deviceIp);
    if (!$deviceDisponivel) {
        throw new Exception("Dispositivo não está respondendo. Verifique se o IP ($deviceIp) está correto e se o dispositivo está acessível na rede.");
    }

    // Obter conteúdo JSON enviado
    $jsonContent = file_get_contents('php://input');
    if (empty($jsonContent)) {
        throw new Exception("Nenhum dado recebido.");
    }

    $data = json_decode($jsonContent, true);

    // Verificar se há dados válidos
    if (!$data || !isset($data['UserList']) || empty($data['UserList'])) {
        throw new Exception("Dados inválidos ou nenhum usuário fornecido.");
    }

    // URL para cadastro de múltiplos usuários
    $url = "http://$deviceIp/cgi-bin/AccessUser.cgi?action=insertMulti";

    // Inicializa cURL
    $ch = curl_init();

    // Configurações do cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonContent);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "$deviceUser:$devicePass");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Tempo limite para conexão (em segundos)
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);       // Tempo limite total (em segundos)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Desativar verificação de certificado SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Desativar verificação de host SSL
    curl_setopt($ch, CURLOPT_FAILONERROR, false);    // Não falhar em erros HTTP
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0); // Usar HTTP 1.0 que é mais compatível

    // Executa a solicitação
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Verificar erros de cURL
    $curlError = '';
    if (curl_errno($ch)) {
        $curlError = curl_error($ch);
        
        // Tratamentos específicos para erros comuns
        if (strpos($curlError, 'Empty reply from server') !== false) {
            $curlError .= ' - O dispositivo não respondeu à solicitação. Isso geralmente indica que o dispositivo aceitou o comando, mas não retornou uma resposta. Verifique no dispositivo se o cadastro foi realizado.';
            $httpCode = 0; // Consideramos como potencial sucesso
        }
    }

    // Fecha a sessão cURL
    curl_close($ch);

    // Tentar registrar log
    try {
        // Registro detalhado em log
        $logDir = '../logs';
        if (!is_dir($logDir) && !@mkdir($logDir, 0777, true)) {
            $logDir = './logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
        }

        $logFile = $logDir . '/cadastro_multiplos_' . date('Y-m-d') . '.log';
        $userCount = count($data['UserList']);
        $userIds = array_column($data['UserList'], 'UserID');
        $userIdsStr = implode(',', $userIds);

        $logMessage = date('Y-m-d H:i:s') . " - Tentativa de cadastro de $userCount usuários (IDs: $userIdsStr) - Status: $httpCode";
        if ($response) {
            $logMessage .= " - Resposta: " . $response;
        }
        if ($curlError) {
            $logMessage .= " - Erro cURL: " . $curlError;
        }

        if (is_writable($logDir) || (!file_exists($logFile) && is_writable(dirname($logDir)))) {
            @file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
        }
    } catch (Exception $e) {
        // Ignorar erros de log
    }

    // Formatar resposta para evitar problemas de codificação
    $message = $response;
    if (empty($message) && $curlError) {
        if (strpos($curlError, 'Empty reply from server') !== false) {
            $message = "O dispositivo não retornou resposta, mas provavelmente aceitou o comando. Verifique no dispositivo se os usuários foram cadastrados.";
        } else {
            $message = "Erro de conexão: " . $curlError;
        }
    } elseif (empty($message) && $httpCode === 0) {
        $message = "Não foi possível conectar ao dispositivo. Verifique se o IP está correto e se o dispositivo está acessível.";
    }

    // Retorna o resultado
    // No caso de "Empty reply from server", consideramos como provável sucesso
    if (($httpCode >= 200 && $httpCode < 300) || 
        ($httpCode === 0 && strpos($curlError, 'Empty reply from server') !== false)) {
        echo json_encode([
            'success' => true,
            'message' => $message,
            'statusCode' => $httpCode,
            'users_processed' => $userCount
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $message ?: $curlError,
            'statusCode' => $httpCode,
            'error' => $curlError
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'statusCode' => 500,
        'error' => 'Erro de execução'
    ], JSON_UNESCAPED_UNICODE);
} 