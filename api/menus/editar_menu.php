<?php
/**
 * API para editar menu existente
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../conexao.php';

$menu_id = intval($_POST['menu_id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
$url = trim($_POST['url'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$descricao_card = trim($_POST['descricao_card'] ?? '');
$icone = trim($_POST['icone'] ?? 'bi-circle');
$categoria = trim($_POST['categoria'] ?? 'geral');
$classe_card = trim($_POST['classe_card'] ?? 'gerenciamento');
$ordem = intval($_POST['ordem'] ?? 0);
$acesso_padrao = intval($_POST['acesso_padrao'] ?? 0);
$requer_culto = intval($_POST['requer_culto'] ?? 0);
$requer_admin = intval($_POST['requer_admin'] ?? 0);
$ativo = intval($_POST['ativo'] ?? 1);

// Validações
if ($menu_id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID do menu inválido']);
    exit;
}

if (empty($nome) || empty($url)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Nome e URL são obrigatórios']);
    exit;
}

// Verificar se o menu existe
$stmt = $conn->prepare("SELECT id FROM menus WHERE id = ?");
$stmt->bind_param("i", $menu_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Menu não encontrado']);
    exit;
}
$stmt->close();

// Atualizar menu
$stmt = $conn->prepare("
    UPDATE menus SET 
        nome = ?, 
        descricao = ?, 
        descricao_card = ?,
        url = ?, 
        icone = ?, 
        categoria = ?, 
        classe_card = ?,
        ordem = ?, 
        acesso_padrao = ?, 
        requer_culto = ?, 
        requer_admin = ?,
        ativo = ?
    WHERE id = ?
");
$stmt->bind_param("sssssssiiiiii", 
    $nome, $descricao, $descricao_card, $url, $icone, $categoria, $classe_card,
    $ordem, $acesso_padrao, $requer_culto, $requer_admin, $ativo,
    $menu_id
);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'sucesso', 
        'mensagem' => "Menu '$nome' atualizado com sucesso!"
    ]);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar menu: ' . $conn->error]);
}

$stmt->close();

