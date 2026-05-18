<?php
// Exibir imagem do dependente com base em foto_base64 (já sem prefixo)
header('Content-Type: image/jpeg');

// Conexão
require_once __DIR__ . '/../../utils/env.php';
$conn = new mysqli(env('DB_HOST', 'localhost'), env('DB_USER', 'root'), env('DB_PASS', ''), env('DB_NAME', 'presenca_aom'));
if ($conn->connect_error) {
    http_response_code(500);
    die("Erro ao conectar: " . $conn->connect_error);
}

$id_usuario = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT foto_base64 FROM dependentes WHERE id = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$stmt->bind_result($foto_base64);
$stmt->fetch();
$stmt->close();
$conn->close();

if (!$foto_base64) {
    http_response_code(404);
    echo "Imagem não encontrada";
    exit;
}

// Remover prefixo se existir
$foto_base64 = preg_replace('/^data:image\/[^;]+;base64,/', '', $foto_base64);
echo base64_decode($foto_base64);
?>
