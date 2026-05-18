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

require_once __DIR__ . '/../../api/conexao.php';

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
    
    // Buscar presenças de culto do usuário
    $sql_presencas = "
        SELECT DATE(data) as data_presenca,
               status,
               horario_confirmacao
        FROM presencas_culto 
        WHERE id_usuario = ?
        ORDER BY data DESC
    ";
    
    $stmt_presencas = $conn->prepare($sql_presencas);
    if (!$stmt_presencas) {
        throw new Exception('Erro ao preparar consulta de presenças: ' . $conn->error);
    }
    
    $stmt_presencas->bind_param("i", $usuario_id);
    $stmt_presencas->execute();
    $result_presencas = $stmt_presencas->get_result();
    
    $presencas = [];
    while ($row = $result_presencas->fetch_assoc()) {
        $presencas[$row['data_presenca']] = [
            'status' => $row['status'],
            'horario' => $row['horario_confirmacao']
        ];
    }
    
    // Buscar justificativas de falta
    $sql_justificativas = "
        SELECT DATE(j.data_falta) as data_falta,
               j.motivo,
               j.status,
               j.observacoes_admin
        FROM justificativas_culto j
        WHERE j.id_usuario = ?
        AND YEAR(j.data_falta) = ? AND MONTH(j.data_falta) = ?
        ORDER BY j.data_falta DESC
    ";
    
    $stmt_justificativas = $conn->prepare($sql_justificativas);
    if (!$stmt_justificativas) {
        throw new Exception('Erro ao preparar consulta de justificativas: ' . $conn->error);
    }
    
    $stmt_justificativas->bind_param("iii", $usuario_id, $ano, $mes);
    $stmt_justificativas->execute();
    $result_justificativas = $stmt_justificativas->get_result();
    
    $justificativas = [];
    while ($row = $result_justificativas->fetch_assoc()) {
        $justificativas[$row['data_falta']] = [
            'motivo' => $row['motivo'],
            'status' => $row['status'],
            'observacoes' => $row['observacoes_admin']
        ];
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
    
    // Converter string de dias para array
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
    
    // Combinar dados
    $dados = [];
    
    // Data atual para comparar (sem hora)
    $data_hoje = new DateTime();
    $data_hoje->setTime(0, 0, 0);
    
    // Gerar dados para o mês solicitado
    $dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
    
    for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
        $data_completa = sprintf('%s-%02d-%02d', $ano, $mes, $dia);
        
        // Verificar se é um dia da semana configurado para culto
        $data_obj = new DateTime($data_completa);
        $data_obj->setTime(0, 0, 0);
        $dia_semana = $data_obj->format('N'); // 1=segunda, 7=domingo
        $eh_data_futura = $data_obj > $data_hoje;
        
        $dados_dia = [
            'status' => 'sem_dados',
            'justificativa' => null,
            'houve_culto' => false
        ];
        
        // Verificar se é dia de culto configurado (dias_semana)
        $eh_dia_culto_programado = in_array($dia_semana, $dias_culto_semana);
        
        // Verificar se realmente houve culto (pelo menos uma presença confirmada)
        $houve_culto_real = in_array($data_completa, $dias_com_culto);
        
        // Tem culto apenas se: é dia programado E realmente aconteceu
        $tem_culto = $eh_dia_culto_programado && $houve_culto_real;
        
        if ($eh_dia_culto_programado) {
            if ($houve_culto_real) {
                $dados_dia['houve_culto'] = true;
                
                // Verificar se tem justificativa (prioridade sobre presença)
                if (isset($justificativas[$data_completa])) {
                    $justificativa = $justificativas[$data_completa];
                    
                    if ($justificativa['status'] === 'aprovada') {
                        $dados_dia['status'] = 'justificativa_aceita';
                        $dados_dia['justificativa'] = $justificativa['motivo'];
                    } else if ($justificativa['status'] === 'pendente') {
                        $dados_dia['status'] = 'justificativa_pendente';
                        $dados_dia['justificativa'] = $justificativa['motivo'];
                    } else if ($justificativa['status'] === 'rejeitada') {
                        $dados_dia['status'] = 'justificativa_rejeitada';
                        $dados_dia['justificativa'] = $justificativa['motivo'];
                    }
                } else {
                    // Se não tem justificativa, verificar presença
                    if (isset($presencas[$data_completa])) {
                        $dados_dia['status'] = $presencas[$data_completa]['status'];
                    } else {
                        // Se não tem presença nem justificativa
                        // Só é falta se NÃO for data futura
                        if ($eh_data_futura) {
                            $dados_dia['status'] = 'sem_dados';
                        } else {
                            $dados_dia['status'] = 'falta';
                        }
                    }
                }
            } else {
                // É dia de culto configurado, mas não houve culto
                $dados_dia['status'] = 'sem_culto';
            }
        } else {
            // Não é dia de culto configurado
            $dados_dia['status'] = 'nao_culto';
        }
        
        $dados[$data_completa] = $dados_dia;
    }
    
    echo json_encode([
        'status' => 'ok',
        'dados' => $dados,
        'resumo' => [
            'total_presencas' => count(array_filter($presencas, function($p) { return $p['status'] === 'presente'; })),
            'total_faltas' => count(array_filter($presencas, function($p) { return $p['status'] === 'falta'; })),
            'total_justificativas' => count($justificativas)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar dados: ' . $e->getMessage()
    ]);
}
?>
