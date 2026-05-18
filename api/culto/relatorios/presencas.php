<?php
include_once(__DIR__ . '/../../conexao.php');
include_once(__DIR__ . '/../../../auth/verifica_sessao.php');
header('Content-Type: application/json');



$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$usuario_id = isset($_GET['usuario_id']) && !empty($_GET['usuario_id']) ? intval($_GET['usuario_id']) : null;

try {
    // Buscar configuração de dias da semana para culto
    $sql_config = "SELECT valor FROM configuracoes_culto WHERE chave = 'dias_semana'";
    $stmt_config = $conn->prepare($sql_config);
    $stmt_config->execute();
    $result_config = $stmt_config->get_result();
    
    $dias_semana_config = '1,2,3,4,5'; // Padrão: segunda a sexta
    if ($result_config->num_rows > 0) {
        $config = $result_config->fetch_assoc();
        $dias_semana_config = $config['valor'];
    }
    $stmt_config->close();
    
    // Converter string de dias para array
    $dias_culto_semana = array_map('trim', explode(',', $dias_semana_config));
    
    // Criar estrutura de calendário para todas as datas do período
    $inicio = new DateTime($data_inicio);
    $fim = new DateTime($data_fim);
    $fim->modify('+1 day');
    $periodo = new DatePeriod($inicio, new DateInterval('P1D'), $fim);
    
    // Filtrar apenas dias que são de culto
    $datas_culto_periodo = [];
    foreach ($periodo as $data) {
        $dia_semana = $data->format('N'); // 1=segunda, 7=domingo
        if (in_array($dia_semana, $dias_culto_semana)) {
            $datas_culto_periodo[] = $data->format('Y-m-d');
        }
    }
    
    // Buscar usuários com culto = 1 - TODOS, mesmo sem presença
    $sql_usuarios = "SELECT id, nome FROM usuarios WHERE culto = 1 AND ativo = 1";
    if ($usuario_id) {
        $sql_usuarios .= " AND id = ?";
        $stmt_usuarios = $conn->prepare($sql_usuarios);
        $stmt_usuarios->bind_param("i", $usuario_id);
    } else {
        $stmt_usuarios = $conn->prepare($sql_usuarios);
    }
    $stmt_usuarios->execute();
    $result_usuarios = $stmt_usuarios->get_result();
    
    // Inicializar TODOS os usuários, mesmo sem presença
    $presencas_por_usuario = [];
    while ($user = $result_usuarios->fetch_assoc()) {
        $presencas_por_usuario[$user['id']] = [
            'id_usuario' => $user['id'],
            'nome_usuario' => $user['nome'],
            'presencas' => [],
            'calendario' => [],
            'presentes' => 0,
            'atrasados' => 0,
            'faltas' => 0,
            'justificados' => 0
        ];
        
        // Inicializar calendário apenas para dias de culto
        foreach ($datas_culto_periodo as $data_str) {
            $presencas_por_usuario[$user['id']]['calendario'][$data_str] = [
                'presentes' => [],
                'atrasados' => [],
                'faltas' => [],
                'justificados' => []
            ];
        }
    }
    $stmt_usuarios->close();
    
    // Buscar todas as presenças no período (filtrar apenas dias de culto)
    if (!empty($datas_culto_periodo)) {
        $placeholders = str_repeat('?,', count($datas_culto_periodo) - 1) . '?';
        $sql_presencas = "SELECT 
                            pc.id,
                            pc.data,
                            pc.horario_confirmacao,
                            pc.tipo_confirmacao,
                            pc.status,
                            pc.observacoes,
                            u.id as id_usuario,
                            u.nome as nome_usuario
                        FROM presencas_culto pc
                        INNER JOIN usuarios u ON pc.id_usuario = u.id
                        WHERE pc.data IN ($placeholders)";
        
        if ($usuario_id) {
            $sql_presencas .= " AND pc.id_usuario = ?";
        }
        
        $sql_presencas .= " ORDER BY u.nome ASC, pc.data DESC";
        
        $stmt_presencas = $conn->prepare($sql_presencas);
        if ($stmt_presencas === false) {
            throw new Exception("Erro na preparação da query: " . $conn->error);
        }
        
        $params = $datas_culto_periodo;
        $types = str_repeat('s', count($datas_culto_periodo));
        if ($usuario_id) {
            $params[] = $usuario_id;
            $types .= 'i';
        }
        $stmt_presencas->bind_param($types, ...$params);
        
        $stmt_presencas->execute();
        $result_presencas = $stmt_presencas->get_result();
        
        while ($row = $result_presencas->fetch_assoc()) {
            $id_user = $row['id_usuario'];
            
            // Verificar se a data está nos dias de culto configurados
            $data_obj = new DateTime($row['data']);
            $dia_semana_data = $data_obj->format('N');
            if (!in_array($dia_semana_data, $dias_culto_semana)) {
                continue; // Pular presenças em dias que não são de culto
            }
            
            if (!isset($presencas_por_usuario[$id_user])) {
                // Se não existir, criar (pode acontecer se usuário foi adicionado depois)
                $presencas_por_usuario[$id_user] = [
                    'id_usuario' => $id_user,
                    'nome_usuario' => $row['nome_usuario'],
                    'presencas' => [],
                    'calendario' => [],
                    'presentes' => 0,
                    'atrasados' => 0,
                    'faltas' => 0,
                    'justificados' => 0
                ];
                foreach ($datas_culto_periodo as $data_str) {
                    $presencas_por_usuario[$id_user]['calendario'][$data_str] = [
                        'presentes' => [],
                        'atrasados' => [],
                        'faltas' => [],
                        'justificados' => []
                    ];
                }
            }
            
            $presencas_por_usuario[$id_user]['presencas'][] = $row;
            
            if ($row['status'] == 'presente') {
                $presencas_por_usuario[$id_user]['presentes']++;
                if (isset($presencas_por_usuario[$id_user]['calendario'][$row['data']])) {
                    $presencas_por_usuario[$id_user]['calendario'][$row['data']]['presentes'][] = [
                        'horario' => $row['horario_confirmacao']
                    ];
                }
            } else if ($row['status'] == 'atrasado') {
                $presencas_por_usuario[$id_user]['atrasados']++;
                if (isset($presencas_por_usuario[$id_user]['calendario'][$row['data']])) {
                    $presencas_por_usuario[$id_user]['calendario'][$row['data']]['atrasados'][] = [
                        'horario' => $row['horario_confirmacao']
                    ];
                }
            }
        }
        $stmt_presencas->close();
    }
    
    // Calcular faltas e justificados para cada usuário (apenas nos dias de culto)
    foreach ($presencas_por_usuario as $id_user => &$dados_usuario) {
        foreach ($datas_culto_periodo as $data_str) {
            $usuario_compareceu = false;
            foreach ($dados_usuario['presencas'] as $presenca) {
                if ($presenca['data'] == $data_str) {
                    $usuario_compareceu = true;
                    break;
                }
            }
            
            // Verificar justificativa (mesmo que tenha comparecido, justificativa tem prioridade)
            $sql_just = "SELECT id, status FROM justificativas_culto WHERE id_usuario = ? AND data_falta = ?";
            $stmt_just = $conn->prepare($sql_just);
            $stmt_just->bind_param("is", $id_user, $data_str);
            $stmt_just->execute();
            $result_just = $stmt_just->get_result();
            $justificativa = $result_just->fetch_assoc();
            $stmt_just->close();
            
            if ($justificativa) {
                $status_just = $justificativa['status'];
                if ($status_just == 'aprovada') {
                    // Justificativa aceita = contar como justificado (prioridade sobre presença)
                    // Remover da contagem de presentes se tinha presença
                    if ($usuario_compareceu) {
                        // Se tinha presença, remover e contar como justificado
                        foreach ($dados_usuario['presencas'] as $key => $presenca) {
                            if ($presenca['data'] == $data_str) {
                                if ($presenca['status'] == 'presente') {
                                    $dados_usuario['presentes']--;
                                } else if ($presenca['status'] == 'atrasado') {
                                    $dados_usuario['atrasados']--;
                                }
                                // Limpar calendário de presença
                                if (isset($dados_usuario['calendario'][$data_str])) {
                                    $dados_usuario['calendario'][$data_str]['presentes'] = [];
                                    $dados_usuario['calendario'][$data_str]['atrasados'] = [];
                                }
                                break;
                            }
                        }
                    }
                    $dados_usuario['justificados']++;
                    if (isset($dados_usuario['calendario'][$data_str])) {
                        $dados_usuario['calendario'][$data_str]['justificados'][] = ['tipo' => 'aprovada'];
                    }
                } else if (!$usuario_compareceu && ($status_just == 'rejeitada' || $status_just == 'pendente')) {
                    // Justificativa rejeitada ou pendente = contar como falta
                    $dados_usuario['faltas']++;
                    if (isset($dados_usuario['calendario'][$data_str])) {
                        $dados_usuario['calendario'][$data_str]['faltas'][] = [
                            'tipo' => $status_just == 'rejeitada' ? 'justificativa_rejeitada' : 'justificativa_pendente'
                        ];
                    }
                }
            } else if (!$usuario_compareceu) {
                // Sem justificativa e sem presença = falta
                $dados_usuario['faltas']++;
                if (isset($dados_usuario['calendario'][$data_str])) {
                    $dados_usuario['calendario'][$data_str]['faltas'][] = [];
                }
            }
        }
        
        // Calcular percentuais
        $total_registros = $dados_usuario['presentes'] + $dados_usuario['atrasados'] + 
                          $dados_usuario['faltas'] + $dados_usuario['justificados'];
        
        $dados_usuario['percentuais'] = [
            'presentes' => $total_registros > 0 ? round(($dados_usuario['presentes'] / $total_registros) * 100, 1) : 0,
            'atrasados' => $total_registros > 0 ? round(($dados_usuario['atrasados'] / $total_registros) * 100, 1) : 0,
            'faltas' => $total_registros > 0 ? round(($dados_usuario['faltas'] / $total_registros) * 100, 1) : 0,
            'justificados' => $total_registros > 0 ? round(($dados_usuario['justificados'] / $total_registros) * 100, 1) : 0
        ];
        
        $dados_usuario['data_inicio'] = $data_inicio;
        $dados_usuario['data_fim'] = $data_fim;
    }
    
    // Converter array associativo para array numérico
    $usuarios_dados = array_values($presencas_por_usuario);
    
    echo json_encode([
        'status' => 'ok',
        'usuarios' => $usuarios_dados,
        'data_inicio' => $data_inicio,
        'data_fim' => $data_fim
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
?>
