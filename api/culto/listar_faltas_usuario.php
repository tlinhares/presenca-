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
    require_once __DIR__ . '/../conexao.php';
    
    if (!isset($conn) || !$conn) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }
    
    $conn->set_charset("utf8");
    $usuario_id = $_SESSION['usuario_id'];
    
    // Parâmetros de filtro
    $data_inicio = $_GET['data_inicio'] ?? '';
    $data_fim = $_GET['data_fim'] ?? '';
    $filtro_status = $_GET['filtro_status'] ?? '';
    
    // Buscar presenças de culto do usuário (mesma lógica do resumo.php)
    $sql_presencas = "
        SELECT DATE(data) as data_presenca,
               status,
               horario_confirmacao
        FROM presencas_culto 
        WHERE id_usuario = ?
        ORDER BY data DESC
    ";
    
    $stmt_presencas = $conn->prepare($sql_presencas);
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
        ORDER BY j.data_falta DESC
    ";
    
    $stmt_justificativas = $conn->prepare($sql_justificativas);
    $stmt_justificativas->bind_param("i", $usuario_id);
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
    ";
    
    // Adicionar filtros de data se fornecidos
    if (!empty($data_inicio)) {
        $sql_dias_culto .= " AND DATE(data) >= ?";
    }
    
    if (!empty($data_fim)) {
        $sql_dias_culto .= " AND DATE(data) <= ?";
    }
    
    $sql_dias_culto .= " ORDER BY data_culto DESC";
    
    $stmt_dias_culto = $conn->prepare($sql_dias_culto);
    $parametros_dias = [];
    $tipos_dias = "";
    
    if (!empty($data_inicio)) {
        $parametros_dias[] = $data_inicio;
        $tipos_dias .= "s";
    }
    
    if (!empty($data_fim)) {
        $parametros_dias[] = $data_fim;
        $tipos_dias .= "s";
    }
    
    if (!empty($parametros_dias)) {
        $stmt_dias_culto->bind_param($tipos_dias, ...$parametros_dias);
    }
    
    $stmt_dias_culto->execute();
    $resultado_dias_culto = $stmt_dias_culto->get_result();
    
    $dias_com_culto = [];
    while ($dia = $resultado_dias_culto->fetch_assoc()) {
        $dias_com_culto[] = $dia['data_culto'];
    }
    
    // Definir período para buscar dias (mesma lógica do resumo.php)
    $data_inicio_periodo = !empty($data_inicio) ? $data_inicio : '2020-01-01';
    $data_fim_periodo = !empty($data_fim) ? $data_fim : date('Y-m-d');
    
    // Gerar todos os dias do período e filtrar pelos dias da semana configurados
    $dias_culto = [];
    $data_atual = new DateTime($data_inicio_periodo);
    $data_fim_obj = new DateTime($data_fim_periodo);
    
    while ($data_atual <= $data_fim_obj) {
        $dia_semana = $data_atual->format('N'); // 1=segunda, 7=domingo
        $data_str = $data_atual->format('Y-m-d');
        
        if (in_array($dia_semana, $dias_culto_semana)) {
            $dias_culto[] = $data_str;
        }
        
        $data_atual->add(new DateInterval('P1D'));
    }
    
    // Ordenar por data decrescente
    rsort($dias_culto);
    
    // Buscar as faltas do usuário (lógica corrigida para faltas implícitas e justificativas aprovadas)
    $faltas = [];
    $estatisticas = [
        'faltas' => 0,
        'pendentes' => 0,
        'aprovadas' => 0,
        'rejeitadas' => 0,
        'total' => 0
    ];
    
    // Conjunto de datas já processadas (para evitar duplicatas)
    $datas_processadas = [];
    
    foreach ($dias_culto as $dia_culto) {
        // Verificar se é um dia da semana configurado para culto
        $data_obj = new DateTime($dia_culto);
        $dia_semana = $data_obj->format('N'); // 1=segunda, 7=domingo
        
        // Verificar se é dia de culto configurado
        if (in_array($dia_semana, $dias_culto_semana)) {
            // Verificar se houve culto neste dia (pelo menos uma presença)
            if (in_array($dia_culto, $dias_com_culto)) {
                // Houve culto - verificar se o usuário tem presença
                $tem_presenca = isset($presencas[$dia_culto]);
                $status_presenca = $tem_presenca ? $presencas[$dia_culto]['status'] : null;
                
                // Buscar justificativa se existir
                $justificativa = null;
                if (isset($justificativas[$dia_culto])) {
                    $justificativa = $justificativas[$dia_culto];
                }
                
                // Determinar se deve aparecer na lista:
                // - Se não tem presença registrada = falta implícita
                // - Se tem presença com status 'falta' = falta explícita
                // - Se tem justificativa (mesmo que presença seja 'presente' após aprovação) = mostrar
                $deve_aparecer = false;
                $tipo_falta = null;
                
                if (!$tem_presenca) {
                    // Usuário não tem presença registrada = falta implícita
                    $deve_aparecer = true;
                    $tipo_falta = 'implícita';
                } elseif ($status_presenca === 'falta') {
                    // Usuário tem presença registrada com status 'falta' = falta explícita
                    $deve_aparecer = true;
                    $tipo_falta = 'explícita';
                } elseif ($justificativa !== null) {
                    // Tem justificativa (pode ter sido aprovada e status mudou para 'presente')
                    $deve_aparecer = true;
                    $tipo_falta = 'justificada';
                }
                
                if ($deve_aparecer) {
                    // Aplicar filtro de status se fornecido
                    if (!empty($filtro_status)) {
                        if ($filtro_status === 'falta' && $justificativa !== null) {
                            continue; // Pular se queremos apenas faltas sem justificativa
                        } elseif ($filtro_status !== 'falta' && ($justificativa === null || $justificativa['status'] !== $filtro_status)) {
                            continue; // Pular se o status não corresponde
                        }
                    }
                    
                    // Adicionar à lista de faltas
                    $falta = [
                        'data' => $dia_culto,
                        'status' => 'falta',
                        'motivo' => $justificativa ? $justificativa['motivo'] : null,
                        'observacoes' => $justificativa ? $justificativa['observacoes'] : null,
                        'status_justificativa' => $justificativa ? $justificativa['status'] : null,
                        'observacoes_admin' => $justificativa ? $justificativa['observacoes'] : null,
                        'data_cadastro' => null,
                        'tipo_falta' => $tipo_falta
                    ];
                    
                    $faltas[] = $falta;
                    $datas_processadas[] = $dia_culto;
                    $estatisticas['total']++;
                    
                    if (!$justificativa) {
                        $estatisticas['faltas']++;
                    } else {
                        switch ($justificativa['status']) {
                            case 'pendente':
                                $estatisticas['pendentes']++;
                                break;
                            case 'aprovada':
                                $estatisticas['aprovadas']++;
                                break;
                            case 'rejeitada':
                                $estatisticas['rejeitadas']++;
                                break;
                        }
                    }
                }
            }
            // Se não houve culto, não é falta (não adicionar à lista)
        }
        // Se não é dia de culto configurado, não é falta (não adicionar à lista)
    }
    
    // Incluir justificativas que podem não estar nos dias de culto processados
    // (por exemplo, justificativas aprovadas de datas antigas)
    foreach ($justificativas as $data_just => $just) {
        if (!in_array($data_just, $datas_processadas)) {
            // Verificar filtro de data
            if (!empty($data_inicio) && $data_just < $data_inicio) continue;
            if (!empty($data_fim) && $data_just > $data_fim) continue;
            
            // Aplicar filtro de status se fornecido
            if (!empty($filtro_status)) {
                if ($filtro_status === 'falta') continue; // Justificativas não são "faltas sem justificativa"
                if ($just['status'] !== $filtro_status) continue;
            }
            
            $falta = [
                'data' => $data_just,
                'status' => 'falta',
                'motivo' => $just['motivo'],
                'observacoes' => $just['observacoes'],
                'status_justificativa' => $just['status'],
                'observacoes_admin' => $just['observacoes'],
                'data_cadastro' => null,
                'tipo_falta' => 'justificada'
            ];
            
            $faltas[] = $falta;
            $estatisticas['total']++;
            
            switch ($just['status']) {
                case 'pendente':
                    $estatisticas['pendentes']++;
                    break;
                case 'aprovada':
                    $estatisticas['aprovadas']++;
                    break;
                case 'rejeitada':
                    $estatisticas['rejeitadas']++;
                    break;
            }
        }
    }
    
    // Ordenar faltas por data decrescente
    usort($faltas, function($a, $b) {
        return strcmp($b['data'], $a['data']);
    });
    
    echo json_encode([
        'status' => 'ok',
        'faltas' => $faltas,
        'estatisticas' => $estatisticas
    ]);
    
} catch (Exception $e) {
    error_log("Erro em listar_faltas_usuario.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao listar faltas: ' . $e->getMessage()]);
}

$conn->close();
?>
