<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once '../../auth/verifica_sessao.php';
require_once '../../api/conexao.php';

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');

if (!$usuario_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

try {
    // Verificar se a conexão está funcionando
    if (!$conn) {
        throw new Exception('Erro de conexão com o banco de dados');
    }
    
    // Buscar reservas próprias do usuário
    $sql_reservas = "
        SELECT DATE(data) as data_reserva, 
               COUNT(*) as total_reservas
        FROM reservas_almoco 
        WHERE id_usuario = ? 
        GROUP BY DATE(data)
    ";
    
    $stmt_reservas = $conn->prepare($sql_reservas);
    if (!$stmt_reservas) {
        throw new Exception('Erro ao preparar consulta: ' . $conn->error);
    }
    
    $stmt_reservas->bind_param("i", $usuario_id);
    $stmt_reservas->execute();
    $result_reservas = $stmt_reservas->get_result();
    
    $reservas_proprias = [];
    while ($row = $result_reservas->fetch_assoc()) {
        $reservas_proprias[$row['data_reserva']] = [
            'tem_reserva' => true,
            'total' => $row['total_reservas']
        ];
    }
    
    // Buscar reservas de dependentes
    $sql_dependentes = "
        SELECT DATE(ra.data) as data_reserva,
               COUNT(*) as total_dependentes
        FROM reservas_adicionais ra
        JOIN dependentes d ON ra.id_dependente = d.id
        WHERE d.id_usuario = ?
        GROUP BY DATE(ra.data)
    ";
    
    $stmt_dependentes = $conn->prepare($sql_dependentes);
    if (!$stmt_dependentes) {
        throw new Exception('Erro ao preparar consulta de dependentes: ' . $conn->error);
    }
    
    $stmt_dependentes->bind_param("i", $usuario_id);
    $stmt_dependentes->execute();
    $result_dependentes = $stmt_dependentes->get_result();
    
    $reservas_dependentes = [];
    while ($row = $result_dependentes->fetch_assoc()) {
        $reservas_dependentes[$row['data_reserva']] = [
            'tem_dependente' => true,
            'total' => $row['total_dependentes']
        ];
    }
    
    // Combinar dados
    $dados = [];
    
    // Gerar dados para o mês solicitado
    $dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
    
    for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
        $data_completa = sprintf('%s-%02d-%02d', $ano, $mes, $dia);
        
        $dados[$data_completa] = [
            'tem_reserva' => isset($reservas_proprias[$data_completa]),
            'tem_dependente' => isset($reservas_dependentes[$data_completa]),
            'total_reservas' => $reservas_proprias[$data_completa]['total'] ?? 0,
            'total_dependentes' => $reservas_dependentes[$data_completa]['total'] ?? 0
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'dados' => $dados,
        'resumo' => [
            'total_reservas_proprias' => count($reservas_proprias),
            'total_reservas_dependentes' => count($reservas_dependentes)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar dados: ' . $e->getMessage()
    ]);
}
?>
