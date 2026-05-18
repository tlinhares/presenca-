<?php
/**
 * API para Buscar Histórico de Utilizações do Usuário
 */
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
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Usuário não autenticado. Token inválido ou ausente.'
        ]);
        exit;
    }
}

$usuario_id = $_SESSION['usuario_id'];

$estatisticas = isset($_GET['estatisticas']) && $_GET['estatisticas'] == 1;
$dias = isset($_GET['dias']) && is_numeric($_GET['dias']) ? intval($_GET['dias']) : null;
$status = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : null;

try {
    // Buscar valor do KM configurado
    $valor_km = 0;
    $sql_config = "SELECT valor FROM frota_configuracoes WHERE chave = 'valor_km' LIMIT 1";
    $result_config = $conn->query($sql_config);
    if ($result_config && $row_config = $result_config->fetch_assoc()) {
        $valor_km = floatval($row_config['valor']);
    }

    if ($estatisticas) {
        $sql = "SELECT 
                    COUNT(*) as total_viagens,
                    COALESCE(SUM(km_percorrido), 0) as km_total,
                    COALESCE(SUM(tempo_utilizacao), 0) as tempo_total_minutos,
                    COUNT(CASE WHEN MONTH(data_saida) = MONTH(NOW()) AND YEAR(data_saida) = YEAR(NOW()) THEN 1 END) as mes_atual
                FROM frota_utilizacoes
                WHERE id_usuario = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $minutos_total = intval($result['tempo_total_minutos']);
        $horas = floor($minutos_total / 60);
        $mins = $minutos_total % 60;
        $tempo_formatado = $horas > 0 ? "{$horas}h {$mins}min" : "{$mins}min";

        $km_total = intval($result['km_total']);
        $valor_total_locacao = $km_total * $valor_km;
        
        echo json_encode([
            'status' => 'ok',
            'estatisticas' => [
                'total_viagens' => intval($result['total_viagens']),
                'km_total' => $km_total,
                'tempo_total' => $tempo_formatado,
                'mes_atual' => intval($result['mes_atual']),
                'valor_km' => $valor_km,
                'valor_total_locacao' => $valor_total_locacao
            ]
        ]);
        exit;
    }
    
    // Buscar utilizações
    $sql = "SELECT fu.*, 
                   v.placa, v.modelo, v.marca, v.cor,
                   e.entidade_nome,
                   fd.nome as departamento_nome,
                   DATE_FORMAT(fu.data_saida, '%d/%m/%Y %H:%i') as data_saida_formatada,
                   DATE_FORMAT(fu.data_entrada, '%d/%m/%Y %H:%i') as data_entrada_formatada
            FROM frota_utilizacoes fu
            JOIN frota_veiculos v ON fu.id_veiculo = v.id
            LEFT JOIN entidade e ON fu.id_entidade = e.entidade_id
            LEFT JOIN frota_departamentos fd ON fu.id_departamento = fd.id
            WHERE fu.id_usuario = ?";
    
    $params = [$usuario_id];
    $types = "s";
    
    if ($dias) {
        $sql .= " AND fu.data_saida >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = $dias;
        $types .= "i";
    }
    
    if ($status) {
        $sql .= " AND fu.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $sql .= " ORDER BY fu.data_saida DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $utilizacoes = [];
    while ($row = $result->fetch_assoc()) {
        // Formatar tempo
        $tempo_formatado = null;
        if ($row['tempo_utilizacao']) {
            $minutos = intval($row['tempo_utilizacao']);
            $horas = floor($minutos / 60);
            $mins = $minutos % 60;
            $tempo_formatado = $horas > 0 ? "{$horas}h {$mins}min" : "{$mins}min";
        }
        
        $km_perc = $row['km_percorrido'] ? intval($row['km_percorrido']) : null;
        $valor_locacao = $km_perc !== null ? $km_perc * $valor_km : null;

        $utilizacoes[] = [
            'id' => intval($row['id']),
            'placa' => $row['placa'],
            'modelo' => $row['modelo'],
            'marca' => $row['marca'],
            'entidade' => $row['entidade_nome'],
            'departamento' => $row['departamento_nome'],
            'data_saida' => $row['data_saida'],
            'data_saida_formatada' => $row['data_saida_formatada'],
            'data_entrada' => $row['data_entrada'],
            'data_entrada_formatada' => $row['data_entrada_formatada'],
            'km_saida' => intval($row['km_saida']),
            'km_entrada' => $row['km_entrada'] ? intval($row['km_entrada']) : null,
            'km_percorrido' => $km_perc,
            'tempo_formatado' => $tempo_formatado,
            'destino' => $row['destino'],
            'motivo' => $row['motivo'],
            'status' => $row['status'],
            'valor_km' => $valor_km,
            'valor_locacao' => $valor_locacao
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'utilizacoes' => $utilizacoes,
        'total' => count($utilizacoes)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar histórico: ' . $e->getMessage()
    ]);
}
?>

