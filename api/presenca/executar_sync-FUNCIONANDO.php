<?php
header('Content-Type: application/json');
include_once(__DIR__ . '/../../utils/config.php');

$conn = new mysqli("localhost", "root", "@Arcs2901", "presenca_aom");
if ($conn->connect_error) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro na conexão com o banco: ' . $conn->connect_error]);
    exit;
}

$data = $_GET['data'] ?? date('Y-m-d');
$logFile = __DIR__ . "/../../logs/sincronizacao_facial_{$data}.log";

function logConsole($msg, $logFile) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

logConsole("Iniciando sincronizacao facial para data $data", $logFile);

// Pegar configurações do dispositivo facial
$ip = get_config('ip_dispositivo_facial', '10.144.129.69');
$porta = get_config('porta_dispositivo_facial', '80');
$user = get_config('usuario_dispositivo_facial', 'admin');
$senha = get_config('senha_dispositivo_facial', 'admin');

$sql = "SELECT s.id, s.id_usuario, u.nome, u.foto_base64 
        FROM facial_sync s
        JOIN usuarios u ON s.id_usuario = u.id
        WHERE s.data = ? AND s.status = 'pendente'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $data);
$stmt->execute();
$result = $stmt->get_result();

$sincronizados = 0;
$falhas = 0;

while ($row = $result->fetch_assoc()) {
    $id_sync = $row['id'];
    $id_usuario = $row['id_usuario'];
    $nome = $row['nome'];
    $foto_base64 = trim($row['foto_base64']);

    logConsole("Sincronizando usuario #{$id_usuario} - {$nome}", $logFile);

    $url_insert = "http://{$ip}/cgi-bin/AccessUser.cgi?action=insertMulti";
    $payload_insert = json_encode([
        "UserList" => [[
            "UserID" => (string)$id_usuario,
            "UserName" => $nome,
            "UserType" => 0,
            "ValidEnable" => 1,
            "ValidFrom" => "2024-01-01 00:00:00",
            "ValidTo" => "2099-12-31 23:59:59"
        ]]
    ]);

    $ch1 = curl_init($url_insert);
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_POST, true);
    curl_setopt($ch1, CURLOPT_USERPWD, "$user:$senha");
    curl_setopt($ch1, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch1, CURLOPT_POSTFIELDS, $payload_insert);
    curl_setopt($ch1, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $res_insert = curl_exec($ch1);
    $erro_insert = curl_error($ch1);
    curl_close($ch1);

    logConsole("Resposta insert: $res_insert | Erro: $erro_insert", $logFile);

    // 2. Enviar foto
    $detalhes = '';
    if ($foto_base64) {
        $url_face = "http://{$ip}:{$porta}/cgi-bin/AccessFace.cgi?action=updateMulti";
        $payload_face = json_encode([
            "FaceList" => [[
                "UserID" => (string)$id_usuario,
                "PhotoData" => [$foto_base64]
            ]]
        ]);

        $ch2 = curl_init($url_face);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_USERPWD, "$user:$senha");
        curl_setopt($ch2, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, $payload_face);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $res_face = curl_exec($ch2);
        $erro_face = curl_error($ch2);
        curl_close($ch2);

        logConsole("Resposta foto: $res_face | Erro: $erro_face", $logFile);
    }

    // Registrar resultado
    if (strpos($res_insert, 'OK') !== false) {
        if (!empty($foto_base64)) {
            if (isset($res_face) && strpos($res_face, 'OK') !== false) {
                $detalhes = "Sincronizado com sucesso";
            } else {
                $detalhes = "Sincronizado, mas falhou ao enviar foto";
            }
        } else {
            $detalhes = "Sincronizado sem foto";
        }

        $conn->query("UPDATE facial_sync SET status = 'sincronizado', horario_sync = NOW(), detalhes = ? WHERE id = $id_sync");
        $update = $conn->prepare("UPDATE facial_sync SET status = 'sincronizado', horario_sync = NOW(), detalhes = ? WHERE id = ?");
        $update->bind_param("si", $detalhes, $id_sync);
        $update->execute();
        $update->close();

        logConsole("Usuario #{$id_usuario} marcado como sincronizado", $logFile);
        $sincronizados++;
    } else {
        $detalhes = "Erro ao sincronizar usuário";
        $update = $conn->prepare("UPDATE facial_sync SET status = 'falha', horario_sync = NOW(), detalhes = ? WHERE id = ?");
        $update->bind_param("si", $detalhes, $id_sync);
        $update->execute();
        $update->close();

        logConsole("Falha ao sincronizar usuario #{$id_usuario}", $logFile);
        $falhas++;
    }
}

$stmt->close();
$conn->close();

logConsole("Finalizado. Total: sincronizados=$sincronizados, falhas=$falhas", $logFile);

echo json_encode([
    'status' => 'ok',
    'mensagem' => "Sincronizados: $sincronizados | Falhas: $falhas"
]);
