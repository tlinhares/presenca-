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

try {
    require_once __DIR__ . '/../../api/conexao.php';
    
    if (!isset($conn) || !$conn) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }
    
    $conn->set_charset("utf8");
    
    $id_usuario = $_SESSION['usuario_id'];
    $mes = $_GET['mes'] ?? date('Y-m');
    $periodo = $_GET['periodo'] ?? 'mes';
    
    // Calcular datas baseado no período
    $data_inicio = '';
    $data_fim = '';
    
    switch ($periodo) {
        case 'mes':
            $data_inicio = $mes . '-01';
            $data_fim = date('Y-m-t', strtotime($data_inicio));
            break;
        case '3meses':
            $data_inicio = date('Y-m-01', strtotime('-2 months', strtotime($mes . '-01')));
            $data_fim = date('Y-m-t', strtotime($mes . '-01'));
            break;
        case '6meses':
            $data_inicio = date('Y-m-01', strtotime('-5 months', strtotime($mes . '-01')));
            $data_fim = date('Y-m-t', strtotime($mes . '-01'));
            break;
        case 'ano':
            $ano = date('Y', strtotime($mes . '-01'));
            $data_inicio = $ano . '-01-01';
            $data_fim = $ano . '-12-31';
            break;
        case 'todos':
            $data_inicio = '2020-01-01';
            $data_fim = date('Y-m-d');
            break;
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
    $stmt_config->close();
    
    // Converter string de dias para array
    $dias_culto_semana = array_map('trim', explode(',', $dias_semana_config));
    
    // Gerar lista de dias de culto programados baseado nos dias da semana configurados
    $dias_culto_programados = [];
    $data_culto_atual = new DateTime($data_inicio);
    $data_culto_fim = new DateTime($data_fim);
    
    while ($data_culto_atual <= $data_culto_fim) {
        $dia_semana = $data_culto_atual->format('N'); // 1=segunda, 7=domingo
        $data_str = $data_culto_atual->format('Y-m-d');
        
        if (in_array($dia_semana, $dias_culto_semana)) {
            $dias_culto_programados[] = $data_str;
        }
        
        $data_culto_atual->add(new DateInterval('P1D'));
    }
    
    // Buscar dias onde realmente houve culto (pelo menos uma presença confirmada)
    $stmt_check = $conn->prepare("SELECT DISTINCT DATE(data) as data_culto FROM presencas_culto WHERE data BETWEEN ? AND ? AND status IN ('presente', 'atrasado')");
    $stmt_check->bind_param("ss", $data_inicio, $data_fim);
    $stmt_check->execute();
    $check_result = $stmt_check->get_result();
    $datas_com_presencas = [];
    while ($row = $check_result->fetch_assoc()) {
        $datas_com_presencas[] = $row['data_culto'];
    }
    $stmt_check->close();
    
    // Buscar presenças do usuário
    $stmt = $conn->prepare("
        SELECT 
            pc.data,
            pc.horario_confirmacao,
            pc.status,
            pc.tipo_confirmacao,
            j.id as justificativa_id,
            j.motivo,
            j.observacoes,
            j.status as justificativa_status,
            j.data_aprovacao,
            j.observacoes_admin
        FROM presencas_culto pc
        LEFT JOIN justificativas_culto j ON pc.id_usuario = j.id_usuario AND pc.data = j.data_falta
        WHERE pc.id_usuario = ? 
        AND pc.data BETWEEN ? AND ?
        ORDER BY pc.data DESC
    ");
    $stmt->bind_param("iss", $id_usuario, $data_inicio, $data_fim);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $presencas = [];
    $estatisticas = [
        'total_presentes' => 0,
        'total_atrasados' => 0,
        'total_faltas' => 0,
        'total_justificativas' => 0
    ];
    
    // Criar array de presenças existentes
    $presencas_existentes = [];
    while ($presenca = $resultado->fetch_assoc()) {
        $presencas_existentes[$presenca['data']] = $presenca;
    }
    
    // Gerar array de todas as datas do período
    $data_atual = new DateTime($data_inicio);
    $data_fim_obj = new DateTime($data_fim);
    $data_hoje = new DateTime(); // Data atual para comparar
    $data_hoje->setTime(0, 0, 0); // Zerar hora para comparação apenas de data
    
    while ($data_atual <= $data_fim_obj) {
        $data_str = $data_atual->format('Y-m-d');
        $data_atual_obj = clone $data_atual;
        $data_atual_obj->setTime(0, 0, 0);
        $eh_data_futura = $data_atual_obj > $data_hoje;
        
        // Verificar se é dia de culto programado (baseado em dias_semana)
        $tem_culto_programado = in_array($data_str, $dias_culto_programados);
        
        // Verificar se realmente houve culto (pelo menos uma presença confirmada)
        $tem_culto_real = in_array($data_str, $datas_com_presencas);
        
        // Determinar se tem culto: DEVE ser dia programado E realmente aconteceu
        // IMPORTANTE: Não considerar falta se não for dia programado OU se não houve culto real
        $tem_culto = $tem_culto_programado && $tem_culto_real;
        
        if (isset($presencas_existentes[$data_str])) {
            // Usar presença existente
            $presenca = $presencas_existentes[$data_str];
            
            // Se tem justificativa, o status deve ser 'justificado'
            if ($presenca['justificativa_id']) {
                $presenca['status'] = 'justificado';
            }
            
            // Adicionar campo tem_culto
            $presenca['tem_culto'] = $tem_culto;
            $presenca['tem_culto_programado'] = $tem_culto_programado;
        } else {
            // Gerar falta automática APENAS se:
            // 1. É dia de culto programado (dias_semana)
            // 2. Realmente houve culto (pelo menos uma presença confirmada)
            // 3. NÃO é data futura
            if ($tem_culto && !$eh_data_futura) {
                $status = 'falta';
            } else {
                $status = 'sem-presenca';
            }
            
            $presenca = [
                'data' => $data_str,
                'horario_confirmacao' => null,
                'status' => $status,
                'tipo_confirmacao' => null,
                'justificativa_id' => null,
                'motivo' => null,
                'observacoes' => null,
                'justificativa_status' => null,
                'data_aprovacao' => null,
                'observacoes_admin' => null,
                'tem_culto' => $tem_culto,
                'tem_culto_programado' => $tem_culto_programado
            ];
        }
        
        // Contar estatísticas APENAS para dias que realmente têm culto
        // (dia programado E realmente aconteceu)
        if ($tem_culto) {
            switch ($presenca['status']) {
                case 'presente':
                    $estatisticas['total_presentes']++;
                    break;
                case 'atrasado':
                    $estatisticas['total_atrasados']++;
                    break;
                case 'falta':
                    $estatisticas['total_faltas']++;
                    break;
                case 'justificado':
                    $estatisticas['total_justificativas']++;
                    break;
            }
        }
        
        // Adicionar dados da justificativa se existir
        if ($presenca['justificativa_id']) {
            $presenca['justificativa'] = [
                'id' => $presenca['justificativa_id'],
                'motivo' => $presenca['motivo'],
                'observacoes' => $presenca['observacoes'],
                'status' => $presenca['justificativa_status'],
                'data_aprovacao' => $presenca['data_aprovacao'],
                'observacoes_admin' => $presenca['observacoes_admin']
            ];
        }
        
        // Remover campos duplicados
        unset($presenca['justificativa_id'], $presenca['motivo'], $presenca['observacoes'], 
              $presenca['justificativa_status'], $presenca['data_aprovacao'], $presenca['observacoes_admin']);
        
        $presencas[] = $presenca;
        $data_atual->add(new DateInterval('P1D'));
    }
    
    echo json_encode([
        'status' => 'ok',
        'presencas' => $presencas,
        'estatisticas' => $estatisticas,
        'periodo' => [
            'inicio' => $data_inicio,
            'fim' => $data_fim
        ],
        'dias_culto' => $dias_culto_programados // Lista de dias com culto programado
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao carregar histórico: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
