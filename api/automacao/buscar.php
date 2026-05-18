<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();


include_once(__DIR__ . '/../conexao.php');



$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido.']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM automacoes_relatorios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Decodificar JSON dos dias da semana
        $row['dias_semana'] = json_decode($row['dias_semana'], true);
        
        echo json_encode([
            'status' => 'sucesso',
            'data' => $row
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Automação não encontrada.']);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar automação: ' . $e->getMessage()
    ]);
}
?>
