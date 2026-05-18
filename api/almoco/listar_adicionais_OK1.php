<?php
include_once(__DIR__ . '/../conexao.php');
include_once(__DIR__ . '/../../utils/config.php');
header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

$id_usuario = $_SESSION['usuario_id'];

$sql = "SELECT id, data, quantidade, detalhe, tipo, data_cadastro, valor_refeicao, valor_marmitex FROM reservas_adicionais WHERE id_usuario = ? ORDER BY data DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$stmt->bind_result($id, $data, $quantidade, $detalhe, $tipo, $data_cadastro, $valor_refeicao, $valor_marmitex);

$reservas = [];
$quantidade_total = 0;

// Configurações do sistema
$hora_limite = get_config('hora_limite', '10:30:00');
$permitir_atraso = get_config('permitir_reserva_atraso', '0');

// Data/hora atual para comparação
$dataHoraAtual = new DateTime();

while ($stmt->fetch()) {
    $quantidade_total += $quantidade;



   $pode_excluir = false;

    $dataCadastro = DateTime::createFromFormat('Y-m-d H:i:s', $data_cadastro);
    $limiteDia = DateTime::createFromFormat('Y-m-d H:i:s', $dataCadastro->format('Y-m-d') . ' ' . $hora_limite);

    if ($permitir_atraso === '1' || $dataHoraAtual <= $limiteDia) {
        $pode_excluir = true;
    }

    $reservas[] = [
        'id' => $id,
        'data' => $data,
        'quantidade' => $quantidade,
        'tipo' => $tipo,
        'detalhe' => $detalhe,
        'data_cadastro' => $data_cadastro,
        'valor_refeicao' => $valor_refeicao,
        'valor_marmitex' => $valor_marmitex,
        'pode_excluir' => $pode_excluir
    ];
}

echo json_encode([
    'status' => 'ok',
    'reservas' => $reservas,
    'quantidade_total' => $quantidade_total
]);
?>
