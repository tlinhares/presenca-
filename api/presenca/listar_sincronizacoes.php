<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../conexao.php';

session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

$is_admin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
if (!$is_admin) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso não autorizado']);
    exit;
}

$data = $_GET['data'] ?? date('Y-m-d');

try {
    $sql = "
        SELECT 
            fs.id,
            fs.id_usuario,
            fs.origem,
            COALESCE(u.nome, d.nome, 'Erro ao buscar nome') AS nome_usuario,
            fs.status,
            fs.horario_sync,
            fs.detalhes
        FROM facial_sync fs
        LEFT JOIN usuarios u ON fs.origem = 'usuario' AND fs.id_usuario = u.id
        LEFT JOIN dependentes d ON fs.origem = 'dependente' AND fs.id_usuario = d.id
        WHERE fs.data = ?
        ORDER BY 
            CASE 
                WHEN fs.status = 'pendente' THEN 1
                WHEN fs.status = 'falha' THEN 2
                WHEN fs.status = 'sincronizado' THEN 3
                ELSE 4
            END,
            nome_usuario
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta: " . $conn->error);
    }

    $stmt->bind_param("s", $data);
    $stmt->execute();
    $stmt->bind_result($id, $id_usuario, $origem, $nome_usuario, $status, $horario_sync, $detalhes);

    $sincronizacoes = [];
    while ($stmt->fetch()) {
        $sincronizacoes[] = [
            'id' => $id,
            'id_usuario' => $id_usuario,
            'origem' => $origem,
            'nome_usuario' => $nome_usuario,
            'status' => $status,
            'horario_sync' => $horario_sync ? date('d/m/Y H:i:s', strtotime($horario_sync)) : '-',
            'detalhes' => $detalhes,
            'tipo_reserva' => $origem === 'dependente' ? 'Adicional' : 'Titular'
        ];
    }

    $stmt->close();

    echo json_encode([
        'status' => 'ok',
        'data' => $data,
        'data_formatada' => date('d/m/Y', strtotime($data)),
        'sincronizacoes' => $sincronizacoes,
        'total' => count($sincronizacoes)
    ]);
} catch (Exception $e) {
    $log_file = __DIR__ . '/../../logs/presenca_api_' . date('Y-m-d') . '.log';
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Erro: " . $e->getMessage() . PHP_EOL, FILE_APPEND);

    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao listar sincronizações: ' . $e->getMessage()
    ]);
}
?>
