<?php
header('Content-Type: application/json; charset=UTF-8');
require_once(__DIR__ . '/../conexao.php');

$sql = "SELECT id, descricao, valor FROM grupo_valor ORDER BY descricao";
$result = $conn->query($sql);

$grupos = [];

while ($row = $result->fetch_assoc()) {
    $grupos[] = [
        'id' => $row['id'],
        'descricao' => $row['descricao'],
        'valor' => number_format($row['valor'], 2, ',', '.')
    ];
}

echo json_encode($grupos);
$conn->close();
?>
