<?php
header('Content-Type: application/json; charset=UTF-8');
require_once '../conexao.php';
include_once(__DIR__ . '/../../auth/verifica_sessao.php');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não logado']);
    exit;
}

try {
    $hoje = date('Y-m-d');
    $inicio_mes = date('Y-m-01');
    
    // Reservas por dia (últimos 7 dias)
    $stmt = $conn->prepare("
        SELECT DATE(data) as dia, COUNT(*) as total
        FROM reservas_almoco 
        WHERE data >= DATE_SUB(?, INTERVAL 7 DAY)
        GROUP BY DATE(data)
        ORDER BY dia
    ");
    $stmt->bind_param("s", $hoje);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reservas_por_dia = [
        'labels' => [],
        'values' => []
    ];
    
    while ($row = $result->fetch_assoc()) {
        $reservas_por_dia['labels'][] = date('d/m', strtotime($row['dia']));
        $reservas_por_dia['values'][] = (int)$row['total'];
    }
    $stmt->close();
    
    // Tipos de reserva
    $stmt = $conn->prepare("
        SELECT tipo, COUNT(*) as total
        FROM reservas_almoco 
        WHERE data >= ?
        GROUP BY tipo
    ");
    $stmt->bind_param("s", $inicio_mes);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tipos_reserva = [
        'labels' => [],
        'values' => []
    ];
    
    while ($row = $result->fetch_assoc()) {
        $tipos_reserva['labels'][] = ucfirst($row['tipo']);
        $tipos_reserva['values'][] = (int)$row['total'];
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'sucesso',
        'dados' => [
            'reservas_por_dia' => $reservas_por_dia,
            'tipos_reserva' => $tipos_reserva
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar dados dos gráficos: ' . $e->getMessage()
    ]);
}
?>