<?php
// Configurar para não exibir erros na saída
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Headers obrigatórios
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responder a requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Função para retornar erro JSON
function retornarErro($mensagem, $codigo = 400) {
    http_response_code($codigo);
    echo json_encode([
        'success' => false,
        'error' => $mensagem,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Envolver tudo em try-catch global
try {
    require_once __DIR__ . '/../conexao.php';
    require_once __DIR__ . '/../../config/timezone.php';

// Função para log
function logLeituraFacial($mensagem) {
    $logFile = __DIR__ . '/../../logs/leitura_facial_culto_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $mensagem\n", FILE_APPEND);
}

// Função para obter configurações de culto
function getConfigCulto($chave, $valorPadrao = null) {
    global $conn;
    $stmt = $conn->prepare("SELECT valor FROM configuracoes_culto WHERE chave = ?");
    $stmt->bind_param("s", $chave);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['valor'];
    }
    return $valorPadrao;
}

try {
    // Verificar método HTTP
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Para testes - retornar status da API
        echo json_encode([
            'success' => true,
            'message' => 'API de leitura facial funcionando',
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => 'GET (teste)'
        ]);
        exit;
    }
    
    if ($method !== 'POST') {
        throw new Exception("Método não permitido. Use POST. Recebido: $method");
    }

    // Obter dados da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        // Tentar obter dados do POST tradicional
        $input = $_POST;
    }
    
    // Log da requisição recebida
    logLeituraFacial("Método recebido: $method, Dados: " . json_encode($input));

    // Validar dados obrigatórios
    $required_fields = ['nome_usuario', 'ip_dispositivo', 'timestamp'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Campo obrigatório ausente: $field");
        }
    }

    $nome_usuario = trim($input['nome_usuario']);
    $ip_dispositivo = trim($input['ip_dispositivo']);
    $timestamp = $input['timestamp'];
    $foto_base64 = $input['foto_base64'] ?? null;

    logLeituraFacial("Recebida leitura facial: $nome_usuario do dispositivo $ip_dispositivo");

    // Validar e obter dados do dispositivo
    $stmt = $conn->prepare("
        SELECT id, nome, tipo_dispositivo, ativo 
        FROM dispositivos_faciais 
        WHERE ip = ? AND tipo_dispositivo = 'culto' AND ativo = 1
    ");
    $stmt->bind_param("s", $ip_dispositivo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$dispositivo = $result->fetch_assoc()) {
        throw new Exception("Dispositivo não encontrado ou inativo: $ip_dispositivo");
    }

    // Buscar usuário pelo nome
    $stmt = $conn->prepare("
        SELECT id, nome, email, ativo 
        FROM usuarios 
        WHERE nome = ? AND ativo = 1
    ");
    $stmt->bind_param("s", $nome_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$usuario = $result->fetch_assoc()) {
        throw new Exception("Usuário não encontrado: $nome_usuario");
    }

    // Obter data e horário atual
    $data_atual = date('Y-m-d');
    $horario_atual = date('H:i:s');
    
    // Obter configurações de horário do culto
    $horario_inicio = getConfigCulto('horario_inicio', '07:30:00');
    $horario_fim = getConfigCulto('horario_fim', '08:30:00');
    $tolerancia_atraso = getConfigCulto('tolerancia_atraso', '15'); // minutos

    // Calcular horário limite para atraso
    $horario_limite = date('H:i:s', strtotime("$horario_inicio +$tolerancia_atraso minutes"));

    // Determinar status da presença
    $status_presenca = 'presente';
    $tipo_confirmacao = 'facial';
    
    if ($horario_atual > $horario_limite && $horario_atual <= $horario_fim) {
        $status_presenca = 'atrasado';
        $tipo_confirmacao = 'atrasado';
    } elseif ($horario_atual > $horario_fim) {
        throw new Exception("Horário do culto já encerrado. Horário atual: $horario_atual, Fim: $horario_fim");
    }

    // Verificar se já existe presença para hoje
    $stmt = $conn->prepare("
        SELECT id, status, horario_confirmacao 
        FROM presencas_culto 
        WHERE id_usuario = ? AND data = ?
    ");
    $stmt->bind_param("is", $usuario['id'], $data_atual);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($presenca_existente = $result->fetch_assoc()) {
        // Atualizar presença existente
        $stmt = $conn->prepare("
            UPDATE presencas_culto 
            SET status = ?, horario_confirmacao = ?, tipo_confirmacao = ?, observacoes = ?
            WHERE id = ?
        ");
        $observacoes = "Atualizado via reconhecimento facial - Dispositivo: {$dispositivo['nome']}";
        $stmt->bind_param("ssssi", $status_presenca, $horario_atual, $tipo_confirmacao, $observacoes, $presenca_existente['id']);
        $stmt->execute();
        
        $acao = 'atualizada';
        logLeituraFacial("Presença atualizada para {$usuario['nome']}: $status_presenca às $horario_atual");
    } else {
        // Inserir nova presença
        $stmt = $conn->prepare("
            INSERT INTO presencas_culto 
            (id_usuario, data, horario_confirmacao, tipo_confirmacao, status, observacoes) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $observacoes = "Confirmado via reconhecimento facial - Dispositivo: {$dispositivo['nome']}";
        $stmt->bind_param("isssss", $usuario['id'], $data_atual, $horario_atual, $tipo_confirmacao, $status_presenca, $observacoes);
        $stmt->execute();
        
        $acao = 'registrada';
        logLeituraFacial("Nova presença registrada para {$usuario['nome']}: $status_presenca às $horario_atual");
    }

    // Atualizar status na tabela facial_sync_culto
    $stmt = $conn->prepare("
        UPDATE facial_sync_culto 
        SET status = 'sincronizado', ultima_tentativa = NOW(), detalhes = ?
        WHERE id_usuario = ? AND id_dispositivo = ? AND data = ?
    ");
    $detalhes = "Presença confirmada via reconhecimento facial: $status_presenca às $horario_atual";
    $stmt->bind_param("siis", $detalhes, $usuario['id'], $dispositivo['id'], $data_atual);
    $stmt->execute();

    // Resposta de sucesso
    echo json_encode([
        'status' => 'success',
        'message' => "Presença $acao com sucesso",
        'data' => [
            'usuario' => $usuario['nome'],
            'data' => $data_atual,
            'horario' => $horario_atual,
            'status' => $status_presenca,
            'dispositivo' => $dispositivo['nome'],
            'acao' => $acao
        ],
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $errorDetails = [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ];
    
    logLeituraFacial("ERRO: $errorMessage | Detalhes: " . json_encode($errorDetails));
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $errorMessage,
        'details' => $errorDetails,
        'timestamp' => time()
    ]);
}

} catch (Throwable $e) {
    // Catch global para qualquer erro não capturado
    retornarErro("Erro interno: " . $e->getMessage(), 500);
}

$conn->close();
?>
