<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

include_once(__DIR__ . '/../conexao.php');
include_once(__DIR__ . '/../../utils/config.php');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$id_dependente = $_GET['id_dependente'] ?? '';
$tipo = $_GET['tipo'] ?? 'presencial';
$data_reserva = $_GET['data'] ?? date('Y-m-d');
$hora_atual = date('H:i:s');

// Validações
if (empty($id_dependente)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID do dependente não informado']);
    exit;
}

if (!in_array($tipo, ['presencial', 'marmitex'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Tipo de refeição inválido']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_reserva)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Formato de data inválido']);
    exit;
}

if ($data_reserva < date('Y-m-d')) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Não é possível reservar para datas passadas']);
    exit;
}

// Limite de 30 dias no futuro
$data_limite_futuro = date('Y-m-d', strtotime('+30 days'));
if ($data_reserva > $data_limite_futuro) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Não é possível reservar com mais de 30 dias de antecedência']);
    exit;
}

// Verificar se o dependente pertence ao usuário
$stmt = $conn->prepare("SELECT id, nome, cobrar FROM dependentes WHERE id = ? AND id_usuario = ? AND ativo = 1");
$stmt->bind_param("ii", $id_dependente, $id_usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dependente não encontrado ou não pertence ao usuário']);
    exit;
}

$dependente = $result->fetch_assoc();
$stmt->close();

// Verificar se já existe reserva adicional para este dependente nesta data
$stmt = $conn->prepare("SELECT id FROM reservas_adicionais WHERE id_dependente = ? AND data = ?");
$stmt->bind_param("is", $id_dependente, $data_reserva);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe uma reserva adicional para este dependente nesta data']);
    exit;
}
$stmt->close();

// Verificar horário limite para reservas do dia atual
if ($data_reserva == date('Y-m-d')) {
    $hora_limite = get_config('hora_limite', '09:00:00');
    $permite_atraso = get_config('permitir_reserva_atraso', '0');

    if ($hora_atual > $hora_limite && $permite_atraso !== '1') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Horário limite para reservas excedido']);
        exit;
    }
}

// Calcular valores
$valor_refeicao = 0.00;
$valor_marmitex = 0.00;
$fora_do_horario = false;

if ($data_reserva == date('Y-m-d')) {
    $hora_limite = get_config('hora_limite', '09:00:00');
    $fora_do_horario = ($hora_atual > $hora_limite);
}

if ($dependente['cobrar'] == 0) {
    // MAIOR de 12 anos → Cobra refeição com base no grupo do titular
    $stmt = $conn->prepare("SELECT gv.valor FROM usuarios u JOIN grupo_valor gv ON u.id_valor = gv.id WHERE u.id = ?");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $stmt->bind_result($valor_grupo);
    
    if ($stmt->fetch()) {
        $valor_grupo = floatval($valor_grupo);
        
        if ($fora_do_horario) {
            $valor_fora = floatval(get_config('valor_fora_horario', '0.00'));
            $valor_refeicao = ($tipo === 'presencial') ? $valor_fora : 0.00;
            $valor_marmitex = ($tipo === 'marmitex') ? $valor_fora : 0.00;
        } else {
            $valor_refeicao = ($tipo === 'presencial') ? $valor_grupo : 0.00;
            $valor_marmitex = ($tipo === 'marmitex') ? floatval(get_config('valor_marmitex', '0.00')) : 0.00;
        }
    } else {
        // Usuário sem grupo
        $valor_padrao = floatval(get_config('valor_refeicao', '0.00'));
        $valor_marmitex_padrao = floatval(get_config('valor_marmitex', '0.00'));
        
        if ($fora_do_horario) {
            $valor_fora = floatval(get_config('valor_fora_horario', '0.00'));
            $valor_refeicao = ($tipo === 'presencial') ? $valor_fora : 0.00;
            $valor_marmitex = ($tipo === 'marmitex') ? $valor_fora : 0.00;
        } else {
            $valor_refeicao = ($tipo === 'presencial') ? $valor_padrao : 0.00;
            $valor_marmitex = ($tipo === 'marmitex') ? $valor_marmitex_padrao : 0.00;
        }
    }
    $stmt->close();
} else {
    // MENOR de 12 anos → Não cobra
    $valor_refeicao = 0.00;
    $valor_marmitex = 0.00;
}

echo json_encode([
    'status' => 'ok',
    'mensagem' => 'Reserva pode ser feita',
    'dependente' => [
        'id' => $dependente['id'],
        'nome' => $dependente['nome'],
        'cobrar' => $dependente['cobrar']
    ],
    'valores' => [
        'valor_refeicao' => $valor_refeicao,
        'valor_marmitex' => $valor_marmitex,
        'fora_do_horario' => $fora_do_horario
    ]
]);

$conn->close();
?>
