<?php
/**
 * API - Verificar Status de Todas as Sessões WhatsApp
 * Retorna o status de todas as APIs cadastradas
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
        throw new Exception('Tabela whatsapp_apis não existe.');
    }
    
    // Buscar todas as APIs
    $sql = "SELECT 
                id,
                nome,
                url_mensagem,
                token
            FROM whatsapp_apis
            ORDER BY prioridade ASC, nome ASC";
    
    $result = $conn->query($sql);
    
    $status_apis = [];
    
    if ($result) {
        while ($api = $result->fetch_assoc()) {
            $id = intval($api['id']);
            $status_info = verificarStatusAPI($api);
            $status_apis[$id] = $status_info;
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'status_apis' => $status_apis
    ]);
    
} catch (Exception $e) {
    error_log("Erro em whatsapp_apis/verificar_status_todos.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage(),
        'status_apis' => []
    ]);
}

/**
 * Função para verificar status de uma API específica
 */
function verificarStatusAPI($api) {
    try {
        // Extrair URL base da url_mensagem
        $url_mensagem = $api['url_mensagem'];
        $url_parts = parse_url($url_mensagem);
        
        if (!$url_parts || !isset($url_parts['scheme']) || !isset($url_parts['host'])) {
            return [
                'status_sessao' => 'erro',
                'mensagem' => 'URL de mensagem inválida'
            ];
        }
        
        $base_url = $url_parts['scheme'] . '://' . $url_parts['host'];
        if (isset($url_parts['port'])) {
            $base_url .= ':' . $url_parts['port'];
        }
        
        // Obter nome da sessão (usa nome da API)
        $nome_sessao = trim($api['nome'] ?? '');
        
        if (empty($nome_sessao)) {
            return [
                'status_sessao' => 'erro',
                'mensagem' => 'Nome da sessão não encontrado'
            ];
        }
        
        // Construir URL no formato: /api/{nomedasessao}/status-session
        $status_url = $base_url . '/api/' . urlencode($nome_sessao) . '/status-session';
        
        // Tratamento do token
        $token_clean = preg_replace('/^Bearer\s+/i', '', trim($api['token']));
        $token_clean = trim($token_clean);
        
        if (empty($token_clean)) {
            return [
                'status_sessao' => 'erro',
                'mensagem' => 'Token não encontrado'
            ];
        }
        
        // Fazer requisição com Bearer primeiro
        $resultado = fazerRequisicaoStatus($status_url, $token_clean, 'Bearer');
        
        // Se retornar 401, tentar sem Bearer
        if ($resultado['http_code'] === 401) {
            $resultado = fazerRequisicaoStatus($status_url, $token_clean, 'sem_bearer');
        }
        
        // Processar resposta
        $response_data = $resultado['response'];
        $http_code = $resultado['http_code'];
        
        $status_sessao = 'desconhecido';
        $mensagem = '';
        
        if ($http_code === 200) {
            $json_data = json_decode($response_data, true);
            
            // Log para debug (remover em produção se necessário)
            error_log("Resposta status-session para API {$api['id']}: " . $response_data);
            
            if ($json_data) {
                // Tentar diferentes campos possíveis na resposta
                $status_raw = null;
                
                if (isset($json_data['status']) && is_string($json_data['status'])) {
                    $status_raw = $json_data['status'];
                } elseif (isset($json_data['state']) && is_string($json_data['state'])) {
                    $status_raw = $json_data['state'];
                } elseif (isset($json_data['sessionStatus']) && is_string($json_data['sessionStatus'])) {
                    $status_raw = $json_data['sessionStatus'];
                } elseif (isset($json_data['connectionStatus']) && is_string($json_data['connectionStatus'])) {
                    $status_raw = $json_data['connectionStatus'];
                } elseif (isset($json_data['instance']['state']) && is_string($json_data['instance']['state'])) {
                    // Formato comum do Evolution: { "instance": { "state": "open" } }
                    $status_raw = $json_data['instance']['state'];
                } elseif (isset($json_data['instance']['status']) && is_string($json_data['instance']['status'])) {
                    $status_raw = $json_data['instance']['status'];
                } elseif (isset($json_data['instance']['connectionStatus']['state']) && is_string($json_data['instance']['connectionStatus']['state'])) {
                    $status_raw = $json_data['instance']['connectionStatus']['state'];
                } elseif (is_string($json_data)) {
                    // Se a resposta for uma string simples
                    $status_raw = $json_data;
                }
                
                if ($status_raw !== null) {
                    $status_sessao = strtolower(trim($status_raw));
                    
                    // Normalizar status - Evolution API geralmente retorna: open, close, connecting
                    if (in_array($status_sessao, ['open', 'opened', 'connected', 'authenticated', 'ready', 'qrcode'])) {
                        $status_sessao = 'ativa';
                        $mensagem = 'Sessão ativa e conectada';
                    } elseif (in_array($status_sessao, ['close', 'closed', 'disconnected', 'logout', 'loggedout'])) {
                        $status_sessao = 'inativa';
                        $mensagem = 'Sessão desconectada';
                    } elseif (in_array($status_sessao, ['connecting', 'connect'])) {
                        $status_sessao = 'conectando';
                        $mensagem = 'Sessão em processo de conexão';
                    } else {
                        // Se não reconhecer, manter o status original mas marcar como desconhecido
                        $status_sessao = 'desconhecido';
                        $mensagem = 'Status: ' . $status_raw . ' (não reconhecido)';
                    }
                } else {
                    // Se não encontrou campo de status, verificar se é um objeto vazio ou array
                    if (empty($json_data)) {
                        $status_sessao = 'erro';
                        $mensagem = 'Resposta vazia do servidor';
                    } else {
                        $status_sessao = 'erro';
                        $mensagem = 'Campo de status não encontrado na resposta. Estrutura: ' . json_encode(array_keys($json_data));
                    }
                }
            } else {
                // Se não conseguiu decodificar JSON, verificar se é uma string
                if (!empty($response_data)) {
                    $status_sessao = 'erro';
                    $mensagem = 'Resposta não é JSON válido: ' . substr($response_data, 0, 100);
                } else {
                    $status_sessao = 'erro';
                    $mensagem = 'Resposta vazia do servidor';
                }
            }
        } elseif ($http_code === 401) {
            $status_sessao = 'erro';
            $mensagem = 'Token inválido ou expirado';
        } elseif ($http_code === 404) {
            $status_sessao = 'erro';
            $mensagem = 'Sessão não encontrada no servidor';
        } elseif ($http_code === 0) {
            // Erro de conexão (timeout, DNS, etc)
            $status_sessao = 'erro';
            $error_msg = isset($resultado['error']) && !empty($resultado['error']) ? $resultado['error'] : 'Erro ao conectar com o servidor (timeout ou servidor inacessível)';
            $mensagem = $error_msg;
        } elseif ($http_code >= 500) {
            $status_sessao = 'erro';
            $mensagem = 'Erro no servidor WhatsApp (HTTP ' . $http_code . ')';
        } else {
            $status_sessao = 'erro';
            $mensagem = 'Erro HTTP ' . $http_code . (isset($resultado['response']) ? ': ' . substr($resultado['response'], 0, 100) : '');
        }
        
        return [
            'status_sessao' => $status_sessao,
            'mensagem' => $mensagem,
            'http_code' => $http_code,
            'url_verificada' => isset($status_url) ? $status_url : '',
            'response_raw' => isset($response_data) ? substr($response_data, 0, 200) : ''
        ];
        
    } catch (Exception $e) {
        return [
            'status_sessao' => 'erro',
            'mensagem' => 'Erro: ' . $e->getMessage()
        ];
    }
}

/**
 * Função para fazer requisição de status
 */
function fazerRequisicaoStatus($url, $token, $tentativa = 'Bearer') {
    $auth_header = $tentativa === 'Bearer' ? 'Bearer ' . $token : $token;
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => [
            'Accept: */*',
            'Authorization: ' . $auth_header
        ],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $response_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        return [
            'response' => null,
            'http_code' => 0,
            'auth_header_used' => $auth_header,
            'error' => $curl_error
        ];
    }
    
    return [
        'response' => $response_data,
        'http_code' => $http_code,
        'auth_header_used' => $auth_header
    ];
}

$conn->close();
