<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

include_once(__DIR__ . '/../conexao.php');
include_once(__DIR__ . '/../../utils/config.php');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

// Construir query base
$sql = "SELECT r.data, r.horario_confirmacao, r.valor_refeicao, 
               CASE 
                   WHEN r.data < CURDATE() THEN 'passado'
                   WHEN r.data = CURDATE() THEN 'hoje'
                   ELSE 'futuro'
               END as periodo,
               CASE 
                   WHEN r.data < CURDATE() THEN 'Ausente'
                   WHEN r.data = CURDATE() THEN 'Presente'
                   ELSE 'Agendado'
               END as status
        FROM reservas_almoco r 
        WHERE r.id_usuario = ?";

$params = [$id_usuario];
$types = "i";

// Adicionar filtros de data
if (!empty($data_inicio)) {
    $sql .= " AND r.data >= ?";
    $params[] = $data_inicio;
    $types .= "s";
}

if (!empty($data_fim)) {
    $sql .= " AND r.data <= ?";
    $params[] = $data_fim;
    $types .= "s";
}

$sql .= " ORDER BY r.data DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao preparar query: ' . $conn->error]);
    exit;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$reservas = [];
$hoje = date('Y-m-d');
$hora_limite = get_config('hora_limite', '09:00');
$hora_atual = date('H:i');

while ($row = $result->fetch_assoc()) {
    // Determinar se pode cancelar
    $pode_cancelar = false;
    
    if ($row['data'] == $hoje) {
        // Para hoje, verificar se ainda está dentro do horário limite
        $pode_cancelar = ($hora_atual <= $hora_limite);
    } elseif ($row['data'] > $hoje) {
        // Para datas futuras, sempre pode cancelar
        $pode_cancelar = true;
    }
    
    // Formatar data para exibição
    $data_formatada = '';
    if ($row['data']) {
        $dt = DateTime::createFromFormat('Y-m-d', $row['data']);
        if ($dt) {
            $data_formatada = $dt->format('d/m/Y');
        }
    }
    
    $reservas[] = [
        'data' => $data_formatada,
        'data_original' => $row['data'],
        'horario_confirmacao' => $row['horario_confirmacao'],
        'valor_refeicao' => floatval($row['valor_refeicao']),
        'status' => $row['status'],
        'periodo' => $row['periodo'],
        'pode_cancelar' => $pode_cancelar
    ];
}

echo json_encode([
    'status' => 'ok',
    'reservas' => $reservas
]);

$stmt->close();
$conn->close();
?>
