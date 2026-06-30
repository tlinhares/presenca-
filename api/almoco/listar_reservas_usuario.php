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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

require_once __DIR__ . '/../conexao.php';
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
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não logado']);
        exit;
    }
}

$id_usuario = $_SESSION['usuario_id'] ?? '';

$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

try {
    // Buscar apenas reservas do usuário principal (não dos dependentes)
    $sql = "SELECT ra.id, ra.data, ra.valor_refeicao as valor, ra.horario_confirmacao
            FROM reservas_almoco ra
            WHERE ra.id_usuario = ? 
            AND ra.data >= ? 
            AND ra.data <= ?
            ORDER BY ra.data DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $id_usuario, $data_inicio, $data_fim);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reservas = [];
    $hoje = date('Y-m-d');
    $hora_limite = get_config('hora_limite', '09:00:00');
    $hora_atual = date('H:i:s');
    $valor_total = 0;
    $quantidade_reservas = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Determinar status e se pode excluir
        $status = '';
        $pode_excluir = false;
        
        if ($row['data'] < $hoje) {
            // Data passada
            $status = 'Finalizada';
            $pode_excluir = false;
        } elseif ($row['data'] > $hoje) {
            // Data futura
            $status = 'Futura';
            $pode_excluir = true;
        } else {
            // Data de hoje
            if ($hora_atual <= $hora_limite) {
                $status = 'Atual';
                $pode_excluir = true;
            } else {
                $status = 'Finalizada';
                $pode_excluir = false;
            }
        }
        
        // Somar ao total
        $valor_total += floatval($row['valor']);
        $quantidade_reservas++;
        
        $reservas[] = [
            'id' => $row['id'],
            'data' => $row['data'],
            'valor' => $row['valor'],
            'status' => $status,
            'pode_excluir' => $pode_excluir
        ];
    }
    $stmt->close();

    // ATENÇÃO: o resumo retornado SOMA reservas próprias + reservas de
    // dependentes no MESMO INTERVALO. Isso é proposital para que o card
    // "Refeições confirmadas" do dashboard mostre o total da família
    // (igual o site faz via /api/calendario/resumo_refeicoes.php).
    // A lista `reservas[]` continua só com as próprias — quem quiser a lista
    // dos dependentes usa /api/almoco/listar_reservas_adicionais_usuario.php.
    $quantidade_dependentes = 0;
    $valor_dependentes = 0.0;
    $stmt_dep = $conn->prepare("SELECT COUNT(*) AS qtd,
                                       COALESCE(SUM(ra.valor_refeicao + ra.valor_marmitex), 0) AS valor
                                  FROM reservas_adicionais ra
                                  JOIN dependentes d ON d.id = ra.id_dependente
                                 WHERE d.id_usuario = ? AND ra.data BETWEEN ? AND ?");
    if ($stmt_dep) {
        $stmt_dep->bind_param("iss", $id_usuario, $data_inicio, $data_fim);
        $stmt_dep->execute();
        $row_dep = $stmt_dep->get_result()->fetch_assoc();
        if ($row_dep) {
            $quantidade_dependentes = (int) $row_dep['qtd'];
            $valor_dependentes      = (float) $row_dep['valor'];
        }
        $stmt_dep->close();
    }

    echo json_encode([
        'status' => 'ok',
        'reservas' => $reservas,
        'resumo' => [
            // Total consolidado (próprias + dependentes) — o que o app deve exibir.
            'quantidade'   => $quantidade_reservas + $quantidade_dependentes,
            'valor_total'  => $valor_total + $valor_dependentes,
            // Breakdown opcional pra quem quiser detalhar.
            'proprias'     => ['quantidade' => $quantidade_reservas, 'valor_total' => $valor_total],
            'dependentes'  => ['quantidade' => $quantidade_dependentes, 'valor_total' => $valor_dependentes],
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro: ' . $e->getMessage()]);
}

$conn->close();
?>
