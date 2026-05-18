<?php
// api/usuarios/cadastrar.php
header('Content-Type: application/json; charset=UTF-8');
session_start();

// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();

require_once '../conexao.php';
require_once '../utils/funcoes.php';

$nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$senha = isset($_POST['senha']) ? trim($_POST['senha']) : '';
categoria = isset($_POST['categoria']) ? $_POST['categoria'] : 'membro';

if ($nome === '' || $email === '' || $senha === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Preencha todos os campos obrigatórios.']);
    exit;
}

$senhaHash = gerarHashAntigo($senha);

$stmtVerifica = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmtVerifica->bind_param("s", $email);
$stmtVerifica->execute();
$stmtVerifica->store_result();

if ($stmtVerifica->num_rows > 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'E-mail já cadastrado.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, categoria) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $nome, $email, $senhaHash, $categoria);

if ($stmt->execute()) {
    echo json_encode(['status' => 'ok', 'mensagem' => 'Usuário cadastrado com sucesso.']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao cadastrar usuário.']);
}

$stmt->close();
$conn->close();