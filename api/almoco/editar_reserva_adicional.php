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
$reserva_id = intval($_POST['id'] ?? 0);
$data = $_POST['data'] ?? '';
$quantidade = intval($_POST['quantidade'] ?? 0);
$detalhe = trim($_POST['detalhe'] ?? '');
$tipo = $_POST['tipo'] ?? '';
$id_dependente = intval($_POST['dependente'] ?? 0);
$fora_do_horario = isset($_POST['fora_do_horario']) && $_POST['fora_do_horario'] === 'true';

if ($reserva_id <= 0 || empty($data) || $quantidade <= 0 || !in_array($tipo, ['presencial', 'marmitex']) || $id_dependente <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos.']);
    exit;
}

// Verifica se marmitex está habilitado
$marmitex_habilitado = get_config('marmitex_habilitado', '0');
if ($tipo === 'marmitex' && $marmitex_habilitado !== '1') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Reservas para marmitex estão desabilitadas no sistema.']);
    exit;
}

try {
    // Verificar se a reserva pertence ao usuário (através do dependente)
    $stmt = $conn->prepare("SELECT ra.id FROM reservas_adicionais ra 
                           INNER JOIN dependentes d ON ra.id_dependente = d.id 
                           WHERE ra.id = ? AND d.id_usuario = ?");
    $stmt->bind_param("ii", $reserva_id, $id_usuario);
    $stmt->execute();
    
    if (!$stmt->get_result()->fetch_row()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Reserva não encontrada ou não pertence ao usuário.']);
        exit;
    }
    $stmt->close();

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

    // Regra centralizada em DependenteService (idade-limite vem da config).
    require_once __DIR__ . '/../../core/services/DependenteService.php';
    $recalc = DependenteService::calcularCobrar($conn, $nascimento_dep);
    if ($recalc !== null) {
        $cobrar = $recalc;
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
        if ($stmt->fetch()) {
            $valor_refeicao = $fora_do_horario ? $valor_fora : $valor_grupo;
        }
        $stmt->close();
    }

    // Se for marmitex, adiciona valor do marmitex
    if ($tipo === 'marmitex') {
        $valor_marmitex = $valor_marmitex_padrao;
    }

    // Atualizar reserva adicional
    $stmt = $conn->prepare("UPDATE reservas_adicionais SET 
                           data = ?, 
                           quantidade = ?, 
                           tipo = ?, 
                           id_dependente = ?, 
                           valor_refeicao = ?, 
                           valor_marmitex = ?, 
                           detalhe = ?,
                           fora_do_horario = ?
                           WHERE id = ?");
    
    $stmt->bind_param("sisiddii", $data, $quantidade, $tipo, $id_dependente, $valor_refeicao, $valor_marmitex, $detalhe, $fora_do_horario, $reserva_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Reserva adicional atualizada com sucesso'
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar reserva adicional']);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao atualizar reserva adicional: ' . $e->getMessage()
    ]);
}
?>
