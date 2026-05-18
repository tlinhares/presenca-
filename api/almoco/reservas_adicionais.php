<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

include_once(__DIR__ . '/../conexao.php');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode([]);
    exit;
}

$id_usuario = $_SESSION['id_usuario'];

$stmt = $conn->prepare("
    SELECT ra.id, ra.data, ra.valor, d.nome as dependente_nome
    FROM reservas_adicionais ra
    INNER JOIN dependentes d ON ra.id_usuario = d.id
    WHERE d.id_usuario = ? AND ra.data = CURDATE()
    ORDER BY d.nome
");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();

$reservas = [];
while ($row = $result->fetch_assoc()) {
    $reservas[] = $row;
}

echo json_encode($reservas);

$stmt->close();
$conn->close();
?>
