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
$dependente_id = $_GET['dependente'] ?? '';

try {
    // Buscar reservas adicionais do usuário
    $sql = "SELECT ra.id, ra.data, ra.quantidade, ra.tipo, ra.detalhe, 
                   (ra.valor_refeicao + ra.valor_marmitex) as valor_total,
                   ra.data_cadastro, d.nome as dependente_nome, d.id as dependente_id
            FROM reservas_adicionais ra
            INNER JOIN dependentes d ON ra.id_dependente = d.id
            WHERE ra.id_usuario = ? 
            AND ra.data >= ? 
            AND ra.data <= ?";
    
    // Adicionar filtro por dependente se especificado
    if (!empty($dependente_id)) {
        $sql .= " AND ra.id_dependente = ?";
    }
    
    $sql .= " ORDER BY ra.data DESC";
    
    $stmt = $conn->prepare($sql);
    
    // Bind parameters baseado na presença do filtro de dependente
    if (!empty($dependente_id)) {
        $stmt->bind_param("issi", $id_usuario, $data_inicio, $data_fim, $dependente_id);
    } else {
        $stmt->bind_param("iss", $id_usuario, $data_inicio, $data_fim);
    }
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
        
        // Formatar data de cadastro
        $data_cadastro_formatada = '';
        if ($row['data_cadastro']) {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $row['data_cadastro']);
            if ($dt) {
                $data_cadastro_formatada = $dt->format('d/m/Y H:i');
            }
        }
        
        // Somar ao total
        $valor_total += floatval($row['valor_total']);
        $quantidade_reservas++;
        
        $reservas[] = [
            'id' => $row['id'],
            'data' => $row['data'],
            'quantidade' => intval($row['quantidade']),
            'tipo' => $row['tipo'],
            'detalhe' => $row['detalhe'],
            'valor_total' => floatval($row['valor_total']),
            'data_cadastro' => $data_cadastro_formatada,
            'dependente_id' => intval($row['dependente_id']),
            'dependente_nome' => $row['dependente_nome'],
            'status' => $status,
            'pode_excluir' => $pode_excluir
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'ok',
        'reservas' => $reservas,
        'resumo' => [
            'quantidade' => $quantidade_reservas,
            'valor_total' => $valor_total
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro: ' . $e->getMessage()]);
}

$conn->close();
?>
