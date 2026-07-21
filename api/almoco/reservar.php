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
    // Tenta autenticar via token mobile
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Sua sessão expirou. Faça logoff e login novamente para continuar.']);
        exit;
    }
}

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

$data = $input_data['data'] ?? date('Y-m-d');
$fora_do_horario_raw = $input_data['fora_do_horario'] ?? false;
$fora_do_horario = (
    $fora_do_horario_raw === 'true' || 
    $fora_do_horario_raw === true || 
    $fora_do_horario_raw === 1 || 
    $fora_do_horario_raw === '1'
);

try {
    // Verificar se o refeitório está fechado nesta data
    $stmt = $conn->prepare("SELECT motivo FROM dias_fechado WHERE data = ? AND ativo = 1 LIMIT 1");
    $stmt->bind_param("s", $data);
    $stmt->execute();
    $res_fechado = $stmt->get_result();
    if ($res_fechado->num_rows > 0) {
        $row_fechado = $res_fechado->fetch_assoc();
        $stmt->close();
        $motivo = trim($row_fechado['motivo'] ?? '');
        $msg = 'O refeitório está fechado nesta data' . ($motivo !== '' ? " ($motivo)" : '') . '. Não é possível fazer reservas.';
        echo json_encode(['status' => 'erro', 'mensagem' => $msg]);
        exit;
    }
    $stmt->close();

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

    // Recalcula "fora do horário" NO SERVIDOR — o valor cobrado não pode
    // depender do que o cliente informa (regra idêntica ao verificar_horario.php:
    // só vale para reservas do dia corrente).
    $hora_limite_cfg = get_config('hora_limite', '09:00');
    $fora_srv = ($data === date('Y-m-d')) && (date('H:i') > $hora_limite_cfg);

    if ($fora_srv) {
        // Trava de negócio: reserva em atraso só se habilitada na configuração.
        if (get_config('permitir_reserva_atraso', '0') !== '1') {
            echo json_encode(['status' => 'erro', 'mensagem' => "O horário limite ($hora_limite_cfg) já passou e reservas fora do horário estão desabilitadas."]);
            exit;
        }
        // Valor maior exige consentimento: o front confirma no modal e envia
        // fora_do_horario=true. Sem confirmação, orienta em vez de cobrar mais.
        if (!$fora_do_horario) {
            echo json_encode(['status' => 'erro', 'mensagem' => "O horário limite ($hora_limite_cfg) já passou. Confirme a reserva fora do horário (valor diferenciado) e tente novamente."]);
            exit;
        }
    }
    // Daqui em diante vale o relógio do servidor (cliente confirmando "fora"
    // para uma data futura, por exemplo, paga o valor normal).
    $fora_do_horario = $fora_srv;

    // Definir valor da refeição
    $valor_refeicao = 0.00;

    if ($fora_do_horario) {
        // Usar valor fora do horário
        $valor_refeicao = floatval(get_config('valor_fora_horario', '30.00'));
    } else {
        // Usar valor normal do grupo do usuário.
        // O fallback é lido ANTES de abrir o statement: get_config() com um
        // stmt aberto na mesma conexão falha ("commands out of sync") e
        // devolvia o padrão 0.00 — reserva saía de graça pra quem não tem grupo.
        $valor_refeicao_padrao = floatval(get_config('valor_refeicao', '0.00'));
        $stmt = $conn->prepare("SELECT u.id_valor, gv.valor FROM usuarios u LEFT JOIN grupo_valor gv ON u.id_valor = gv.id WHERE u.id = ?");
        $stmt->bind_param("i", $_SESSION['usuario_id']);
        $stmt->execute();
        $stmt->bind_result($id_valor, $valor_grupo);
        if ($stmt->fetch()) {
            if ($id_valor && $valor_grupo) {
                $valor_refeicao = floatval($valor_grupo);
            } else {
                $valor_refeicao = $valor_refeicao_padrao;
            }
        }
        $stmt->close();
    }

    // Inserir reserva
    $stmt = $conn->prepare("INSERT INTO reservas_almoco (id_usuario, data, horario_confirmacao, valor_refeicao) VALUES (?, ?, NOW(), ?)");
    $stmt->bind_param("isd", $_SESSION['usuario_id'], $data, $valor_refeicao);
    
    if ($stmt->execute()) {
        // Enviar notificação se habilitada
        require_once __DIR__ . '/../notificacao/enviar_notificacao_reserva.php';
        $horario_atual = date('H:i');
        $dados_notificacao = [
            'data' => date('d/m/Y', strtotime($data)),
            'horario' => $horario_atual,
            'valor' => $valor_refeicao,
            'fora_horario' => $fora_do_horario
        ];
        enviarNotificacaoReserva($_SESSION['usuario_id'], 'propria', $dados_notificacao, $conn);
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Reserva realizada com sucesso',
            'valor_aplicado' => $valor_refeicao
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao registrar reserva']);
    }
    
    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao processar reserva: ' . $e->getMessage()
    ]);
}
?>