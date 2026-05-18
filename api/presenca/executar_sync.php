<?php
header('Content-Type: application/json');

// INCLUIR CONFIGURAÇÃO DE TIMEZONE PRIMEIRO
include_once(__DIR__ . '/../../config/timezone.php');
include_once(__DIR__ . '/../../utils/config.php');
include_once(__DIR__ . '/../../api/conexao.php');

// Aceitar data via GET (web) ou via argumento de linha de comando (cron)
$data = $_GET['data'] ?? $argv[1] ?? date('Y-m-d');
$logFile = __DIR__ . "/../../logs/sincronizacao_facial_{$data}.log";

function logConsole($msg, $logFile) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

logConsole("Iniciando sincronizacao facial para data $data", $logFile);

// Buscar apenas dispositivos ativos do tipo restaurante
$sql_dispositivos = "SELECT id, nome, ip, porta, usuario, senha, tipo_dispositivo FROM dispositivos_faciais WHERE ativo = 1 AND tipo_dispositivo = 'restaurante'";
$result_dispositivos = $conn->query($sql_dispositivos);

if (!$result_dispositivos || $result_dispositivos->num_rows == 0) {
    logConsole("Nenhum dispositivo facial do tipo 'restaurante' ativo encontrado", $logFile);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Nenhum dispositivo facial do tipo restaurante ativo encontrado'
    ]);
    exit;
}

$dispositivos = [];
while ($row = $result_dispositivos->fetch_assoc()) {
    $dispositivos[] = $row;
}

logConsole("Encontrados " . count($dispositivos) . " dispositivos de restaurante ativos", $logFile);

$sql = "SELECT s.id, s.id_usuario, s.origem FROM facial_sync s WHERE s.data = ? AND s.status IN ('pendente', 'falha')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $data);
$stmt->execute();
$result = $stmt->get_result();

$sincronizados = 0;
$falhas = 0;

while ($row = $result->fetch_assoc()) {
    $id_sync = $row['id'];
    $id_usuario = $row['id_usuario'];
    $origem = $row['origem'];

    if ($origem === 'dependente') {
        $sql_dados = "SELECT nome, foto_base64 FROM dependentes WHERE id = ?";
    } else {
        $sql_dados = "SELECT nome, foto_base64 FROM usuarios WHERE id = ?";
    }

    $stmt_dados = $conn->prepare($sql_dados);
    $stmt_dados->bind_param("i", $id_usuario);
    $stmt_dados->execute();
    $stmt_dados->bind_result($nome, $foto_base64);
    $stmt_dados->fetch();
    $stmt_dados->close();

    if (empty($nome)) {
        logConsole("Erro: usuário/dependente #$id_usuario não encontrado.", $logFile);
        continue;
    }

    logConsole("Sincronizando usuario #{$id_usuario} - {$nome}", $logFile);

    // Sincronizar com todos os dispositivos
    $sucesso_geral = true;
    $detalhes_geral = [];
    
    foreach ($dispositivos as $dispositivo) {
        $ip = $dispositivo['ip'];
        $porta = $dispositivo['porta'];
        $user = $dispositivo['usuario'];
        $senha = $dispositivo['senha'];
        $nome_dispositivo = $dispositivo['nome'];
        
        logConsole("Sincronizando com dispositivo: {$nome_dispositivo} ({$ip}:{$porta})", $logFile);

        $url_insert = "http://{$ip}:{$porta}/cgi-bin/AccessUser.cgi?action=insertMulti";
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
        curl_setopt($ch1, CURLOPT_TIMEOUT, 10);
        $res_insert = curl_exec($ch1);
        $erro_insert = curl_error($ch1);
        curl_close($ch1);

        logConsole("Resposta insert {$nome_dispositivo}: $res_insert | Erro: $erro_insert", $logFile);

        $foto_base64 = trim($foto_base64);
        $sucesso_dispositivo = false;
        
        if (strpos($res_insert, 'OK') !== false) {
            $sucesso_dispositivo = true;
            
            // Enviar foto se disponível
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
                curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
                $res_face = curl_exec($ch2);
                $erro_face = curl_error($ch2);
                curl_close($ch2);

                logConsole("Resposta foto {$nome_dispositivo}: $res_face | Erro: $erro_face", $logFile);
                
                if (strpos($res_face, 'OK') !== false) {
                    $detalhes_geral[] = "{$nome_dispositivo}: Sincronizado com foto";
                } else {
                    $detalhes_geral[] = "{$nome_dispositivo}: Sincronizado sem foto";
                }
            } else {
                $detalhes_geral[] = "{$nome_dispositivo}: Sincronizado sem foto";
            }
        } else {
            $detalhes_geral[] = "{$nome_dispositivo}: Falha - {$erro_insert}";
            $sucesso_geral = false;
        }
    }

    // Atualizar status baseado no resultado geral
    if ($sucesso_geral) {
        $detalhes = implode('; ', $detalhes_geral);
        $update = $conn->prepare("UPDATE facial_sync SET status = 'sincronizado', horario_sync = NOW(), detalhes = ? WHERE id = ?");
        $update->bind_param("si", $detalhes, $id_sync);
        $update->execute();
        $update->close();

        logConsole("Usuario #{$id_usuario} marcado como sincronizado", $logFile);
        $sincronizados++;
    } else {
        $detalhes = implode('; ', $detalhes_geral);
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
