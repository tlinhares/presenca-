<?php
/**
 * API para verificar utilização atual do usuário
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

try {
    $sql = "SELECT fu.*, 
                   v.placa, v.modelo, v.marca, v.cor,
                   e.entidade_nome,
                   DATE_FORMAT(fu.data_saida, '%d/%m/%Y %H:%i') as data_saida_formatada,
                   TIMESTAMPDIFF(MINUTE, fu.data_saida, NOW()) as minutos_uso
            FROM frota_utilizacoes fu
            JOIN frota_veiculos v ON fu.id_veiculo = v.id
            LEFT JOIN entidade e ON fu.id_entidade = e.entidade_id
            WHERE fu.id_usuario = ? 
            AND fu.status = 'em_andamento'
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $utilizacao = $result->fetch_assoc();
        
        // Calcular tempo de uso
        $minutos = intval($utilizacao['minutos_uso']);
        $horas = floor($minutos / 60);
        $mins = $minutos % 60;
        $tempo_uso = $horas > 0 ? "{$horas}h {$mins}min" : "{$mins}min";
        
        echo json_encode([
            'status' => 'ok',
            'tem_veiculo' => true,
            'utilizacao' => [
                'id' => intval($utilizacao['id']),
                'id_veiculo' => intval($utilizacao['id_veiculo']),
                'placa' => $utilizacao['placa'],
                'modelo' => $utilizacao['modelo'],
                'marca' => $utilizacao['marca'],
                'cor' => $utilizacao['cor'],
                'entidade' => $utilizacao['entidade_nome'],
                'data_saida' => $utilizacao['data_saida'],
                'data_saida_formatada' => $utilizacao['data_saida_formatada'],
                'km_saida' => intval($utilizacao['km_saida']),
                'destino' => $utilizacao['destino'],
                'motivo' => $utilizacao['motivo'],
                'tempo_uso' => $tempo_uso
            ]
        ]);
    } else {
        echo json_encode([
            'status' => 'ok',
            'tem_veiculo' => false,
            'utilizacao' => null
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao verificar utilização: ' . $e->getMessage()
    ]);
}
?>


