<?php
/**
 * API de Estatísticas de Presença de Culto
 * Retorna dados para gráfico de pizza com percentuais
 */
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
    if (!$conn) {
        throw new Exception('Erro de conexão com o banco de dados');
    }
    
    // Buscar configuração de dias da semana para culto
    $sql_config = "SELECT valor FROM configuracoes_culto WHERE chave = 'dias_semana'";
    $stmt_config = $conn->prepare($sql_config);
    $stmt_config->execute();
    $resultado_config = $stmt_config->get_result();
    
    $dias_semana_config = '1,2,3,4,5'; // Padrão: segunda a sexta
    if ($resultado_config->num_rows > 0) {
        $config = $resultado_config->fetch_assoc();
        $dias_semana_config = $config['valor'];
    }
    $dias_culto_semana = array_map('trim', explode(',', $dias_semana_config));
    
    // Buscar dias onde houve culto (pelo menos uma presença)
    $sql_dias_culto = "
        SELECT DISTINCT DATE(data) as data_culto
        FROM presencas_culto 
        WHERE status IN ('presente', 'atrasado')
        AND YEAR(data) = ? AND MONTH(data) = ?
    ";
    
    $stmt_dias_culto = $conn->prepare($sql_dias_culto);
    $stmt_dias_culto->bind_param("ii", $ano, $mes);
    $stmt_dias_culto->execute();
    $resultado_dias_culto = $stmt_dias_culto->get_result();
    
    $dias_com_culto = [];
    while ($dia = $resultado_dias_culto->fetch_assoc()) {
        $dias_com_culto[] = $dia['data_culto'];
    }
    
    // Buscar presenças do usuário no mês
    $sql_presencas = "
        SELECT DATE(data) as data_presenca,
               status
        FROM presencas_culto 
        WHERE id_usuario = ?
        AND YEAR(data) = ? AND MONTH(data) = ?
    ";
    
    $stmt_presencas = $conn->prepare($sql_presencas);
    $stmt_presencas->bind_param("iii", $usuario_id, $ano, $mes);
    $stmt_presencas->execute();
    $result_presencas = $stmt_presencas->get_result();
    
    $presencas = [];
    while ($row = $result_presencas->fetch_assoc()) {
        $presencas[$row['data_presenca']] = $row['status'];
    }
    
    // Buscar justificativas do usuário no mês
    $sql_justificativas = "
        SELECT DATE(data_falta) as data_falta,
               status
        FROM justificativas_culto
        WHERE id_usuario = ?
        AND YEAR(data_falta) = ? AND MONTH(data_falta) = ?
    ";
    
    $stmt_justificativas = $conn->prepare($sql_justificativas);
    $stmt_justificativas->bind_param("iii", $usuario_id, $ano, $mes);
    $stmt_justificativas->execute();
    $result_justificativas = $stmt_justificativas->get_result();
    
    $justificativas = [];
    while ($row = $result_justificativas->fetch_assoc()) {
        $justificativas[$row['data_falta']] = $row['status'];
    }
    
    // Calcular estatísticas
    $total_presentes = 0;
    $total_atrasados = 0;
    $total_faltas = 0;
    $total_justificativas = 0;
    $total_dias_culto = 0;
    
    // Percorrer dias do mês
    $dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
    $hoje = date('Y-m-d');
    
    for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
        $data_completa = sprintf('%s-%02d-%02d', $ano, $mes, $dia);
        
        // Ignorar datas futuras
        if ($data_completa > $hoje) {
            continue;
        }
        
        // Verificar se é dia de culto configurado
        $data_obj = new DateTime($data_completa);
        $dia_semana = $data_obj->format('N'); // 1=segunda, 7=domingo
        
        if (in_array($dia_semana, $dias_culto_semana) && in_array($data_completa, $dias_com_culto)) {
            $total_dias_culto++;
            
            // Verificar status do usuário neste dia
            if (isset($justificativas[$data_completa])) {
                // Tem justificativa
                if ($justificativas[$data_completa] === 'aprovada') {
                    $total_justificativas++;
                } else {
                    $total_faltas++; // Justificativa pendente ou rejeitada conta como falta
                }
            } elseif (isset($presencas[$data_completa])) {
                // Tem presença
                if ($presencas[$data_completa] === 'presente') {
                    $total_presentes++;
                } elseif ($presencas[$data_completa] === 'atrasado') {
                    $total_atrasados++;
                } else {
                    $total_faltas++;
                }
            } else {
                // Não tem nada - é falta
                $total_faltas++;
            }
        }
    }
    
    // Calcular percentuais
    $percentual_presentes = $total_dias_culto > 0 ? round(($total_presentes / $total_dias_culto) * 100, 1) : 0;
    $percentual_atrasados = $total_dias_culto > 0 ? round(($total_atrasados / $total_dias_culto) * 100, 1) : 0;
    $percentual_faltas = $total_dias_culto > 0 ? round(($total_faltas / $total_dias_culto) * 100, 1) : 0;
    $percentual_justificativas = $total_dias_culto > 0 ? round(($total_justificativas / $total_dias_culto) * 100, 1) : 0;
    
    echo json_encode([
        'status' => 'ok',
        'estatisticas' => [
            'total_dias_culto' => $total_dias_culto,
            'presentes' => $total_presentes,
            'atrasados' => $total_atrasados,
            'faltas' => $total_faltas,
            'justificativas' => $total_justificativas,
            'percentual_presentes' => $percentual_presentes,
            'percentual_atrasados' => $percentual_atrasados,
            'percentual_faltas' => $percentual_faltas,
            'percentual_justificativas' => $percentual_justificativas
        ],
        'mes' => $mes,
        'ano' => $ano
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar estatísticas: ' . $e->getMessage()
    ]);
}
?>




