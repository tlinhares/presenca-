<?php
header('Content-Type: text/html; charset=UTF-8');
ob_implicit_flush(true);
ob_start();

// Conexão
require_once __DIR__ . '/../../utils/env.php';
$conn = new mysqli(env('DB_HOST', 'localhost'), env('DB_USER', 'root'), env('DB_PASS', ''), env('DB_NAME', 'presenca_aom'));
if ($conn->connect_error) {
    echo "❌ Erro ao conectar no banco: " . $conn->connect_error; exit;
}
echo "✅ Conexão com o banco estabelecida.<br>"; flush(); sleep(1);

// Parâmetros
$id_usuario = isset($_POST['id_usuario']) ? intval($_POST['id_usuario']) : 0;
$data = $_POST['data'] ?? date('Y-m-d');
$ip_dispositivo = $_POST['ip_dispositivo'] ?? '';
$usuario_dispositivo = $_POST['usuario_dispositivo'] ?? 'admin';
$senha_dispositivo = $_POST['senha_dispositivo'] ?? 'admin';

if (!$id_usuario || !$ip_dispositivo) {
    echo "❌ Parâmetros ausentes. Envio cancelado."; exit;
}
echo "📦 Parâmetros recebidos: id_usuario={$id_usuario}, IP={$ip_dispositivo}<br>"; flush(); sleep(1);

// Enviar dados do usuário
$url_refeicao = "http://{$ip_dispositivo}/cgi-bin/AccessUser.cgi?action=insertMulti";
$payload_refeicao = json_encode([
    "UserList" => [[
        "UserID" => (string)$id_usuario,
        "Name" => "Usuario {$id_usuario}",
        "UserType" => "normal",
        "ValidEnable" => 0
    ]]
]);

echo "📤 Enviando dados do usuário para $url_refeicao...<br>"; flush();
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_refeicao);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_USERPWD, "{$usuario_dispositivo}:{$senha_dispositivo}");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_refeicao);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$res_refeicao = curl_exec($ch);
$erro_refeicao = curl_error($ch);
curl_close($ch);

if ($erro_refeicao) {
    echo "❌ Erro ao enviar dados do usuário: $erro_refeicao<br>"; exit;
}
echo "🟢 Resposta do envio do usuário: $res_refeicao<br>"; flush(); sleep(1);

// Buscar e enviar foto
$stmt = $conn->prepare("SELECT foto_base64 FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$stmt->bind_result($foto);
$stmt->fetch();
$stmt->close();
$conn->close();

if (!$foto) {
    echo "⚠️ Foto ausente para o usuário $id_usuario<br>"; exit;
}

echo "📸 Foto encontrada, preparando envio...<br>"; flush(); sleep(1);

$url_foto = "http://{$ip_dispositivo}/cgi-bin/AccessFace.cgi?action=updateMulti";
$payload_foto = json_encode([
    "FaceList" => [[
        "UserID" => (string)$id_usuario,
        "PhotoData" => [$foto]
    ]]
], JSON_PRETTY_PRINT);

file_put_contents(__DIR__ . '/../../logs/debug_face_data.json', $payload_foto);

echo "📤 Enviando foto para $url_foto...<br>"; flush();
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $url_foto);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_USERPWD, "{$usuario_dispositivo}:{$senha_dispositivo}");
curl_setopt($ch2, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
curl_setopt($ch2, CURLOPT_POSTFIELDS, $payload_foto);
curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$res_foto = curl_exec($ch2);
$erro_foto = curl_error($ch2);
curl_close($ch2);

file_put_contents(__DIR__ . '/../../logs/debug_face_response.txt', "Resposta: $res_foto\nErro: $erro_foto");

if ($erro_foto) {
    echo "❌ Erro ao enviar a foto: $erro_foto<br>"; exit;
}
echo "🟢 Resposta do envio da foto: $res_foto<br>"; flush();

echo "<br><strong>✅ Processo concluído com sucesso.</strong>"; flush();
?>
