<?php
/**
 * API para verificar se uma data está fechada (usado pelo script de notificação)
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../conexao.php';

$data = $_GET['data'] ?? date('Y-m-d');

try {
    $stmt = $conn->prepare("SELECT id, motivo, observacoes FROM dias_fechado WHERE data = ? AND ativo = 1 LIMIT 1");
    $stmt->bind_param("s", $data);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $esta_fechado = $result->num_rows > 0;
    $detalhes = null;
    
    if ($esta_fechado) {
        $row = $result->fetch_assoc();
        $detalhes = [
            'motivo' => $row['motivo'] ?? '',
            'observacoes' => $row['observacoes'] ?? ''
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'status' => 'ok',
        'data' => $data,
        'esta_fechado' => $esta_fechado,
        'detalhes' => $detalhes
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao verificar data: ' . $e->getMessage()
    ]);
}
?>

