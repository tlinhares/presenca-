<?php
/**
 * API para criar novo menu
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();




require_once __DIR__ . '/../conexao.php';

$codigo = trim($_POST['codigo'] ?? '');
$nome = trim($_POST['nome'] ?? '');
$url = trim($_POST['url'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$icone = trim($_POST['icone'] ?? 'bi-circle');
$categoria = trim($_POST['categoria'] ?? 'geral');
$ordem = intval($_POST['ordem'] ?? 0);
$acesso_padrao = intval($_POST['acesso_padrao'] ?? 1);
$requer_culto = intval($_POST['requer_culto'] ?? 0);
$requer_admin = intval($_POST['requer_admin'] ?? 0);

// Validações
if (empty($codigo) || empty($nome) || empty($url)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Código, nome e URL são obrigatórios']);
    exit;
}

// Formatar código (minúsculas, sem espaços)
$codigo = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $codigo));

// Verificar se código já existe
$stmt = $conn->prepare("SELECT id FROM menus WHERE codigo = ?");
$stmt->bind_param("s", $codigo);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe um menu com este código']);
    exit;
}
$stmt->close();

// Inserir menu
$stmt = $conn->prepare("
    INSERT INTO menus (codigo, nome, descricao, url, icone, categoria, ordem, acesso_padrao, requer_culto, requer_admin) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("ssssssiiii", $codigo, $nome, $descricao, $url, $icone, $categoria, $ordem, $acesso_padrao, $requer_culto, $requer_admin);

if ($stmt->execute()) {
    $menu_id = $conn->insert_id;
    echo json_encode([
        'status' => 'sucesso', 
        'id' => $menu_id,
        'mensagem' => "Menu '$nome' criado com sucesso!"
    ]);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao criar menu: ' . $conn->error]);
}

$stmt->close();

