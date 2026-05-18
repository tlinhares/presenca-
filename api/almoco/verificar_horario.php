<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once(__DIR__ . '/../conexao.php');
include_once(__DIR__ . '/../../utils/config.php');

// Inicia sessão ANTES do middleware (compatível com web)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Middleware mobile: converte Bearer Token em sessão PHP se necessário
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

// Verifica autenticação (web ou mobile)
if (!isset($_SESSION['usuario_id'])) {
    // Log de debug
    error_log("verificar_horario.php - Sessão não encontrada, tentando autenticação mobile");
    error_log("verificar_horario.php - Headers recebidos: " . json_encode(getallheaders()));
    error_log("verificar_horario.php - _SERVER['HTTP_AUTHORIZATION']: " . (isset($_SERVER['HTTP_AUTHORIZATION']) ? 'EXISTE' : 'NÃO EXISTE'));
    
    // Tenta autenticar via token mobile
    $authResult = MobileAuthMiddleware::handle();
    error_log("verificar_horario.php - Resultado do handle(): " . ($authResult ? 'SUCESSO' : 'FALHOU'));
    
    if (!$authResult) {
        error_log("verificar_horario.php - Autenticação falhou, retornando erro");
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não logado']);
        exit;
    }
    
    error_log("verificar_horario.php - Autenticação bem-sucedida, usuario_id: " . $_SESSION['usuario_id']);
}

$data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'presencial';

try {
    // Verificar se a data é válida
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Data inválida']);
        exit;
    }

    // Verificar se a data não é no passado (exceto hoje)
    $hoje = date('Y-m-d');
    if ($data < $hoje) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Não é possível agendar para datas passadas']);
        exit;
    }

    // Verificar se a data não é muito no futuro (máximo 30 dias)
    $limite_futuro = date('Y-m-d', strtotime('+30 days'));
    if ($data > $limite_futuro) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Não é possível agendar com mais de 30 dias de antecedência']);
        exit;
    }

    // Verificar se já existe reserva para esta data
    $stmt = $conn->prepare("SELECT COUNT(*) FROM reservas_almoco WHERE id_usuario = ? AND data = ?");
    $stmt->bind_param("is", $_SESSION['usuario_id'], $data);
    $stmt->execute();
    $ja_reservado = $stmt->get_result()->fetch_row()[0] > 0;
    $stmt->close();

    if ($ja_reservado) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Você já possui uma reserva para esta data']);
        exit;
    }

    // Verificar horário limite (apenas para hoje)
    $fora_do_horario = false;
    $hora_atual = date('H:i');
    $horario_limite = get_config('hora_limite', '09:00');
    $valor_fora_horario = get_config('valor_fora_horario', '30.00');
    
    if ($data === $hoje) {
        $fora_do_horario = $hora_atual > $horario_limite;
    }

    // Buscar valor normal do usuário
    $valor_normal = 0.00;
    $stmt = $conn->prepare("SELECT u.id_valor, gv.valor FROM usuarios u LEFT JOIN grupo_valor gv ON u.id_valor = gv.id WHERE u.id = ?");
    $stmt->bind_param("i", $_SESSION['usuario_id']);
    $stmt->execute();
    $stmt->bind_result($id_valor, $valor_grupo);
    if ($stmt->fetch()) {
        if ($id_valor && $valor_grupo) {
            $valor_normal = floatval($valor_grupo);
        } else {
            $valor_normal = floatval(get_config('valor_refeicao', '0.00'));
        }
    }
    $stmt->close();

    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Horário disponível para reserva',
        'data' => $data,
        'tipo' => $tipo,
        'fora_do_horario' => $fora_do_horario,
        'hora_atual' => $hora_atual,
        'horario_limite' => $horario_limite,
        'valor_normal' => $valor_normal,
        'valor_fora_horario' => floatval($valor_fora_horario)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao verificar horário: ' . $e->getMessage()
    ]);
}
?>