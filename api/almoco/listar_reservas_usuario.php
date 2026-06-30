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
    $hoje = date('Y-m-d');
    $hora_limite = get_config('hora_limite', '09:00:00');
    $hora_atual = date('H:i:s');

    /**
     * Calcula status/pode_excluir a partir da data.
     * Para reservas de dependente, `pode_excluir` SEMPRE é false aqui — a
     * exclusão usa /api/almoco/excluir_reserva_adicional.php com o id correto,
     * não passa por esse endpoint.
     */
    $calcularStatus = function ($data, $eh_propria) use ($hoje, $hora_limite, $hora_atual) {
        if ($data < $hoje) return ['Finalizada', false];
        if ($data > $hoje) return ['Futura', $eh_propria];
        // hoje
        if ($hora_atual <= $hora_limite) return ['Atual', $eh_propria];
        return ['Finalizada', false];
    };

    $reservas = [];
    $quantidade_proprias = 0;
    $valor_proprias = 0.0;

    // 1) Reservas próprias do usuário (reservas_almoco)
    $sql = "SELECT id, data, valor_refeicao AS valor, horario_confirmacao
              FROM reservas_almoco
             WHERE id_usuario = ? AND data BETWEEN ? AND ?
             ORDER BY data DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $id_usuario, $data_inicio, $data_fim);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        list($status, $pode_excluir) = $calcularStatus($row['data'], true);
        $valor_proprias += (float) $row['valor'];
        $quantidade_proprias++;
        $reservas[] = [
            'id'           => (int) $row['id'],
            'data'         => $row['data'],
            'valor'        => (float) $row['valor'],
            'status'       => $status,
            'pode_excluir' => $pode_excluir,
            'tipo'         => 'propria',
        ];
    }
    $stmt->close();

    // 2) Reservas de dependentes (reservas_adicionais) — incluídas na MESMA
    //    array para que o card do app baseado em `reservas.length` mostre o
    //    total da família. Marcadas com `tipo: "adicional"` + `dependente_nome`.
    $quantidade_dependentes = 0;
    $valor_dependentes = 0.0;
    $stmt_dep = $conn->prepare(
        "SELECT ra.id, ra.data, ra.tipo AS tipo_refeicao,
                (ra.valor_refeicao + ra.valor_marmitex) AS valor,
                ra.id_dependente, d.nome AS dependente_nome
           FROM reservas_adicionais ra
           JOIN dependentes d ON d.id = ra.id_dependente
          WHERE d.id_usuario = ? AND ra.data BETWEEN ? AND ?
          ORDER BY ra.data DESC"
    );
    if ($stmt_dep) {
        $stmt_dep->bind_param("iss", $id_usuario, $data_inicio, $data_fim);
        $stmt_dep->execute();
        $rs_dep = $stmt_dep->get_result();
        while ($row = $rs_dep->fetch_assoc()) {
            list($status, $_pe) = $calcularStatus($row['data'], false);
            $valor_dependentes += (float) $row['valor'];
            $quantidade_dependentes++;
            $reservas[] = [
                'id'              => (int) $row['id'],
                'data'            => $row['data'],
                'valor'           => (float) $row['valor'],
                'status'          => $status,
                'pode_excluir'    => false, // exclusão usa endpoint diferente
                'tipo'            => 'adicional',
                'tipo_refeicao'   => $row['tipo_refeicao'], // 'presencial' | 'marmitex'
                'id_dependente'   => (int) $row['id_dependente'],
                'dependente_nome' => $row['dependente_nome'],
            ];
        }
        $stmt_dep->close();
    }

    // Ordenar a lista final por data desc (mistura próprias e adicionais).
    usort($reservas, function ($a, $b) {
        return strcmp($b['data'], $a['data']);
    });

    echo json_encode([
        'status'   => 'ok',
        'reservas' => $reservas,
        'resumo'   => [
            // Total consolidado (próprias + dependentes) — usado pelo card do dashboard.
            'quantidade'  => $quantidade_proprias + $quantidade_dependentes,
            'valor_total' => $valor_proprias + $valor_dependentes,
            // Breakdown opcional pra quem quiser detalhar.
            'proprias'    => ['quantidade' => $quantidade_proprias,    'valor_total' => $valor_proprias],
            'dependentes' => ['quantidade' => $quantidade_dependentes, 'valor_total' => $valor_dependentes],
        ],
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro: ' . $e->getMessage()]);
}

$conn->close();
?>
