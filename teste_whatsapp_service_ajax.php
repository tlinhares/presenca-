<?php
/**
 * Endpoint AJAX para teste do WhatsAppService
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/core/services/WhatsAppService.php';

$input = json_decode(file_get_contents('php://input'), true);
$acao = $input['acao'] ?? '';

if ($acao === 'enviar_mensagem') {
    $telefone = $input['telefone'] ?? '';
    $mensagem = $input['mensagem'] ?? '';
    
    if (empty($telefone) || empty($mensagem)) {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Telefone e mensagem são obrigatórios'
        ]);
        exit;
    }
    
    $resultado = WhatsAppService::enviarMensagem($telefone, $mensagem, [
        'log_callback' => function($msg) {
            error_log("WhatsAppService Test: $msg");
        },
        'retornar_detalhes' => true
    ]);
    
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Ação não reconhecida'
    ]);
}

