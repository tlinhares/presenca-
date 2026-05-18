<?php
header('Content-Type: application/json; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

if ($_SESSION['usuario_categoria'] !== 'admin') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado']);
    exit;
}

$arquivo = $_POST['arquivo'] ?? '';

if (empty($arquivo)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Arquivo não especificado']);
    exit;
}

// Sanitizar nome do arquivo
$arquivo = basename($arquivo);

$backup_dir = __DIR__ . '/bkp/';
$arquivo_path = $backup_dir . $arquivo;

// Verificar se o arquivo existe e está dentro do diretório de backup
if (!file_exists($arquivo_path)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Arquivo não encontrado']);
    exit;
}

// Verificar segurança (arquivo deve estar dentro do diretório de backup)
$real_backup_dir = realpath($backup_dir);
$real_arquivo = realpath($arquivo_path);

if (strpos($real_arquivo, $real_backup_dir) !== 0) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado']);
    exit;
}

// Excluir arquivo
if (unlink($arquivo_path)) {
    echo json_encode(['sucesso' => true, 'mensagem' => 'Backup excluído com sucesso']);
} else {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao excluir arquivo']);
}

