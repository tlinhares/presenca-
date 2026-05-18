<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

include_once(__DIR__ . '/../conexao.php');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

try {
    // Buscar configurações de horário e valores
    $stmt = $conn->prepare("SELECT chave, valor FROM configuracoes WHERE chave IN ('horario_limite_agendamento', 'valor_departamento', 'valor_departamento_fora_horario')");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $configuracoes = [];
    while ($row = $result->fetch_assoc()) {
        $configuracoes[$row['chave']] = $row['valor'];
    }
    $stmt->close();
    
    $horario_limite = $configuracoes['horario_limite_agendamento'] ?? '12:00';
    $valor_dentro = floatval($configuracoes['valor_departamento'] ?? 25.00);
    $valor_fora = floatval($configuracoes['valor_departamento_fora_horario'] ?? 35.00);
    
    // Verificar se está dentro do horário limite
    $agora = new DateTime();
    $limite_hoje = new DateTime();
    $limite_hoje->setTime(explode(':', $horario_limite)[0], explode(':', $horario_limite)[1]);
    
    $dentro_horario = $agora <= $limite_hoje;
    
    echo json_encode([
        'status' => 'ok',
        'dentro_horario' => $dentro_horario,
        'horario_limite' => $horario_limite,
        'horario_atual' => $agora->format('H:i'),
        'valor_unitario' => $dentro_horario ? $valor_dentro : $valor_fora,
        'valor_total' => 0 // Será calculado no frontend baseado na quantidade
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}

$conn->close();
?>
