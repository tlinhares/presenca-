<?php
header('Content-Type: application/json; charset=UTF-8');
require_once(__DIR__ . '/../conexao.php');

$sql = "SELECT entidade_id, entidade_nome FROM entidade ORDER BY entidade_nome";
$result = $conn->query($sql);

$entidade = [];

while ($row = $result->fetch_assoc()) {
    $entidade[] = [
        'entidade_id' => $row['entidade_id'],
        'entidade_nome' => $row['entidade_nome']
    ];
}

echo json_encode($entidade);
$conn->close();
?>
