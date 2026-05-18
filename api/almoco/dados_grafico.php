<?php
include_once(__DIR__ . '/../conexao.php');
header('Content-Type: application/json');

// Obtem os últimos 7 dias
$sql = "
    SELECT data, COUNT(*) AS total
    FROM reservas_almoco
    WHERE data >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY data
    UNION ALL
    SELECT data, SUM(quantidade) AS total
    FROM reservas_adicionais
    WHERE data >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY data
";

$res = $conn->query($sql);

$dados_por_data = [];
while ($row = $res->fetch_assoc()) {
    $data = $row['data'];
    $total = (int)$row['total'];
    if (!isset($dados_por_data[$data])) {
        $dados_por_data[$data] = 0;
    }
    $dados_por_data[$data] += $total;
}

// Ordena por data
ksort($dados_por_data);

// Monta arrays finais
$labels = [];
$valores = [];

foreach ($dados_por_data as $data => $total) {
    $labels[] = date('d/m', strtotime($data));
    $valores[] = $total;
}

echo json_encode([
    'labels' => $labels,
    'valores' => $valores
]);
?>
