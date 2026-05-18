<?php
header('Content-Type: text/plain');

// Conexão com o banco
require_once __DIR__ . '/../../utils/env.php';
$conn = new mysqli(env('DB_HOST', 'localhost'), env('DB_USER', 'root'), env('DB_PASS', ''), env('DB_NAME', 'presenca_aom'));
if ($conn->connect_error) {
    die("Erro ao conectar: " . $conn->connect_error);
}

// Buscar a base64 pura do usuário
$id_usuario = isset($_GET['id']) ? intval($_GET['id']) : 1;

$stmt = $conn->prepare("SELECT foto_base64 FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$stmt->bind_result($foto_base64);
$stmt->fetch();
$stmt->close();
$conn->close();

if (empty($foto_base64)) {
    die("⚠️ Nenhuma foto encontrada para o usuário ID $id_usuario.");
}

// Caminho temporário
$tempFile = tempnam('../../logs', 'foto_');
file_put_contents($tempFile, base64_decode($foto_base64));

// Verifica se o arquivo foi salvo corretamente
if (file_exists($tempFile)) {
    echo "✅ Foto salva com sucesso: $tempFile\n";
    echo "Tamanho do arquivo gerado: " . filesize($tempFile) . " bytes\n";
} else {
    echo "❌ Erro ao salvar a foto\n";
}
?>
