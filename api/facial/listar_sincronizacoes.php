<?php
// api/presenca/listar_sincronizacoes.php - Listar sincronizações de presença
header('Content-Type: application/json; charset=UTF-8');

// Incluir arquivo de conexão
require_once __DIR__ . '/..../../conexao.php';

// Verificar se está autenticado via sessão
session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

// Verificar permissão
$is_admin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
if (!$is_admin) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso não autorizado']);
    exit;
}

// Data para filtrar (padrão: hoje)
$data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');

try {
    // Buscar sincronizações
    $sql = "
        SELECT 
            fs.id,
            fs.id_usuario,
            u.nome as nome_usuario,
            fs.status,
            fs.horario_sync,
            fs.detalhes
        FROM facial_sync fs
        JOIN usuarios u ON fs.id_usuario = u.id
        WHERE fs.data = ?
        ORDER BY 
            CASE 
                WHEN fs.status = 'pendente' THEN 1
                WHEN fs.status = 'falha' THEN 2
                WHEN fs.status = 'sincronizado' THEN 3
                ELSE 4
            END,
            u.nome
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $data);
    $stmt->execute();
    
    $sincronizacoes = array();
    
    // Método alternativo para PHP 5.5 sem mysqlnd
    $stmt->bind_result($id, $id_usuario, $nome_usuario, $status, $horario_sync, $detalhes);
    
    while ($stmt->fetch()) {
        $row = array(
            'id' => $id,
            'id_usuario' => $id_usuario,
            'nome_usuario' => $nome_usuario,
            'status' => $status,
            'horario_sync' => $horario_sync,
            'detalhes' => $detalhes
        );
        
        // Formatar horário
        if (!empty($row['horario_sync'])) {
            $row['horario_sync'] = date('d/m/Y H:i:s', strtotime($row['horario_sync']));
        }
        
        $sincronizacoes[] = $row;
    }
    
    $stmt->close();
    
    // Retornar resposta
    echo json_encode([
        'status' => 'ok',
        'data' => $data,
        'data_formatada' => date('d/m/Y', strtotime($data)),
        'sincronizacoes' => $sincronizacoes,
        'total' => count($sincronizacoes)
    ]);
    
} catch (Exception $e) {
    // Log do erro
    $log_file = __DIR__ . '/../../logs/presenca_api_' . date('Y-m-d') . '.log';
    $time = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$time] Erro em listar_sincronizacoes.php: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    
    // Retornar erro
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao listar sincronizações: ' . $e->getMessage()
    ]);
}
?> 