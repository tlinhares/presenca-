<?php
header('Content-Type: application/json');
include_once(__DIR__ . '/../conexao.php');

$dataHoje = date('Y-m-d');

$sql = "SELECT u.nome, r.reservou_conjuge, r.marmitex, r.horario_confirmacao
        FROM reservas_almoco r
        JOIN usuarios u ON u.id = r.id_usuario
        WHERE r.data = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $dataHoje);
$stmt->execute();

// Substituindo get_result() por bind_result()
$stmt->bind_result($nome, $conjuge, $marmitex, $horario);

$reservas = [];
$total_marmitex = 0;

while ($stmt->fetch()) {
    $reservas[] = [
        'nome' => $nome,
        'conjuge' => (bool)$conjuge,
        'marmitex' => (bool)$marmitex,
        'horario' => $horario
    ];
    if ($marmitex) {
        $total_marmitex++;
    }
}

$response = [
    'total_reservas' => count($reservas),
    'total_marmitex' => $total_marmitex,
    'reservas' => $reservas
];

echo json_encode($response);
?>
