<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/../conexao.php');
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

// Aceita JSON (mobile) ou form-data (web)
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($content_type, 'application/json') !== false) {
    $_POST = json_decode(file_get_contents('php://input'), true) ?: [];
}

if (!isset($_SESSION['usuario_id'])) {
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
        exit;
    }
}

// Verificar se é admin
$isAdmin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';

if (!$isAdmin) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado - apenas administradores podem fazer reservas de departamento']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

try {
    // Validar dados recebidos
    $entidade_id = $_POST['entidade_id'] ?? '';
    $quantidade = $_POST['quantidade'] ?? '';
    $evento_motivo = $_POST['evento_motivo'] ?? '';
    $data = $_POST['data'] ?? '';
    
    if (empty($entidade_id) || empty($quantidade) || empty($evento_motivo) || empty($data)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Todos os campos são obrigatórios']);
        exit;
    }
    
    // Validar e formatar data
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    if (!$data_obj) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Formato de data inválido. Use YYYY-MM-DD']);
        exit;
    }
    $data_formatada = $data_obj->format('Y-m-d');
    
    // Validar se a entidade existe
    $stmt_check = $conn->prepare("SELECT entidade_id, entidade_nome FROM entidade WHERE entidade_id = ?");
    $stmt_check->bind_param("i", $entidade_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Departamento não encontrado']);
        exit;
    }
    
    $entidade = $result_check->fetch_assoc();
    $stmt_check->close();
    
    // Verificar se já existe reserva para este departamento nesta data
    $stmt_duplicata = $conn->prepare("SELECT id FROM reservas_departamento WHERE entidade_id = ? AND data = ?");
    $stmt_duplicata->bind_param("is", $entidade_id, $data_formatada);
    $stmt_duplicata->execute();
    $result_duplicata = $stmt_duplicata->get_result();
    
    if ($result_duplicata->num_rows > 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe uma reserva para este departamento nesta data']);
        exit;
    }
    
    $stmt_duplicata->close();
    
    // Buscar configurações de valores
    $stmt_config = $conn->prepare("SELECT chave, valor FROM configuracoes WHERE chave IN ('valor_departamento', 'valor_departamento_fora_horario', 'horario_limite_agendamento')");
    $stmt_config->execute();
    $result_config = $stmt_config->get_result();
    
    $configuracoes = [];
    while ($row = $result_config->fetch_assoc()) {
        $configuracoes[$row['chave']] = $row['valor'];
    }
    $stmt_config->close();
    
    // Verificar se está dentro do horário limite
    $horario_limite = $configuracoes['horario_limite_agendamento'] ?? '12:00';
    $agora = new DateTime();
    $limite_hoje = new DateTime();
    $limite_hoje->setTime(explode(':', $horario_limite)[0], explode(':', $horario_limite)[1]);
    
    $dentro_horario = $agora <= $limite_hoje;
    
    // Definir valor unitário baseado no horário
    $valor_unitario = $dentro_horario ? 
        floatval($configuracoes['valor_departamento'] ?? 25.00) : 
        floatval($configuracoes['valor_departamento_fora_horario'] ?? 35.00);
    
    $valor_total = $valor_unitario * intval($quantidade);
    
    // Inserir reserva
    $stmt = $conn->prepare("
        INSERT INTO reservas_departamento 
        (entidade_id, data, quantidade, evento_motivo, valor_unitario, valor_total, criado_por, data_cadastro) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    if (!$stmt) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro na consulta: ' . $conn->error]);
        exit;
    }
    
    $usuario_id = $_SESSION['usuario_id'];
    $stmt->bind_param("isssssi", $entidade_id, $data_formatada, $quantidade, $evento_motivo, $valor_unitario, $valor_total, $usuario_id);
    
    if ($stmt->execute()) {
        $reserva_id = $conn->insert_id;
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Reserva de departamento realizada com sucesso',
            'reserva_id' => $reserva_id,
            'dados' => [
                'entidade_nome' => $entidade['entidade_nome'],
                'data' => $data,
                'quantidade' => intval($quantidade),
                'evento_motivo' => $evento_motivo,
                'valor_unitario' => $valor_unitario,
                'valor_total' => $valor_total,
                'dentro_horario' => $dentro_horario
            ]
        ]);
    } else {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao inserir reserva: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
