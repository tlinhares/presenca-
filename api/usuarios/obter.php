<?php
include_once(__DIR__ . '/../conexao.php');
header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido']);
    exit;
}

$stmt = $conn->prepare("SELECT id, nome, email, categoria, qrcode, foto_base64, id_valor, entidade_id FROM usuarios WHERE id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro na preparação da consulta']);
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não encontrado']);
    exit;
}

$stmt->bind_result($id, $nome, $email, $categoria, $qrcode, $foto_base64, $id_valor, $entidade_id);
$stmt->fetch();

echo json_encode([
    'status' => 'ok',
    'usuario' => [
        'id' => $id,
        'nome' => $nome,
        'email' => $email,
        'categoria_usuario' => $categoria,
        'foto_base64' => $foto_base64,
        'qrcode' => $qrcode,
        'id_valor' => $id_valor,
        'entidade_id' => $entidade_id
    ]
]);
?>
