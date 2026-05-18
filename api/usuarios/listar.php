<?php
include_once(__DIR__ . '/../conexao.php');

header('Content-Type: application/json');

$sql = "SELECT id, nome, email, ativo, qrcode, id_valor, categoria, entidade_id, telefone, cpf, foto_base64, culto FROM usuarios ORDER BY ativo DESC, nome ASC";
$res = $conn->query($sql);

$usuarios = [];
while ($row = $res->fetch_assoc()) {
    $usuarios[] = [
        'id' => $row['id'],
        'nome' => $row['nome'],
        'email' => $row['email'],
        'status' => ($row['ativo'] == 1 ? 'Ativo' : 'Inativo'),
        'qrcode' => $row['qrcode'],
        'id_valor' => $row['id_valor'],
        'categoria' => $row['categoria'],
        'entidade_id' => $row['entidade_id'],
        'telefone' => $row['telefone'],
        'cpf' => $row['cpf'],
        'foto_base64' => $row['foto_base64'],
        'culto' => $row['culto']
    ];
}

echo json_encode([
    'status' => 'sucesso',
    'dados' => $usuarios
]);
?>
