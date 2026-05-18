<?php
header('Content-Type: application/json; charset=UTF-8');

include_once(__DIR__ . '/../conexao.php');

$usuario_id = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;

if (!$usuario_id) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'ID do usuário não fornecido'
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT id, nome, parentesco, nascimento, foto_base64 FROM dependentes WHERE id_usuario = ? AND ativo = 1 ORDER BY nome");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$stmt->bind_result($id, $nome, $parentesco, $nascimento, $foto_base64);

$dependentes = [];
while ($stmt->fetch()) {
    // Calcular idade
    $idade = '';
    if ($nascimento) {
        $nascimento_date = new DateTime($nascimento);
        $hoje = new DateTime();
        $idade = $nascimento_date->diff($hoje)->y;
    }
    
    $dependentes[] = [
        'id' => $id,
        'nome' => $nome,
        'parentesco' => $parentesco,
        'data_nascimento' => $nascimento,
        'idade' => $idade,
        'foto_base64' => $foto_base64
    ];
}

echo json_encode([
    'status' => 'sucesso',
    'dados' => $dependentes
]);

$stmt->close();
?>
