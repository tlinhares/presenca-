<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

include_once(__DIR__ . '/../conexao.php');
include_once(__DIR__ . '/../../utils/config.php');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado.']);
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$data = isset($_POST['data']) ? trim($_POST['data']) : '';
$quantidade = isset($_POST['quantidade']) ? intval($_POST['quantidade']) : 0;
$detalhe = isset($_POST['detalhe']) ? trim($_POST['detalhe']) : '';
$tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : '';

if ($data == '' || $quantidade <= 0 || ($tipo != 'presencial' && $tipo != 'marmitex')) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos.']);
    exit;
}

// Verifica se marmitex está habilitado
$marmitex_habilitado = get_config('marmitex_habilitado', '0');
if ($tipo === 'marmitex' && $marmitex_habilitado !== '1') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Reservas para marmitex estão desabilitadas no sistema.']);
    exit;
}

// Define os valores conforme o tipo
$valor_refeicao = 0.00;
$valor_marmitex = 0.00;

if ($tipo === 'presencial') {
    $valor_refeicao = floatval(get_config('valor_refeicao', '0.00'));
} elseif ($tipo === 'marmitex') {
    $valor_marmitex = floatval(get_config('valor_marmitex', '0.00'));
}

$stmt = $conn->prepare("INSERT INTO reservas_adicionais 
    (id_usuario, data, quantidade, detalhe, tipo, data_cadastro, valor_refeicao, valor_marmitex) 
    VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");

if (!$stmt) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao preparar inserção.']);
    exit;
}

$stmt->bind_param("isissdd", $id_usuario, $data, $quantidade, $detalhe, $tipo, $valor_refeicao, $valor_marmitex);

if ($stmt->execute()) {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao salvar reserva adicional.']);
}

$stmt->close();
$conn->close();
?>
