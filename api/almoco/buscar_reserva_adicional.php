<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

include_once(__DIR__ . '/../conexao.php');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado.']);
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$reserva_id = intval($_GET['id'] ?? 0);

if ($reserva_id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID da reserva inválido.']);
    exit;
}

try {
    // Buscar dados da reserva adicional
    $stmt = $conn->prepare("SELECT ra.id, ra.data, ra.quantidade, ra.tipo, ra.id_dependente, 
                           ra.valor_refeicao, ra.valor_marmitex, ra.detalhe, ra.fora_do_horario,
                           d.nome as dependente_nome
                           FROM reservas_adicionais ra
                           INNER JOIN dependentes d ON ra.id_dependente = d.id
                           WHERE ra.id = ? AND d.id_usuario = ?");
    $stmt->bind_param("ii", $reserva_id, $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'status' => 'ok',
            'data' => $row
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Reserva não encontrada.']);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar reserva: ' . $e->getMessage()
    ]);
}
?>
