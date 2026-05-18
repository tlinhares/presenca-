<?php
/**
 * API para listar todas as reservas (próprias e de dependentes)
 * Acesso apenas para gestores com permissão
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não logado']);
    exit;
}

// Verificar permissão
if (!MenuPermissaoService::podeAcessar('gestao_reservas')) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado']);
    exit;
}

$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$id_usuario_filtro = isset($_GET['id_usuario']) && !empty($_GET['id_usuario']) ? (int)$_GET['id_usuario'] : null;

try {
    $reservas = [];
    
    // Buscar reservas próprias
    $sql_proprias = "SELECT 
                        ra.id,
                        ra.id_usuario,
                        ra.data,
                        ra.valor_refeicao,
                        ra.horario_confirmacao,
                        u.nome as usuario_nome,
                        u.email as usuario_email,
                        'propria' as tipo_reserva,
                        NULL as dependente_nome,
                        NULL as parentesco
                    FROM reservas_almoco ra
                    INNER JOIN usuarios u ON ra.id_usuario = u.id
                    WHERE ra.data >= ? AND ra.data <= ?";
    
    $params = [$data_inicio, $data_fim];
    $types = "ss";
    
    if ($id_usuario_filtro !== null) {
        $sql_proprias .= " AND ra.id_usuario = ?";
        $params[] = $id_usuario_filtro;
        $types .= "i";
    }
    
    $sql_proprias .= " ORDER BY ra.data DESC, ra.horario_confirmacao DESC";
    
    $stmt = $conn->prepare($sql_proprias);
    if ($id_usuario_filtro !== null) {
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param($types, $data_inicio, $data_fim);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $reservas[] = $row;
    }
    $stmt->close();
    
    // Buscar reservas de dependentes
    $sql_adicionais = "SELECT 
                        ra.id,
                        ra.id_usuario,
                        ra.data,
                        ra.valor_refeicao,
                        ra.valor_marmitex,
                        ra.data_cadastro as horario_confirmacao,
                        u.nome as usuario_nome,
                        u.email as usuario_email,
                        'dependente' as tipo_reserva,
                        d.nome as dependente_nome,
                        d.parentesco
                    FROM reservas_adicionais ra
                    INNER JOIN usuarios u ON ra.id_usuario = u.id
                    LEFT JOIN dependentes d ON ra.id_dependente = d.id
                    WHERE ra.data >= ? AND ra.data <= ?";
    
    $params_adicionais = [$data_inicio, $data_fim];
    $types_adicionais = "ss";
    
    if ($id_usuario_filtro !== null) {
        $sql_adicionais .= " AND ra.id_usuario = ?";
        $params_adicionais[] = $id_usuario_filtro;
        $types_adicionais .= "i";
    }
    
    $sql_adicionais .= " ORDER BY ra.data DESC, ra.data_cadastro DESC";
    
    $stmt = $conn->prepare($sql_adicionais);
    if ($id_usuario_filtro !== null) {
        $stmt->bind_param($types_adicionais, ...$params_adicionais);
    } else {
        $stmt->bind_param($types_adicionais, $data_inicio, $data_fim);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $reservas[] = $row;
    }
    $stmt->close();
    
    // Ordenar por data e horário
    usort($reservas, function($a, $b) {
        $dataA = $a['data'] . ' ' . ($a['horario_confirmacao'] ?? '00:00:00');
        $dataB = $b['data'] . ' ' . ($b['horario_confirmacao'] ?? '00:00:00');
        return strtotime($dataB) - strtotime($dataA);
    });
    
    // Calcular estatísticas
    $total_reservas = count($reservas);
    $valor_total = 0;
    foreach ($reservas as $reserva) {
        $valor_total += floatval($reserva['valor_refeicao'] ?? 0);
        if (isset($reserva['valor_marmitex'])) {
            $valor_total += floatval($reserva['valor_marmitex']);
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'reservas' => $reservas,
        'estatisticas' => [
            'total' => $total_reservas,
            'valor_total' => $valor_total
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao listar reservas: ' . $e->getMessage()
    ]);
}

$conn->close();
?>

