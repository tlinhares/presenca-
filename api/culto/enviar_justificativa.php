<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

// Inicia sessão ANTES do middleware (compatível com web)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Middleware mobile: converte Bearer Token em sessão PHP se necessário
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

// Verifica autenticação (web ou mobile)
if (!isset($_SESSION['usuario_id'])) {
    // Tenta autenticar via token mobile
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Usuário não autenticado. Token inválido ou ausente.'
        ]);
        exit;
    }
}

try {
    require_once '../../api/conexao.php';
    
    if (!isset($conn) || !$conn) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }
    
    $conn->set_charset("utf8");
    
    $id_usuario = $_SESSION['usuario_id'];
    
    // Aceita tanto JSON (mobile) quanto form-data (web)
    $input_data = [];
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($content_type, 'application/json') !== false) {
        // Requisição JSON (mobile)
        $input = file_get_contents('php://input');
        $input_data = json_decode($input, true) ?? [];
    } else {
        // Requisição form-data (web)
        $input_data = $_POST;
    }
    
    $data_falta = $input_data['data_falta'] ?? '';
    $motivo = trim($input_data['motivo'] ?? '');
    $observacoes = trim($input_data['observacoes'] ?? '');
    
    // Validações
    if (empty($data_falta)) {
        throw new Exception('Data da falta é obrigatória');
    }
    
    if (empty($motivo)) {
        throw new Exception('Motivo da falta é obrigatório');
    }
    
    // Verificar se a data não é futura
    if ($data_falta > date('Y-m-d')) {
        throw new Exception('Não é possível justificar faltas de datas futuras');
    }
    
    // Verificar se já existe justificativa para esta data
    $stmt = $conn->prepare("SELECT id FROM justificativas_culto WHERE id_usuario = ? AND data_falta = ?");
    $stmt->bind_param("is", $id_usuario, $data_falta);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows > 0) {
        throw new Exception('Já existe uma justificativa para esta data');
    }
    
    // Verificar se realmente houve falta nesta data
    $stmt = $conn->prepare("SELECT status FROM presencas_culto WHERE id_usuario = ? AND data = ?");
    $stmt->bind_param("is", $id_usuario, $data_falta);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 0) {
        // Verificar se há presenças de outros usuários nesta data (para gerar falta automática)
        $stmt_check = $conn->prepare("SELECT COUNT(*) as total FROM presencas_culto WHERE data = ?");
        $stmt_check->bind_param("s", $data_falta);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();
        $check_data = $check_result->fetch_assoc();
        
        if ($check_data['total'] > 0) {
            // Criar registro de falta automaticamente
            $stmt_insert = $conn->prepare("INSERT INTO presencas_culto (id_usuario, data, horario_confirmacao, status, tipo_confirmacao) VALUES (?, ?, '00:00:00', 'falta', 'manual')");
            $stmt_insert->bind_param("is", $id_usuario, $data_falta);
            $stmt_insert->execute();
        } else {
            throw new Exception('Não há registro de presença para esta data');
        }
    } else {
        $presenca = $resultado->fetch_assoc();
        if ($presenca['status'] !== 'falta') {
            throw new Exception('Só é possível justificar faltas');
        }
    }
    
    // Inserir justificativa
    $stmt = $conn->prepare("
        INSERT INTO justificativas_culto (id_usuario, data_falta, motivo, observacoes, status) 
        VALUES (?, ?, ?, ?, 'pendente')
    ");
    $stmt->bind_param("isss", $id_usuario, $data_falta, $motivo, $observacoes);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Justificativa enviada com sucesso! Aguarde a análise do administrador.'
        ]);
    } else {
        throw new Exception('Erro ao salvar justificativa: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}

$conn->close();
?>
