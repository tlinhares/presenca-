<?php
/**
 * API: Obter Frequência de Culto do Usuário
 * 
 * Endpoint: GET /api/culto/frequencia.php?mes=YYYY-MM
 * 
 * Retorna dados de frequência do mês especificado:
 * - Percentual de frequência
 * - Quantidade de presenças (PRE)
 * - Quantidade de atrasos (ATR)
 * - Quantidade de faltas (FAL)
 * - Quantidade de justificadas (JUS)
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

include_once(__DIR__ . '/../conexao.php');
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
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Usuário não autenticado. Token inválido ou ausente.'
        ]);
        exit;
    }
}

$id_usuario = $_SESSION['usuario_id'];
$mes = $_GET['mes'] ?? date('Y-m');

// Validar formato do mês (YYYY-MM)
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Formato de mês inválido. Use YYYY-MM'
    ]);
    exit;
}

try {
    // Calcular primeiro e último dia do mês
    $data_inicio = $mes . '-01';
    $ultimo_dia = date('t', strtotime($data_inicio));
    $data_fim = $mes . '-' . str_pad($ultimo_dia, 2, '0', STR_PAD_LEFT);
    
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
    $stmt_config->close();
    
    // Converter string de dias para array
    $dias_culto_semana = array_map('trim', explode(',', $dias_semana_config));
    
    // Extrair ano e mês para usar na lógica igual à web
    $ano = intval(substr($mes, 0, 4));
    $mes_num = intval(substr($mes, 5, 2));
    
    // Buscar dias onde houve culto (pelo menos uma presença) no mês
    $sql_dias_culto = "
        SELECT DISTINCT DATE(data) as data_culto
        FROM presencas_culto 
        WHERE status IN ('presente', 'atrasado')
        AND YEAR(data) = ? AND MONTH(data) = ?
    ";
    
    $stmt_dias_culto = $conn->prepare($sql_dias_culto);
    $stmt_dias_culto->bind_param("ii", $ano, $mes_num);
    $stmt_dias_culto->execute();
    $resultado_dias_culto = $stmt_dias_culto->get_result();
    
    $dias_com_culto = [];
    while ($dia = $resultado_dias_culto->fetch_assoc()) {
        $dias_com_culto[] = $dia['data_culto'];
    }
    $stmt_dias_culto->close();
    
    // Buscar presenças do usuário no mês
    $sql_presencas = "
        SELECT DATE(data) as data_presenca,
               status
        FROM presencas_culto 
        WHERE id_usuario = ?
        AND YEAR(data) = ? AND MONTH(data) = ?
    ";
    
    $stmt_presencas = $conn->prepare($sql_presencas);
    $stmt_presencas->bind_param("iii", $id_usuario, $ano, $mes_num);
    $stmt_presencas->execute();
    $result_presencas = $stmt_presencas->get_result();
    
    $presencas = [];
    while ($row = $result_presencas->fetch_assoc()) {
        $presencas[$row['data_presenca']] = $row['status'];
    }
    $stmt_presencas->close();
    
    // Buscar justificativas do usuário no mês
    $sql_justificativas = "
        SELECT DATE(data_falta) as data_falta,
               status
        FROM justificativas_culto
        WHERE id_usuario = ?
        AND YEAR(data_falta) = ? AND MONTH(data_falta) = ?
    ";
    
    $stmt_justificativas = $conn->prepare($sql_justificativas);
    $stmt_justificativas->bind_param("iii", $id_usuario, $ano, $mes_num);
    $stmt_justificativas->execute();
    $result_justificativas = $stmt_justificativas->get_result();
    
    $justificativas = [];
    while ($row = $result_justificativas->fetch_assoc()) {
        $justificativas[$row['data_falta']] = $row['status'];
    }
    $stmt_justificativas->close();
    
    // Contadores
    $presentes = 0;
    $atrasados = 0;
    $faltas = 0;
    $justificadas = 0;
    $total_dias_culto = 0;
    
    // Percorrer dias do mês (igual à API web)
    $dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes_num, $ano);
    $hoje = date('Y-m-d');
    
    for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
        $data_completa = sprintf('%s-%02d-%02d', $ano, $mes_num, $dia);
        
        // Ignorar datas futuras
        if ($data_completa > $hoje) {
            continue;
        }
        
        // Verificar se é dia de culto configurado
        $data_obj = new DateTime($data_completa);
        $dia_semana = $data_obj->format('N'); // 1=segunda, 7=domingo
        
        // Só conta se for dia de culto configurado E realmente teve culto
        if (in_array($dia_semana, $dias_culto_semana) && in_array($data_completa, $dias_com_culto)) {
            $total_dias_culto++;
            
            // Verificar status do usuário neste dia
            if (isset($justificativas[$data_completa])) {
                // Tem justificativa
                if ($justificativas[$data_completa] === 'aprovada') {
                    $justificadas++;
                } else {
                    $faltas++; // Justificativa pendente ou rejeitada conta como falta
                }
            } elseif (isset($presencas[$data_completa])) {
                // Tem presença
                if ($presencas[$data_completa] === 'presente') {
                    $presentes++;
                } elseif ($presencas[$data_completa] === 'atrasado') {
                    $atrasados++;
                } else {
                    $faltas++;
                }
            } else {
                // Não tem nada - é falta
                $faltas++;
            }
        }
    }
    
    // Calcular percentual de presença (presentes + atrasados + justificadas aceitas)
    // Usar total_dias_culto em vez da soma dos contadores para evitar erros
    if ($total_dias_culto > 0) {
        $total_presencas = $presentes + $atrasados + $justificadas;
        $percentual_frequencia = round(($total_presencas / $total_dias_culto) * 100, 1);
    } else {
        $percentual_frequencia = 0;
    }
    
    // Total para compatibilidade (soma dos contadores)
    $total = $presentes + $atrasados + $faltas + $justificadas;
    
    // Retornar dados no formato esperado pelo mobile
    echo json_encode([
        'status' => 'ok',
        'mes' => $mes,
        'frequencia' => [
            'percentual' => $percentual_frequencia,
            'presentes' => $presentes,
            'atrasados' => $atrasados,
            'faltas' => $faltas,
            'justificadas' => $justificadas,
            'total' => $total
        ],
        'dados' => [
            'PRE' => $presentes,
            'ATR' => $atrasados,
            'FAL' => $faltas,
            'JUS' => $justificadas
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erro em frequencia.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao calcular frequência: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
