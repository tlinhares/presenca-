<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();


include_once(__DIR__ . '/../conexao.php');

// Verificar se a conexão foi estabelecida
if (!isset($conn) || !$conn) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro de conexão com o banco de dados']);
    exit;
}

// Verificar se a sessão está ativa
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

// Verificar se é admin


// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;
    
    if (!$id) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID da automação não fornecido']);
        exit;
    }
    
    // Buscar dados da automação
    $stmt = $conn->prepare("SELECT * FROM automacoes_relatorios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Automação não encontrada']);
        exit;
    }
    
    $automacao = $result->fetch_assoc();
    $stmt->close();
    
    // Testar apenas o envio de mensagem (sem arquivo por enquanto)
    $resultado = testarEnvioMensagem($automacao);
    
    if ($resultado['sucesso']) {
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => 'Teste executado com sucesso! ' . $resultado['mensagem']
        ]);
    } else {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro no teste: ' . $resultado['mensagem']
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

function testarEnvioMensagem($automacao) {
    try {
        $mensagem = $automacao['mensagem_personalizada'] ?: "🧪 Teste de automação - " . $automacao['nome'] . " - " . date('d/m/Y H:i:s');
        
        // Dados para envio via WhatsApp
        $dados = [
            'phone' => $automacao['numero_whatsapp'],
            'isGroup' => false,
            'isNewsletter' => false,
            'isLid' => false,
            'message' => $mensagem
        ];
        
        require_once __DIR__ . '/../../utils/env.php';
        $contexto = stream_context_create([
            'http' => [
                'timeout' => 30,
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: ' . env('WHATSAPP_API_TOKEN', '')
                ],
                'content' => json_encode($dados)
            ]
        ]);

        $url_whatsapp = env('WHATSAPP_API_URL_MESSAGE', 'http://10.144.128.34:21465/api/servidor/send-message');
        $resposta = file_get_contents($url_whatsapp, false, $contexto);
        
        // Debug detalhado
        error_log("=== TESTE WHATSAPP ===");
        error_log("URL: " . $url_whatsapp);
        error_log("Dados enviados: " . json_encode($dados));
        error_log("Resposta bruta: " . $resposta);
        
        if ($resposta === false) {
            return ['sucesso' => false, 'mensagem' => 'Erro na comunicação com API do WhatsApp'];
        }
        
        $resposta_json = json_decode($resposta, true);
        error_log("Resposta JSON: " . print_r($resposta_json, true));
        
        // A API retorna "status": "success" em vez de "success": true
        if (isset($resposta_json['status']) && $resposta_json['status'] === 'success') {
            return ['sucesso' => true, 'mensagem' => 'Mensagem de teste enviada com sucesso'];
        } else {
            $erro_detalhado = $resposta_json['message'] ?? $resposta_json['error'] ?? 'Erro desconhecido';
            return ['sucesso' => false, 'mensagem' => 'Erro no envio: ' . $erro_detalhado . ' | Resposta completa: ' . $resposta];
        }
        
    } catch (Exception $e) {
        return ['sucesso' => false, 'mensagem' => $e->getMessage()];
    }
}
?>
