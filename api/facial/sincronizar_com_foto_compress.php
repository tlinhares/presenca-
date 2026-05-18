<?php
header('Content-Type: application/json; charset=UTF-8');
error_reporting(0);
include_once(__DIR__ . '/../conexao.php');

// Função auxiliar para reduzir qualidade da imagem
function reduzirImagemBase64($base64, $qualidade = 60) {
    $imageData = base64_decode($base64);
    $tmpPath = sys_get_temp_dir() . '/img_temp_' . uniqid() . '.jpg';

    file_put_contents($tmpPath, $imageData);
    $image = @imagecreatefromstring(file_get_contents($tmpPath));

    if (!$image) {
        return false;
    }

    // Regrava com qualidade reduzida
    imagejpeg($image, $tmpPath, $qualidade);
    imagedestroy($image);

    $compressedData = file_get_contents($tmpPath);
    unlink($tmpPath);

    return base64_encode($compressedData);
}

// Parâmetros recebidos
$id_usuario = isset($_POST['id_usuario']) ? intval($_POST['id_usuario']) : 0;
$ip_dispositivo = isset($_POST['ip_dispositivo']) ? $_POST['ip_dispositivo'] : '';
$usuario_dispositivo = isset($_POST['usuario_dispositivo']) ? $_POST['usuario_dispositivo'] : 'admin';
$senha_dispositivo = isset($_POST['senha_dispositivo']) ? $_POST['senha_dispositivo'] : 'admin';

if ($id_usuario <= 0 || empty($ip_dispositivo)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Parâmetros obrigatórios ausentes.']);
    exit;
}

// Consulta a foto no banco
include_once(__DIR__ . '/../conexao.php');
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro na conexão com o banco de dados.']);
    exit;
}
$sql = "SELECT foto_base64 FROM usuarios WHERE id = ?";
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

// Tenta reduzir a imagem até estar abaixo de 150 KB
$tamanho_max = 150 * 1024;
$base64_final = $foto_base64;
$tentativas = [60, 40, 25];  // Qualidades a tentar

foreach ($tentativas as $qualidade) {
    $reduzida = reduzirImagemBase64($foto_base64, $qualidade);
    if ($reduzida && strlen(base64_decode($reduzida)) < $tamanho_max) {
        $base64_final = $reduzida;
        break;
    }
}

if (strlen(base64_decode($base64_final)) >= $tamanho_max) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Não foi possível reduzir a imagem para menos de 150 KB.']);
    exit;
}

// Envio ao equipamento
$url = "http://{$ip_dispositivo}/cgi-bin/AccessFace.cgi?action=updateMulti";
$data = [
    "FaceList" => [
        [
            "UserID" => (string)$id_usuario,
            "PhotoData" => [$base64_final]
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

// Verifica sucesso real
if ($error) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao enviar: ' . $error]);
} elseif (strpos($response, '"rval":0') !== false || strpos($response, '"rval":200') !== false) {
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Foto sincronizada com sucesso.']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Falha no retorno do dispositivo.', 'resposta' => $response]);
}
?>
