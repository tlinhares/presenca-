<?php
header('Content-Type: application/json; charset=UTF-8');
error_reporting(0);
include_once(__DIR__ . '/..../../conexao.php');

// Parâmetros recebidos
$id_usuario = isset($_POST['id_usuario']) ? intval($_POST['id_usuario']) : 0;
$ip_dispositivo = isset($_POST['ip_dispositivo']) ? $_POST['ip_dispositivo'] : '';
$usuario_dispositivo = isset($_POST['usuario_dispositivo']) ? $_POST['usuario_dispositivo'] : 'admin';
$senha_dispositivo = isset($_POST['senha_dispositivo']) ? $_POST['senha_dispositivo'] : 'admin';

if ($id_usuario <= 0 || empty($ip_dispositivo)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Parâmetros obrigatórios ausentes.']);
    exit;
}

// Consulta os dados do usuário
$sql = "SELECT nome, foto_base64 FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não encontrado.']);
    exit;
}

$usuario = $result->fetch_assoc();
$foto_base64 = $usuario['foto_base64'];

if (empty($foto_base64)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não possui foto.']);
    exit;
}

// Prepara os dados para envio ao dispositivo
$url = "http://{$ip_dispositivo}/cgi-bin/AccessFace.cgi?action=updateMulti";

$data = [
    "FaceList" => [
        [
            "UserID" => (string)$id_usuario,
            "PhotoData" => [$foto_base64]
        ]
    ]
];

$payload = json_encode($data);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_USERPWD, "{$usuario_dispositivo}:{$senha_dispositivo}");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao enviar para o dispositivo: ' . $error]);
} else {
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Foto sincronizada com sucesso.', 'resposta_dispositivo' => $response]);
}
?>