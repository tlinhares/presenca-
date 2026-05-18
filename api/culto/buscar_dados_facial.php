<?php
/**
 * API para buscar dados do dispositivo facial usando recordFinder.cgi
 * Baseado no exemplo Python fornecido pelo usuário
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responder a requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../config/timezone.php';

// Função para log
function logBuscaFacial($mensagem) {
    $logFile = __DIR__ . '/../../logs/busca_facial_culto_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $mensagem\n", FILE_APPEND);
}

// Função para fazer requisição HTTP com autenticação digest
function fazerRequisicaoFacial($url, $username, $password) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        throw new Exception("Erro cURL: $error");
    }
    
    if ($httpCode !== 200) {
        throw new Exception("Erro HTTP $httpCode: $response");
    }
    
    return $response;
}

try {
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido. Use GET.');
    }

    // Obter parâmetros
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
    $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
    $ip_dispositivo = $_GET['ip_dispositivo'] ?? null;

    // Se não especificou IP, buscar dispositivos de culto ativos
    if (!$ip_dispositivo) {
        $stmt = $conn->prepare("
            SELECT ip, nome, usuario, senha 
            FROM dispositivos_faciais 
            WHERE tipo_dispositivo = 'culto' AND ativo = 1
        ");
        $stmt->execute();
        $dispositivos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        if (empty($dispositivos)) {
            throw new Exception('Nenhum dispositivo de culto ativo encontrado');
        }
    } else {
        // Buscar dispositivo específico
        $stmt = $conn->prepare("
            SELECT ip, nome, usuario, senha 
            FROM dispositivos_faciais 
            WHERE ip = ? AND tipo_dispositivo = 'culto' AND ativo = 1
        ");
        $stmt->bind_param("s", $ip_dispositivo);
        $stmt->execute();
        $dispositivos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        if (empty($dispositivos)) {
            throw new Exception("Dispositivo não encontrado ou inativo: $ip_dispositivo");
        }
    }

    // Converter datas para timestamp Unix
    $start_time = strtotime($data_inicio . ' 00:00:00');
    $end_time = strtotime($data_fim . ' 23:59:59');

    logBuscaFacial("Buscando dados de $data_inicio até $data_fim");

    $resultados = [];

    // Processar cada dispositivo
    foreach ($dispositivos as $dispositivo) {
        $device_ip = $dispositivo['ip'];
        $username = $dispositivo['usuario'];
        $password = $dispositivo['senha'];
        
        logBuscaFacial("Consultando dispositivo: {$dispositivo['nome']} ($device_ip)");

        // Construir URL conforme exemplo Python
        $url = "http://{$device_ip}/cgi-bin/recordFinder.cgi?action=find&name=AccessControlCardRec&StartTime={$start_time}&EndTime={$end_time}";
        
        try {
            $response = fazerRequisicaoFacial($url, $username, $password);
            
            // Parsear resposta XML (dispositivos faciais geralmente retornam XML)
            $xml = simplexml_load_string($response);
            
            if ($xml === false) {
                // Se não for XML, tratar como texto
                $dados = [
                    'dispositivo' => $dispositivo['nome'],
                    'ip' => $device_ip,
                    'resposta_raw' => $response,
                    'tipo' => 'texto'
                ];
            } else {
                // Processar XML
                $dados = [
                    'dispositivo' => $dispositivo['nome'],
                    'ip' => $device_ip,
                    'xml' => $xml,
                    'tipo' => 'xml'
                ];
            }
            
            $resultados[] = $dados;
            logBuscaFacial("Sucesso ao consultar {$dispositivo['nome']}");
            
        } catch (Exception $e) {
            logBuscaFacial("ERRO ao consultar {$dispositivo['nome']}: " . $e->getMessage());
            $resultados[] = [
                'dispositivo' => $dispositivo['nome'],
                'ip' => $device_ip,
                'erro' => $e->getMessage(),
                'tipo' => 'erro'
            ];
        }
    }

    // Resposta de sucesso
    echo json_encode([
        'status' => 'success',
        'message' => 'Dados buscados com sucesso',
        'data' => [
            'periodo' => [
                'inicio' => $data_inicio,
                'fim' => $data_fim,
                'start_time' => $start_time,
                'end_time' => $end_time
            ],
            'dispositivos' => $resultados,
            'total_dispositivos' => count($dispositivos)
        ],
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    logBuscaFacial("ERRO: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => time()
    ]);
}

$conn->close();
?>
