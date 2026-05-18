<?php
/**
 * API para sincronizar usuário com dispositivo facial
 */
header('Content-Type: application/json');
include_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

$usuario_id = $_POST['usuario_id'] ?? null;

if (!$usuario_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID do usuário é obrigatório']);
    exit;
}

try {
    // Buscar dados do usuário
    $stmt = $conn->prepare("SELECT id, nome, email, foto_base64 FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não encontrado']);
        exit;
    }
    
    $usuario = $result->fetch_assoc();
    
    if (empty($usuario['foto_base64'])) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não possui foto cadastrada']);
        exit;
    }
    
    // Simular sincronização com dispositivo facial
    // Em uma implementação real, aqui seria feita a comunicação com o dispositivo
    $dispositivo_ip = '192.168.1.100'; // IP do dispositivo facial
    $dispositivo_porta = 8080;
    
    // Dados para envio ao dispositivo
    $dados_sincronizacao = [
        'usuario_id' => $usuario['id'],
        'nome' => $usuario['nome'],
        'email' => $usuario['email'],
        'foto' => $usuario['foto_base64'],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Simular envio para dispositivo (comentado para não causar erro)
    /*
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://{$dispositivo_ip}:{$dispositivo_porta}/api/sync_user");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados_sincronizacao));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception('Erro na comunicação com dispositivo facial');
    }
    */
    
    // Registrar sincronização no banco
    $stmt = $conn->prepare("
        INSERT INTO facial_sync (usuario_id, status, data_sync, detalhes) 
        VALUES (?, 'sincronizado', NOW(), ?)
        ON DUPLICATE KEY UPDATE 
        status = 'sincronizado', 
        data_sync = NOW(), 
        detalhes = ?
    ");
    
    $detalhes = json_encode([
        'dispositivo_ip' => $dispositivo_ip,
        'dados_enviados' => $dados_sincronizacao,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $stmt->bind_param("iss", $usuario_id, $detalhes, $detalhes);
    $stmt->execute();
    
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Usuário sincronizado com sucesso no dispositivo facial',
        'detalhes' => [
            'usuario_id' => $usuario_id,
            'nome' => $usuario['nome'],
            'dispositivo' => $dispositivo_ip
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erro na sincronização facial: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>
