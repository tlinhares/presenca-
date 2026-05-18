<?php
// api/auth/login.php
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once '../conexao.php';

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$senha = isset($_POST['senha']) ? trim($_POST['senha']) : '';

if ($email === '' || $senha === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Preencha todos os campos.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, nome, categoria, senha FROM usuarios WHERE email = ? AND ativo = 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($id, $nome, $categoria, $senha_hash);
    $stmt->fetch();

    if (password_verify($senha, $senha_hash)) {
        $_SESSION['usuario_id'] = $id;
        $_SESSION['usuario_nome'] = $nome;
        $_SESSION['usuario_categoria'] = $categoria;

        echo json_encode(['status' => 'ok', 'categoria' => $categoria]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Senha inválida.']);
    }
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não encontrado.']);
}

$stmt->close();
$conn->close();
?>
