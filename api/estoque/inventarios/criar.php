<?php
/**
 * API - Criar Inventário
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $departamento = isset($_POST['departamento']) ? intval($_POST['departamento']) : 0;
    $data = $_POST['data'] ?? date('Y-m-d H:i:s');
    $observacoes = trim($_POST['observacoes'] ?? '');
    $usuarioId = $_SESSION['usuario_id'] ?? 0;
    
    if ($departamento <= 0) {
        throw new Exception('Departamento é obrigatório');
    }
    
    if ($usuarioId <= 0) {
        throw new Exception('Usuário não identificado');
    }
    
    // Converter data para datetime se necessário
    if (strlen($data) === 10) {
        $data = $data . ' ' . date('H:i:s');
    }
    
    $sql = "INSERT INTO estoque_inventarios (id_departamento, data_inicio, observacoes, id_usuario_inicio, status) 
            VALUES (?, ?, ?, ?, 'em_andamento')";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . $conn->error);
    }
    
    $stmt->bind_param("issi", $departamento, $data, $observacoes, $usuarioId);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar query: ' . $stmt->error);
    }
    
    $id = $conn->insert_id;
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Inventário criado com sucesso',
        'id' => $id
    ]);

} catch (Exception $e) {
    error_log("Erro em inventarios/criar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();



