<?php
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

if ($_SESSION['usuario_categoria'] !== 'admin') {
    header('Location: ../reservas/almoco.php');
    exit;
}

$arquivo = $_GET['arquivo'] ?? '';

if (empty($arquivo)) {
    die('Arquivo não especificado');
}

// Sanitizar nome do arquivo
$arquivo = basename($arquivo);

require_once __DIR__ . '/../utils/env.php';
$backup_dir = rtrim(env('BACKUP_BKP_PATH', __DIR__ . '/bkp'), '/') . '/';
$arquivo_path = $backup_dir . $arquivo;

// Verificar se o arquivo existe e está dentro do diretório de backup
if (!file_exists($arquivo_path)) {
    die('Arquivo não encontrado');
}

// Verificar segurança (arquivo deve estar dentro do diretório de backup)
$real_backup_dir = realpath($backup_dir);
$real_arquivo = realpath($arquivo_path);

if (!$real_backup_dir || !$real_arquivo || strpos($real_arquivo, $real_backup_dir) !== 0) {
    die('Acesso negado');
}

// Determinar tipo MIME
$extensao = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
if ($extensao === 'sql') {
    header('Content-Type: application/sql');
} elseif ($extensao === 'zip') {
    header('Content-Type: application/zip');
} else {
    header('Content-Type: application/octet-stream');
}

header('Content-Disposition: attachment; filename="' . $arquivo . '"');
header('Content-Length: ' . filesize($arquivo_path));

readfile($arquivo_path);
exit;

