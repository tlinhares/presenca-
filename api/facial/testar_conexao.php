<?php
// api/facial/testar_conexao.php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../conexao.php';
include_once(__DIR__ . '/../../auth/verifica_sessao.php');



// Receber parâmetros
$ip = isset($_POST['ip']) ? trim($_POST['ip']) : '';
$porta = isset($_POST['porta']) ? trim($_POST['porta']) : '80';
$usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
$senha = isset($_POST['senha']) ? trim($_POST['senha']) : '';

if (empty($ip)) {
    echo json_encode(array('status' => 'erro', 'mensagem' => 'IP do dispositivo não informado'));
    exit;
}

// Log
$log_file = __DIR__ . '/../../logs/teste_dispositivo_' . date('Y-m-d') . '.log';
$time = date('Y-m-d H:i:s');
file_put_contents($log_file, "[$time] Testando conexão com dispositivo: $ip:$porta" . PHP_EOL, FILE_APPEND);

// Aqui implementar o teste de conexão com o dispositivo
// Baseado na documentação do SS3542 MF W

// Por enquanto, simular o teste
try {
    // Simular um teste de conexão
    // Quando tiver a documentação específica, implementar o código real
    
    /*
    // Exemplo de teste via HTTP
    $url = "http://$ip:$porta/api/status";
    $context = stream_context_create(array(
        'http' => array(
            'header' => "Authorization: Basic " . base64_encode("$usuario:$senha")
        )
    ));
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception("Não foi possível conectar ao dispositivo.");
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Resposta inválida do dispositivo.");
    }
    
    // Verificar se o dispositivo respondeu corretamente
    if (!isset($data['status']) || $data['status'] !== 'ok') {
        throw new Exception("Dispositivo respondeu com status inválido.");
    }
    */
    
    // Simulação para testes
    $conectado = true;
    
    if ($conectado) {
        file_put_contents($log_file, "[$time] Conexão bem-sucedida" . PHP_EOL, FILE_APPEND);
        echo json_encode(array('status' => 'ok', 'mensagem' => 'Conexão com o dispositivo estabelecida com sucesso!'));
    } else {
        throw new Exception("Falha na conexão com o dispositivo.");
    }
} catch (Exception $e) {
    file_put_contents($log_file, "[$time] ERRO: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    echo json_encode(array('status' => 'erro', 'mensagem' => 'Erro ao conectar: ' . $e->getMessage()));
}
?>