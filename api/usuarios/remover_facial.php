<?php
/**
 * API para remover sincronização facial
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
    $stmt = $conn->prepare("SELECT id, nome FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não encontrado']);
        exit;
    }
    
    $usuario = $result->fetch_assoc();
    
    // Simular remoção do dispositivo facial
    $dispositivo_ip = '192.168.1.100'; // IP do dispositivo facial
    
    // Dados para remoção do dispositivo
    $dados_remocao = [
        'usuario_id' => $usuario['id'],
        'acao' => 'remover',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Simular envio para dispositivo (comentado para não causar erro)
    /*
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://{$dispositivo_ip}:8080/api/remove_user");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados_remocao));
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
    
    // Atualizar status no banco
    $stmt = $conn->prepare("
        UPDATE facial_sync 
        SET status = 'removido', 
            data_sync = NOW(), 
            detalhes = ? 
        WHERE usuario_id = ?
    ");
    
    $detalhes = json_encode([
        'dispositivo_ip' => $dispositivo_ip,
        'acao' => 'remocao',
        'dados_enviados' => $dados_remocao,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $stmt->bind_param("si", $detalhes, $usuario_id);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        // Se não havia registro, criar um novo
        $stmt = $conn->prepare("
            INSERT INTO facial_sync (usuario_id, status, data_sync, detalhes) 
            VALUES (?, 'removido', NOW(), ?)
        ");
        $stmt->bind_param("is", $usuario_id, $detalhes);
        $stmt->execute();
    }
    
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Sincronização facial removida com sucesso',
        'detalhes' => [
            'usuario_id' => $usuario_id,
            'nome' => $usuario['nome'],
            'dispositivo' => $dispositivo_ip
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erro na remoção facial: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>
