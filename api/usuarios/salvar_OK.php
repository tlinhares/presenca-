<?php
include_once(__DIR__ . '/../conexao.php');
header('Content-Type: application/json');

// Dados recebidos
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$senha = isset($_POST['senha']) ? trim($_POST['senha']) : '';
$categoria = isset($_POST['categoria']) ? trim($_POST['categoria']) : '';
$gerar_qrcode = isset($_POST['gerar_qrcode']) ? 1 : 0;

// Validação
if (empty($nome) || empty($categoria)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Preencha todos os campos obrigatórios.']);
    exit;
}

// Geração de QR Code (se solicitado)
$qrcode = null;
if ($gerar_qrcode) {
    $qrcode = uniqid('qr_');
}

// EDIÇÃO
if ($id > 0) {
    $sql = "UPDATE usuarios SET nome = ?, email = ?, categoria = ?";
    $params = [$nome, $email, $categoria];
    $types = "sss";

    if (!empty($senha)) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $sql .= ", senha = ?";
        $params[] = $senha_hash;
        $types .= "s";
    }

    if ($gerar_qrcode) {
        $sql .= ", qrcode = ?";
        $params[] = $qrcode;
        $types .= "s";
    }

    $sql .= " WHERE id = ?";
    $params[] = $id;
    $types .= "i";

    // Preparar e ligar parâmetros manualmente (PHP 5.5)
    $stmt = $conn->prepare($sql);

    // Construir argumentos por referência
    $bind_names[] = $types;
    foreach ($params as $key => $value) {
        $bind_names[] = &$params[$key];
    }

    // Chamada dinâmica
    call_user_func_array([$stmt, 'bind_param'], $bind_names);

    $ok = $stmt->execute();

    echo json_encode($ok
        ? ['status' => 'ok']
        : ['status' => 'erro', 'mensagem' => 'Erro ao atualizar usuário.']);
    exit;
}

// CADASTRO
if (empty($senha)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'A senha é obrigatória no cadastro.']);
    exit;
}

$senha_hash = password_hash($senha, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, categoria, qrcode, ativo) VALUES (?, ?, ?, ?, ?, 1)");
$stmt->bind_param("sssss", $nome, $email, $senha_hash, $categoria, $qrcode);

$ok = $stmt->execute();

echo json_encode($ok
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => 'Erro ao cadastrar usuário.']);
?>
