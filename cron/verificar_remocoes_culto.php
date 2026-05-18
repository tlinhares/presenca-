<?php
// verificar_remocoes_culto.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../api/conexao.php';
include_once(__DIR__ . '/../utils/config.php');

// Caminho do log
$dataLog = date('Y-m-d');
$log_file = __DIR__ . "/../logs/remocoes_culto_$dataLog.log";

require_once __DIR__ . '/../utils/logger.php';

function registrar_log($mensagem) {
    Logger::emergencial('verificar_remocoes_culto', $mensagem);
}

$hoje = date('Y-m-d');

registrar_log("------------------------Processo de Remoção de Culto Iniciado------------------------");

// Busca todos os registros sincronizados agrupados por dispositivo (apenas culto)
$query = "SELECT fs.id, fs.id_usuario, fs.id_dispositivo, fs.origem, fs.status,
                 df.nome as dispositivo_nome, df.ip, df.porta, df.usuario, df.senha
          FROM facial_sync_culto fs
          JOIN dispositivos_faciais df ON fs.id_dispositivo = df.id
          WHERE fs.status = 'sincronizado' AND fs.data = ? AND df.tipo_dispositivo = 'culto'
          ORDER BY fs.id_dispositivo";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $hoje);
$stmt->execute();
$result = $stmt->get_result();

$removidos = [];
$erros = [];
$dispositivo_atual = null;

while ($row = $result->fetch_assoc()) {
    $id_sync = $row['id'];
    $id_usuario = $row['id_usuario'];
    $id_dispositivo = $row['id_dispositivo'];
    $origem = $row['origem'];
    
    // Log quando mudar de dispositivo
    if ($dispositivo_atual !== $id_dispositivo) {
        $dispositivo_atual = $id_dispositivo;
        registrar_log("Processando dispositivo de culto: {$row['dispositivo_nome']} ({$row['ip']})");
    }
    
    // Buscar dados do usuário
    $sql_usuario = "SELECT nome FROM usuarios WHERE id = ?";
    $stmt_usuario = $conn->prepare($sql_usuario);
    $stmt_usuario->bind_param("i", $id_usuario);
    $stmt_usuario->execute();
    $result_usuario = $stmt_usuario->get_result();
    $usuario = $result_usuario->fetch_assoc();
    
    if (!$usuario) {
        registrar_log("ERRO: Usuário ID $id_usuario não encontrado");
        $erros[] = "Usuário ID $id_usuario não encontrado";
        continue;
    }
    
    $nome_usuario = $usuario['nome'];
    
    // Preparar dados para remoção
    $dados = [
        'acao' => 'remover',
        'nome' => $nome_usuario
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
    
    if ($codigo == 200) {
        // Sucesso na remoção
        $update = $conn->prepare("UPDATE facial_sync_culto SET status = 'removido', detalhes = ? WHERE id = ?");
        $detalhes = "Removido com sucesso. Código: $codigo";
        $update->bind_param("si", $detalhes, $id_sync);
        $update->execute();
        
        $removidos[] = $nome_usuario;
        registrar_log("✓ Usuário '$nome_usuario' removido com sucesso do dispositivo {$row['dispositivo_nome']}");
    } else {
        // Falha na remoção
        $update = $conn->prepare("UPDATE facial_sync_culto SET status = 'erro_remocao', detalhes = ? WHERE id = ?");
        $detalhes = "Erro na remoção. Código: $codigo, Erro: $erro";
        $update->bind_param("si", $detalhes, $id_sync);
        $update->execute();
        
        $erros[] = "Falha ao remover '$nome_usuario' do dispositivo {$row['dispositivo_nome']}. Código: $codigo";
        registrar_log("✗ Erro ao remover usuário '$nome_usuario' do dispositivo {$row['dispositivo_nome']}. Código: $codigo, Erro: $erro");
    }
}

// Log final
registrar_log("Processo de remoção de culto concluído. Removidos: " . count($removidos) . ", Erros: " . count($erros));
registrar_log("------------------------Processo de Remoção de Culto Finalizado------------------------");

// Retornar resultado
echo json_encode([
    'status' => 'ok',
    'data' => $hoje,
    'removidos' => count($removidos),
    'erros' => count($erros),
    'usuarios_removidos' => $removidos,
    'erros_detalhes' => $erros,
    'log_file' => $log_file
]);

$conn->close();
?>
