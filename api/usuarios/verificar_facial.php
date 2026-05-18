<?php
/**
 * API para verificar status da sincronização facial
 */
header('Content-Type: application/json');
include_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

$usuario_id = $_GET['usuario_id'] ?? null;

if (!$usuario_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID do usuário é obrigatório']);
    exit;
}

try {
    // Buscar status da sincronização
    $stmt = $conn->prepare("
        SELECT fs.*, u.nome, u.email 
        FROM facial_sync fs 
        JOIN usuarios u ON fs.usuario_id = u.id 
        WHERE fs.usuario_id = ? 
        ORDER BY fs.data_sync DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'status' => 'sucesso',
            'sincronizado' => false,
            'mensagem' => 'Usuário nunca foi sincronizado',
            'detalhes' => 'Nenhum registro de sincronização encontrado'
        ]);
        exit;
    }
    
    $sync = $result->fetch_assoc();
    $detalhes = json_decode($sync['detalhes'], true);
    
    // Verificar se a sincronização ainda é válida (últimas 24 horas)
    $data_sync = new DateTime($sync['data_sync']);
    $agora = new DateTime();
    $diferenca = $agora->diff($data_sync);
    
    $sincronizado = $sync['status'] === 'sincronizado' && $diferenca->days < 1;
    
    $detalhes_texto = '';
    if ($detalhes) {
        $detalhes_texto = "Última sincronização: " . $sync['data_sync'];
        if (isset($detalhes['dispositivo_ip'])) {
            $detalhes_texto .= " | Dispositivo: " . $detalhes['dispositivo_ip'];
        }
    }
    
    echo json_encode([
        'status' => 'sucesso',
        'sincronizado' => $sincronizado,
        'mensagem' => $sincronizado ? 'Usuário está sincronizado' : 'Usuário não está sincronizado',
        'detalhes' => $detalhes_texto,
        'data_sync' => $sync['data_sync'],
        'status_sync' => $sync['status']
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao verificar status facial: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>
