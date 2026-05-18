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

require_once(__DIR__ . '/../conexao.php');
require_once(__DIR__ . '/../../utils/config.php');

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
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
        exit;
    }
}

$id_usuario = $_SESSION['usuario_id'];
$hoje = date('Y-m-d');
$horaAtual = date('H:i');
$hora_limite = get_config('hora_limite', '10:30');
$valor_fora_horario = floatval(get_config('valor_fora_horario', '0.00'));

$sql = "SELECT r.id, r.data, r.quantidade, r.tipo, r.detalhe, r.data_cadastro, r.valor_refeicao, r.valor_marmitex, d.nome AS nome_dependente
        FROM reservas_adicionais r
        LEFT JOIN dependentes d ON r.id_dependente = d.id
        WHERE r.id_usuario = ? AND r.data = CURDATE()
        ORDER BY r.data DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$stmt->bind_result($id, $data, $quantidade, $tipo, $detalhe, $data_cadastro, $valor_refeicao, $valor_marmitex, $nome_dependente);


$reservas = [];
$quantidade_total = 0;

while ($stmt->fetch()) {
    if ($data == $hoje) {
        $quantidade_total += $quantidade;
    }

    $pode_excluir = false;
    if ($data == $hoje) {
        if ($valor_marmitex == $valor_fora_horario || $valor_refeicao == $valor_fora_horario) {
            $pode_excluir = true;
        } elseif ($horaAtual <= $hora_limite) {
            $pode_excluir = true;
        }
    }

    $data_formatada = '';
    if ($data_cadastro) {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $data_cadastro);
        if ($dt) {
            $data_formatada = $dt->format('d/m/Y H:i:s');
        }
    }

    $data_pedido_formatada = '';
    if ($data) {
        $dte = DateTime::createFromFormat('Y-m-d', $data);
        if ($dte) {
            $data_pedido_formatada = $dte->format('d/m/Y');
        }
    }

    $reservas[] = [
        'id' => $id,
        'data' => $data_pedido_formatada,
        'quantidade' => $quantidade,
        'tipo' => $tipo,
        'detalhe' => $detalhe,
        'data_cadastro' => $data_formatada,
        'valor_refeicao' => $valor_refeicao,
        'valor_marmitex' => $valor_marmitex,
        'pode_excluir' => $pode_excluir,
        'nome_dependente' => $nome_dependente ?: ''
    ];
}

echo json_encode([
    'reservas' => $reservas,
    'quantidade_total' => $quantidade_total
]);

$stmt->close();
$conn->close();
?>
