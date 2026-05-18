<?php
header('Content-Type: application/json');

include_once(__DIR__ . '/../../utils/config.php');
include_once(__DIR__ . '/../../api/conexao.php');

$data = $_GET['data'] ?? date('Y-m-d');
$logFile = __DIR__ . "/../../logs/sincronizacao_culto_{$data}.log";

function logConsole($msg, $logFile) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

logConsole("Iniciando sincronizacao facial para culto - data $data", $logFile);

// Buscar registros pendentes agrupados por dispositivo (apenas culto)
$sql = "SELECT s.id, s.id_usuario, s.id_dispositivo, s.origem, s.tentativas,
               d.nome as dispositivo_nome, d.ip, d.porta, d.usuario, d.senha
        FROM facial_sync_culto s
        JOIN dispositivos_faciais d ON s.id_dispositivo = d.id
        WHERE s.data = ? AND s.status IN ('pendente', 'falha') AND d.tipo_dispositivo = 'culto'
        ORDER BY s.id_dispositivo, s.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $data);
$stmt->execute();
$result = $stmt->get_result();

$sincronizados = 0;
$falhas = 0;
$dispositivo_atual = null;
$dispositivo_stats = [];

while ($row = $result->fetch_assoc()) {
    $id_sync = $row['id'];
    $id_usuario = $row['id_usuario'];
    $id_dispositivo = $row['id_dispositivo'];
    $origem = $row['origem'];
    $tentativas = $row['tentativas'];
    
    // Inicializar estatísticas do dispositivo se for um novo
    if ($dispositivo_atual !== $id_dispositivo) {
        $dispositivo_atual = $id_dispositivo;
        $dispositivo_stats[$id_dispositivo] = [
            'nome' => $row['dispositivo_nome'],
            'ip' => $row['ip'],
            'sincronizados' => 0,
            'falhas' => 0
        ];
        logConsole("Processando dispositivo de culto: {$row['dispositivo_nome']} ({$row['ip']})", $logFile);
    }
    
    // Buscar dados do usuário
    $sql_usuario = "SELECT nome, foto_base64 FROM usuarios WHERE id = ?";
    $stmt_usuario = $conn->prepare($sql_usuario);
    $stmt_usuario->bind_param("i", $id_usuario);
    $stmt_usuario->execute();
    $result_usuario = $stmt_usuario->get_result();
    $usuario = $result_usuario->fetch_assoc();
    
    if (!$usuario) {
        logConsole("ERRO: Usuário ID $id_usuario não encontrado", $logFile);
        $falhas++;
        $dispositivo_stats[$id_dispositivo]['falhas']++;
        continue;
    }
    
    $nome_usuario = $usuario['nome'];
    $foto_base64 = $usuario['foto_base64'];
    
    // Preparar dados para envio
    $dados = [
        'nome' => $nome_usuario,
        'foto' => $foto_base64
    ];
    
    // Fazer requisição para o dispositivo
    $url = "http://{$row['ip']}:{$row['porta']}/cgi-bin/face_recognition.cgi";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($dados))
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "{$row['usuario']}:{$row['senha']}");
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $resposta = curl_exec($ch);
    $erro = curl_error($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Atualizar tentativas
    $tentativas++;
    
    if ($codigo == 200) {
        // Sucesso
        $update = $conn->prepare("UPDATE facial_sync_culto SET status = 'sincronizado', tentativas = ?, ultima_tentativa = NOW(), detalhes = ? WHERE id = ?");
        $detalhes = "Sincronizado com sucesso. Código: $codigo";
        $update->bind_param("isi", $tentativas, $detalhes, $id_sync);
        $update->execute();
        
        $sincronizados++;
        $dispositivo_stats[$id_dispositivo]['sincronizados']++;
        logConsole("✓ Usuário '$nome_usuario' sincronizado com sucesso no dispositivo {$row['dispositivo_nome']}", $logFile);
    } else {
        // Falha
        $update = $conn->prepare("UPDATE facial_sync_culto SET status = 'falha', tentativas = ?, ultima_tentativa = NOW(), detalhes = ? WHERE id = ?");
        $detalhes = "Falha na sincronização. Código: $codigo, Erro: $erro";
        $update->bind_param("isi", $tentativas, $detalhes, $id_sync);
        $update->execute();
        
        $falhas++;
        $dispositivo_stats[$id_dispositivo]['falhas']++;
        logConsole("✗ Falha ao sincronizar usuário '$nome_usuario' no dispositivo {$row['dispositivo_nome']}. Código: $codigo, Erro: $erro", $logFile);
    }
}

// Log de resumo por dispositivo
foreach ($dispositivo_stats as $stats) {
    logConsole("Dispositivo {$stats['nome']}: {$stats['sincronizados']} sincronizados, {$stats['falhas']} falhas", $logFile);
}

// Log final
logConsole("Sincronização de culto concluída. Total: $sincronizados sincronizados, $falhas falhas", $logFile);

echo json_encode([
    'status' => 'ok',
    'data' => $data,
    'sincronizados' => $sincronizados,
    'falhas' => $falhas,
    'dispositivos' => $dispositivo_stats,
    'log_file' => $logFile
]);

$conn->close();
?>
