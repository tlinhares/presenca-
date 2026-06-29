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
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado.']);
        exit;
    }
}

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

// Extrai dados (compatível com ambos os formatos)
$data = $input_data['data'] ?? '';
$quantidade = intval($input_data['quantidade'] ?? 0);
$detalhe = trim($input_data['detalhe'] ?? '');
$tipo = $input_data['tipo'] ?? '';
$id_dependente = intval($input_data['dependente'] ?? 0);

// Aceita fora_do_horario como string 'true', int 1, ou bool true
$fora_do_horario_raw = $input_data['fora_do_horario'] ?? false;
$fora_do_horario = (
    $fora_do_horario_raw === 'true' || 
    $fora_do_horario_raw === true || 
    $fora_do_horario_raw === 1 || 
    $fora_do_horario_raw === '1'
);

if (empty($data) || $quantidade <= 0 || !in_array($tipo, ['presencial', 'marmitex']) || $id_dependente <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos.']);
    exit;
}

// Verifica se marmitex está habilitado
$marmitex_habilitado = get_config('marmitex_habilitado', '0');
if ($tipo === 'marmitex' && $marmitex_habilitado !== '1') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Reservas para marmitex estão desabilitadas no sistema.']);
    exit;
}

// Verifica se o dependente pertence ao usuário e obtém cobrar + nascimento
$stmt = $conn->prepare("SELECT cobrar, nascimento FROM dependentes WHERE id = ? AND id_usuario = ? AND ativo = 1");
$stmt->bind_param("ii", $id_dependente, $id_usuario);
$stmt->execute();
$stmt->bind_result($cobrar, $nascimento_dep);
if (!$stmt->fetch()) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dependente inválido.']);
    exit;
}
$stmt->close();

// Defesa em profundidade: recalcula 'cobrar' pela idade real, independente
// do que está no banco. Se a data de nascimento existe, ela é a fonte da
// verdade — assim mesmo um campo 'cobrar' corrompido (ex.: gravado errado
// por outro endpoint) não causa cobrança indevida em dependente <= 12 anos.
if (!empty($nascimento_dep)) {
    try {
        $idade_dep = (new DateTime())->diff(new DateTime($nascimento_dep))->y;
        $cobrar = ($idade_dep <= 12) ? 1 : 0;
    } catch (Exception $e) {
        // fallback silencioso: mantém o cobrar do banco
    }
}

// Configurações globais
$valor_fora = floatval(get_config('valor_fora_horario', '30.00'));
$valor_marmitex_padrao = floatval(get_config('valor_marmitex', '0.00'));

// Inicializa valores
$valor_refeicao = 0.00;
$valor_marmitex = 0.00;

if ($cobrar == 0) {
    // MAIOR de 12 anos → Cobra refeição com base no grupo do titular

    $stmt = $conn->prepare("SELECT gv.valor FROM usuarios u JOIN grupo_valor gv ON u.id_valor = gv.id WHERE u.id = ?");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $stmt->bind_result($valor_grupo);
    if (!$stmt->fetch()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário sem grupo de valor associado.']);
        exit;
    }
    $stmt->close();

    $valor_grupo = floatval($valor_grupo);

    if ($fora_do_horario) {
        $valor_refeicao = ($tipo === 'presencial') ? $valor_fora : 0.00;
        $valor_marmitex = ($tipo === 'marmitex') ? $valor_fora : 0.00;
    } else {
        $valor_refeicao = ($tipo === 'presencial') ? $valor_grupo : 0.00;
        $valor_marmitex = ($tipo === 'marmitex') ? $valor_marmitex_padrao : 0.00;
    }

} else {
    // MENOR de 12 anos → Não cobra
    $valor_refeicao = 0.00;
    $valor_marmitex = 0.00;
}

// Inserção
$stmt = $conn->prepare("INSERT INTO reservas_adicionais 
    (id_usuario, id_dependente, data, quantidade, detalhe, tipo, data_cadastro, valor_refeicao, valor_marmitex) 
    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
$stmt->bind_param("iissssdd", $id_usuario, $id_dependente, $data, $quantidade, $detalhe, $tipo, $valor_refeicao, $valor_marmitex);

if (!$stmt->execute()) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao salvar reserva adicional.', 'debug' => $stmt->error]);
    exit;
}

// Buscar nome do dependente para notificação
$stmt_dep = $conn->prepare("SELECT nome FROM dependentes WHERE id = ?");
$stmt_dep->bind_param("i", $id_dependente);
$stmt_dep->execute();
$stmt_dep->bind_result($dependente_nome);
$stmt_dep->fetch();
$stmt_dep->close();

// Enviar notificação se habilitada
require_once __DIR__ . '/../notificacao/enviar_notificacao_reserva.php';
$valor_total = ($valor_refeicao + $valor_marmitex) * $quantidade;
$horario_atual = date('H:i');
$dados_notificacao = [
    'data' => date('d/m/Y', strtotime($data)),
    'horario' => $horario_atual,
    'dependente_nome' => $dependente_nome ?? 'Dependente',
    'tipo' => $tipo,
    'quantidade' => $quantidade,
    'valor_total' => $valor_total,
    'fora_horario' => $fora_do_horario
];
enviarNotificacaoReserva($id_usuario, 'adicional', $dados_notificacao, $conn);

echo json_encode(['status' => 'ok']);
$stmt->close();
$conn->close();

