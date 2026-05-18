<?php
// api/facial/dashboard.php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../conexao.php';
include_once(__DIR__ . '/../../auth/verifica_sessao.php');

// Log para debug
if (!file_exists(__DIR__ . '/../../logs')) {
    mkdir(__DIR__ . '/../../logs', 0755, true);
}
$log_file = __DIR__ . '/../../logs/dashboard_' . date('Y-m-d') . '.log';
$time = date('Y-m-d H:i:s');
file_put_contents($log_file, "[$time] Acesso ao dashboard facial" . PHP_EOL, FILE_APPEND);



$data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
file_put_contents($log_file, "[$time] Data solicitada: $data" . PHP_EOL, FILE_APPEND);

// Debug: Verificar diretamente as reservas
$query_debug = "SELECT COUNT(*) FROM reservas_almoco WHERE data = '$data'";
$result_debug = $conn->query($query_debug);
$row_debug = $result_debug->fetch_row();
$count_debug = $row_debug[0];
file_put_contents($log_file, "[$time] DEBUG: Total de reservas para esta data: $count_debug" . PHP_EOL, FILE_APPEND);

// Buscar estatísticas
$stmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM facial_sync WHERE data = ? AND status = 'sincronizado') as sincronizados,
        (SELECT COUNT(*) FROM facial_sync WHERE data = ? AND status = 'pendente') as pendentes,
        (SELECT COUNT(*) FROM facial_sync WHERE data = ? AND status = 'falha') as falhas,
        (SELECT COUNT(*) FROM checkin_facial WHERE DATE(data_hora) = ?) as checkins
");
$stmt->bind_param("ssss", $data, $data, $data, $data);
$stmt->execute();
$stmt->bind_result($sincronizados, $pendentes, $falhas, $checkins);
$stmt->fetch();

file_put_contents($log_file, "[$time] Estatísticas: sincronizados=$sincronizados, pendentes=$pendentes, falhas=$falhas, checkins=$checkins" . PHP_EOL, FILE_APPEND);

echo json_encode(array(
    'status' => 'ok',
    'sincronizados' => $sincronizados,
    'pendentes' => $pendentes,
    'falhas' => $falhas,
    'checkins' => $checkins
));
?>