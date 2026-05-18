<?php
/**
 * API para Listar Veículos da Frota
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

$status = isset($_GET['status']) ? $_GET['status'] : '';
$incluir_inativos = isset($_GET['incluir_inativos']) && $_GET['incluir_inativos'] === '1';

try {
    $sql = "SELECT v.*, 
                   u.utilizacao_id,
                   u.usuario_nome as usuario_atual
            FROM frota_veiculos v
            LEFT JOIN (
                SELECT fu.id as utilizacao_id, 
                       fu.id_veiculo, 
                       us.nome as usuario_nome
                FROM frota_utilizacoes fu
                JOIN usuarios us ON fu.id_usuario = us.id
                WHERE fu.status = 'em_andamento'
            ) u ON v.id = u.id_veiculo
            WHERE 1=1";
    
    // Filtrar por ativo/inativo
    if (!$incluir_inativos) {
        $sql .= " AND v.ativo = 1";
    }
    
    if (!empty($status)) {
        if ($status === 'inativo') {
            // Filtro especial para inativos
            $sql .= " AND v.ativo = 0";
        } else {
            $sql .= " AND v.status = ?";
        }
    }
    
    $sql .= " ORDER BY v.ativo DESC, v.status ASC, v.modelo ASC";
    
    $stmt = $conn->prepare($sql);
    
    // Só faz bind se tiver status e não for 'inativo' (que é tratado diferente)
    if (!empty($status) && $status !== 'inativo') {
        $stmt->bind_param("s", $status);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $veiculos = [];
    while ($row = $result->fetch_assoc()) {
        $veiculos[] = [
            'id' => intval($row['id']),
            'placa' => $row['placa'],
            'modelo' => $row['modelo'],
            'marca' => $row['marca'],
            'ano' => $row['ano'] ? intval($row['ano']) : null,
            'cor' => $row['cor'],
            'km_atual' => intval($row['km_atual']),
            'status' => $row['status'],
            'ativo' => intval($row['ativo']),
            'foto_veiculo' => $row['foto_veiculo'],
            'observacoes' => $row['observacoes'],
            'usuario_atual' => $row['usuario_atual']
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'veiculos' => $veiculos,
        'total' => count($veiculos)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao listar veículos: ' . $e->getMessage()
    ]);
}
?>




