<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Verifica permissão de admin
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
if (!MenuPermissaoService::isAdmin()) {
    die('Acesso não autorizado. Por favor, faça login novamente.');
}

require_once(__DIR__ . '/../../../vendor/autoload.php');
include_once(__DIR__ . '/../../conexao.php');

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'presencas';
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$usuario_id = isset($_GET['usuario_id']) && !empty($_GET['usuario_id']) ? intval($_GET['usuario_id']) : null;

// Validar tipo de relatório
$tipos_validos = ['presencas', 'faltas', 'justificativas', 'estatisticas', 'usuario', 'frequencia', 'atrasos', 'comparativo'];
if (!in_array($tipo, $tipos_validos)) {
    die('Tipo de relatório inválido. Tipos válidos: ' . implode(', ', $tipos_validos));
}

try {
    // Inicializar mPDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'P',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'margin_header' => 10,
        'margin_footer' => 10
    ]);

    // Buscar dados baseado no tipo
    if ($tipo === 'presencas') {
        $dados = buscarDadosPresencas($data_inicio, $data_fim, $usuario_id, $conn);
        $html = gerarHTMLPresencas($dados, $data_inicio, $data_fim);
    } else {
        $dados = buscarDadosRelatorio($tipo, $data_inicio, $data_fim, $usuario_id, $conn);
        $html = gerarHTMLRelatorio($tipo, $dados, $data_inicio, $data_fim);
    }

    $mpdf->SetTitle('Relatório de Culto - ' . ucfirst($tipo));
    $mpdf->SetAuthor('Sistema de Presença');
    $mpdf->WriteHTML($html);
    $mpdf->Output('relatorio_culto_' . $tipo . '_' . date('Y-m-d') . '.pdf', 'I');
    
} catch (Exception $e) {
    die('Erro ao gerar PDF: ' . $e->getMessage());
}

function buscarDadosPresencas($data_inicio, $data_fim, $usuario_id, $conn) {
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
    return array_values($presencas_por_usuario);
}

function gerarGraficoPizzaSVG($presentes, $atrasados, $faltas, $justificados) {
    $total = $presentes + $atrasados + $faltas + $justificados;
    
    if ($total == 0) {
        return '<svg width="200" height="200" viewBox="0 0 200 200" class="grafico-svg">
                    <circle cx="100" cy="100" r="80" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/>
                    <text x="100" y="100" text-anchor="middle" dominant-baseline="middle" font-size="12" fill="#666">Sem dados</text>
                </svg>';
    }
    
    $cores = [
        'presente' => '#28a745',    // Verde - Pontual
        'atrasado' => '#007bff',     // Azul - Atraso
        'falta' => '#dc3545',        // Vermelho - Falta
        'justificado' => '#ffc107'    // Amarelo - Justificado
    ];
    
    $valores = [
        'presente' => $presentes,
        'atrasado' => $atrasados,
        'falta' => $faltas,
        'justificado' => $justificados
    ];
    
    $labels = [
        'presente' => 'Pontual',
        'atrasado' => 'Atraso',
        'falta' => 'Falta',
        'justificado' => 'Justificado'
    ];
    
    $cx = 80;   // Mover gráfico para a esquerda para dar espaço para legenda
    $cy = 100;
    $raio = 70;
    $inicio = -90; // Começar do topo
    $svg_paths = '';
    $textos_dentro = '';  // Textos dentro das fatias
    $legendas = '';
    $current_angle = $inicio;
    $legenda_y = 40;  // Começar legenda mais abaixo, alinhada com o centro do gráfico
    $legenda_x = 160;  // Mover legenda para o lado direito
    
    // Verificar se há apenas um tipo de valor (ex: 100% faltas)
    $tipos_com_valor = 0;
    $cor_principal = '';
    foreach ($valores as $key => $valor) {
        if ($valor > 0) {
            $tipos_com_valor++;
            if ($tipos_com_valor == 1) {
                $cor_principal = $cores[$key];
            }
        }
    }
    
    // Se houver apenas um tipo, desenhar círculo completo
    if ($tipos_com_valor == 1) {
        $key_principal = '';
        $valor_principal = 0;
        foreach ($valores as $key => $valor) {
            if ($valor > 0) {
                $key_principal = $key;
                $valor_principal = $valor;
                break;
            }
        }
        
        $percent = 100.0;
        // Desenhar círculo completo
        $svg_paths = '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $raio . '" fill="' . $cor_principal . '" stroke="none"/>';
        
        // Adicionar texto no centro
        $textos_dentro .= '<text x="' . $cx . '" y="' . ($cy - 5) . '" text-anchor="middle" dominant-baseline="middle" font-size="11" font-weight="bold" fill="#fff">' . round($percent, 1) . '%</text>';
        $textos_dentro .= '<text x="' . $cx . '" y="' . ($cy + 8) . '" text-anchor="middle" dominant-baseline="middle" font-size="9" fill="#fff">(' . $valor_principal . ')</text>';
        
        // Adicionar legenda
        $legendas .= '<g>
            <rect x="' . $legenda_x . '" y="' . $legenda_y . '" width="12" height="12" fill="' . $cor_principal . '" stroke="#ddd" stroke-width="0.5"/>
            <text x="' . ($legenda_x + 16) . '" y="' . ($legenda_y + 9) . '" font-size="8" fill="#333">' . htmlspecialchars($labels[$key_principal]) . ' (' . round($percent, 1) . '%)</text>
        </g>';
    } else {
        // Múltiplos tipos, desenhar fatias normalmente
        foreach ($valores as $key => $valor) {
            if ($valor > 0) {
                $percent = ($valor / $total) * 100;
                $angulo = ($percent / 100) * 360;
                
                // Calcular coordenadas do arco
                $x1 = $cx + $raio * cos(deg2rad($current_angle));
                $y1 = $cy + $raio * sin(deg2rad($current_angle));
                $end_angle = $current_angle + $angulo;
                $x2 = $cx + $raio * cos(deg2rad($end_angle));
                $y2 = $cy + $raio * sin(deg2rad($end_angle));
                
                // Determinar se o arco é grande (>180 graus)
                $large_arc = $angulo > 180 ? 1 : 0;
                
                // Criar path para o arco (sem borda)
                $svg_paths .= '<path d="M ' . $cx . ' ' . $cy . ' L ' . $x1 . ' ' . $y1 . ' A ' . $raio . ' ' . $raio . ' 0 ' . $large_arc . ' 1 ' . $x2 . ' ' . $y2 . ' Z" fill="' . $cores[$key] . '" stroke="none"/>';
                
                // Calcular posição do texto dentro da fatia
                if ($percent > 5) { // Só mostrar se for maior que 5% para não sobrepor
                    $mid_angle = deg2rad($current_angle + $angulo / 2);
                    $text_distance = $raio * 0.5; // Meio do raio
                    $text_x = $cx + cos($mid_angle) * $text_distance;
                    $text_y = $cy + sin($mid_angle) * $text_distance;
                    
                    // Adicionar texto dentro da fatia
                    $textos_dentro .= '<text x="' . round($text_x) . '" y="' . round($text_y - 5) . '" text-anchor="middle" dominant-baseline="middle" font-size="11" font-weight="bold" fill="#fff">' . round($percent, 1) . '%</text>';
                    $textos_dentro .= '<text x="' . round($text_x) . '" y="' . round($text_y + 8) . '" text-anchor="middle" dominant-baseline="middle" font-size="9" fill="#fff">(' . $valor . ')</text>';
                }
                
                // Adicionar legenda
                $legendas .= '<g>
                    <rect x="' . $legenda_x . '" y="' . $legenda_y . '" width="12" height="12" fill="' . $cores[$key] . '" stroke="#ddd" stroke-width="0.5"/>
                    <text x="' . ($legenda_x + 16) . '" y="' . ($legenda_y + 9) . '" font-size="8" fill="#333">' . htmlspecialchars($labels[$key]) . ' (' . round($percent, 1) . '%)</text>
                </g>';
                
                $legenda_y += 16;
                $current_angle = $end_angle;
            }
        }
    }
    
    return '<svg width="240" height="200" viewBox="0 0 240 200" class="grafico-svg">
                ' . $svg_paths . '
                ' . $textos_dentro . '
                ' . $legendas . '
            </svg>';
}

function gerarCalendarioMesPDF($calendario, $inicioMes, $fimMes, $dataInicioPeriodo, $dataFimPeriodo) {
    $html = '<table class="calendario">
                <thead>
                    <tr>
                        <th>D</th><th>S</th><th>T</th><th>Q</th><th>Q</th><th>S</th><th>S</th>
                    </tr>
                </thead>
                <tbody>';
    
    // Criar matriz de calendário para o mês
    $inicio = new DateTime($inicioMes);
    $fim = new DateTime($fimMes);
    
    $primeiro_dia = new DateTime($inicio->format('Y-m-01'));
    $ultimo_dia = new DateTime($inicio->format('Y-m-t'));
    
    $dia_atual = clone $primeiro_dia;
    $dia_atual->modify('-' . $dia_atual->format('w') . ' days'); // Primeiro domingo
    
    $semanas = [];
    $max_semanas = 6;
    
    for ($s = 0; $s < $max_semanas; $s++) {
        $semana_atual = [];
        for ($d = 0; $d < 7; $d++) {
            $data_str = $dia_atual->format('Y-m-d');
            $semana_atual[] = [
                'data' => $data_str,
                'dia' => $dia_atual->format('d'),
                'mes_atual' => ($dia_atual->format('Y-m') == $inicio->format('Y-m'))
            ];
            $dia_atual->modify('+1 day');
        }
        $semanas[] = $semana_atual;
        
        // Parar se passou o último dia do mês
        if ($dia_atual->format('Y-m-d') > $ultimo_dia->format('Y-m-d')) {
            break;
        }
    }
    
    // Renderizar semanas
    foreach ($semanas as $semana) {
        $html .= '<tr>';
        foreach ($semana as $dia_info) {
            $data_str = $dia_info['data'];
            $dia = $dia_info['dia'];
            $mes_atual = $dia_info['mes_atual'];
            
            // Verificar status do dia
            $classe = 'dia-sem-culto';
            $simbolo = '';
            
            if ($mes_atual && $data_str >= $dataInicioPeriodo && $data_str <= $dataFimPeriodo) {
                $dia_data = $calendario[$data_str] ?? null;
                if ($dia_data) {
                    // Verificar justificativa aprovada primeiro (tem prioridade)
                    if (!empty($dia_data['justificados'])) {
                        $justAprovada = false;
                        foreach ($dia_data['justificados'] as $just) {
                            if (isset($just['tipo']) && $just['tipo'] === 'aprovada') {
                                $justAprovada = true;
                                break;
                            }
                        }
                        if ($justAprovada) {
                            $classe = 'dia-justificado-aprovada';
                            $simbolo = '✓';
                        } else {
                            $classe = 'dia-justificado';
                        }
                    } else if (!empty($dia_data['faltas'])) {
                        // Verificar se há justificativa rejeitada ou pendente
                        $temJustRejeitada = false;
                        $temJustPendente = false;
                        foreach ($dia_data['faltas'] as $falta) {
                            if (isset($falta['tipo'])) {
                                if ($falta['tipo'] === 'justificativa_rejeitada') {
                                    $temJustRejeitada = true;
                                } else if ($falta['tipo'] === 'justificativa_pendente') {
                                    $temJustPendente = true;
                                }
                            }
                        }
                        if ($temJustRejeitada) {
                            $classe = 'dia-falta-just-rejeitada';
                            $simbolo = '✗';
                        } else if ($temJustPendente) {
                            $classe = 'dia-falta-just-pendente';
                            $simbolo = '?';
                        } else {
                            $classe = 'dia-falta';
                        }
                    } else if (!empty($dia_data['presentes'])) {
                        $classe = 'dia-presente';
                    } else if (!empty($dia_data['atrasados'])) {
                        $classe = 'dia-atrasado';
                    }
                }
            }
            
            if (!$mes_atual) {
                $classe = 'dia-sem-culto';
                $simbolo = '';
            }
            
            $html .= '<td class="' . $classe . '" style="position: relative;"><span class="dia-numero">' . $dia . '</span>' . 
                     ($simbolo ? '<span style="position: absolute; top: 1px; right: 1px; font-size: 8px;">' . $simbolo . '</span>' : '') . 
                     '</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    return $html;
}

function gerarHTMLPresencas($usuarios_dados, $data_inicio, $data_fim) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { 
                font-family: Arial, sans-serif; 
                font-size: 9px; 
            }
            .header { 
                text-align: center; 
                margin-bottom: 20px;
                border-bottom: 2px solid #333;
                padding-bottom: 10px;
            }
            .header h1 {
                margin: 0;
                font-size: 16px;
            }
            .usuario-section {
                margin-bottom: 30px;
                page-break-inside: avoid;
                border: 1px solid #ddd;
                padding: 10px;
                margin-top: 15px;
            }
            .usuario-header {
                background-color: #0066cc;
                color: white;
                padding: 8px;
                margin: -10px -10px 10px -10px;
                font-weight: bold;
                font-size: 11px;
            }
            .stats-container {
                display: table;
                width: 100%;
                margin-bottom: 15px;
            }
            .stats-wrapper {
                display: table;
                width: 100%;
            }
            .stats-left {
                display: table-cell;
                width: 50%;
                vertical-align: top;
                padding-right: 10px;
            }
            .stats-right {
                display: table-cell;
                width: 50%;
                vertical-align: top;
                padding-left: 10px;
            }
            .stat-item {
                display: block;
                padding: 6px;
                text-align: center;
                border: 1px solid #ddd;
                margin-bottom: 5px;
                border-radius: 3px;
            }
            .stat-label {
                font-size: 7px;
                color: #666;
                margin-bottom: 3px;
            }
            .stat-value {
                font-size: 12px;
                font-weight: bold;
            }
            .stat-count {
                font-size: 7px;
                color: #666;
                margin-top: 2px;
            }
            .stat-presente {
                background-color: #d4edda;
                color: #155724;
            }
            .stat-atrasado {
                background-color: #cfe2ff;
                color: #084298;
            }
            .stat-falta {
                background-color: #f8d7da;
                color: #721c24;
            }
            .stat-justificado {
                background-color: #fff3cd;
                color: #856404;
            }
            .grafico-container {
                text-align: center;
                margin-bottom: 10px;
            }
            .grafico-svg {
                max-width: 200px;
                max-height: 200px;
            }
            .calendario {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }
            .calendario th {
                background-color: #f2f2f2;
                border: 1px solid #ddd;
                padding: 4px;
                text-align: center;
                font-size: 8px;
                font-weight: bold;
            }
            .calendario td {
                border: 1px solid #ddd;
                padding: 3px;
                text-align: center;
                font-size: 7px;
                width: 3.5%;
            }
            .dia-presente {
                background-color: #28a745;
                color: white;
            }
            .dia-atrasado {
                background-color: #007bff;
                color: white;
            }
            .dia-falta {
                background-color: #dc3545;
                color: white;
            }
            .dia-falta-just-rejeitada {
                background-color: #dc3545;
                color: white;
                border: 2px solid #721c24;
            }
            .dia-falta-just-pendente {
                background-color: #dc3545;
                color: white;
                border: 2px dashed #721c24;
            }
            .dia-justificado {
                background-color: #ffc107;
                color: #856404;
            }
            .dia-justificado-aprovada {
                background-color: #ffc107;
                color: #856404;
                border: 2px solid #856404;
            }
            .dia-sem-culto {
                background-color: #f8f9fa;
                color: #999;
            }
            .dia-numero {
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Relatório de Presenças</h1>
            <p>Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim)) . '</p>
        </div>';
    
    foreach ($usuarios_dados as $usuario) {
        $percentuais = $usuario['percentuais'] ?? [];
        $calendario = $usuario['calendario'] ?? [];
        
        $presentes = $usuario['presentes'] ?? 0;
        $atrasados = $usuario['atrasados'] ?? 0;
        $faltas = $usuario['faltas'] ?? 0;
        $justificados = $usuario['justificados'] ?? 0;
        
        $html .= '
        <div class="usuario-section">
            <div class="usuario-header">' . htmlspecialchars($usuario['nome_usuario']) . '</div>
            
            <div class="grafico-container" style="text-align: center; margin-bottom: 15px;">
                ' . gerarGraficoPizzaSVG($presentes, $atrasados, $faltas, $justificados) . '
            </div>';
        
        // Calcular número de meses entre as datas
        $inicio = new DateTime($data_inicio);
        $fim = new DateTime($data_fim);
        $mesesDiferenca = ($fim->format('Y') - $inicio->format('Y')) * 12 + ($fim->format('m') - $inicio->format('m')) + 1;
        
        // Se o período for maior que 1 mês, dividir por meses
        if ($mesesDiferenca > 1) {
            $dataAtual = clone $inicio;
            
            while ($dataAtual <= $fim) {
                // Calcular início e fim do mês atual
                $inicioMes = new DateTime($dataAtual->format('Y-m-01'));
                $fimMes = new DateTime($dataAtual->format('Y-m-t'));
                
                // Ajustar para não ultrapassar as datas do período
                if ($inicioMes < $inicio) {
                    $inicioMes = clone $inicio;
                }
                if ($fimMes > $fim) {
                    $fimMes = clone $fim;
                }
                
                // Nome do mês em português
                $mesesPT = [
                    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
                ];
                $mes = (int)$dataAtual->format('m');
                $ano = $dataAtual->format('Y');
                $nomeMes = $mesesPT[$mes] . ' de ' . $ano;
                
                $html .= '<div style="margin-bottom: 20px; page-break-inside: avoid;">
                    <h6 style="margin-bottom: 10px; font-size: 14px; font-weight: bold;">' . $nomeMes . '</h6>
                    ' . gerarCalendarioMesPDF($calendario, $inicioMes->format('Y-m-d'), $fimMes->format('Y-m-d'), $data_inicio, $data_fim) . '
                </div>';
                
                // Avançar para o próximo mês
                $dataAtual->modify('first day of next month');
            }
        } else {
            // Período de até 1 mês, gerar calendário único
            $html .= gerarCalendarioMesPDF($calendario, $data_inicio, $data_fim, $data_inicio, $data_fim);
        }
        
        $html .= '
        </div>';
    }
    
    $html .= '
    </body>
    </html>';
    
    return $html;
}

function buscarDadosRelatorio($tipo, $data_inicio, $data_fim, $usuario_id, $conn) {
    $dados = [];
    
    switch($tipo) {
        case 'faltas':
            // Buscar faltas - usuários que não tiveram presença em datas de culto
            $sql_datas = "SELECT DISTINCT data FROM presencas_culto WHERE data BETWEEN ? AND ? ORDER BY data";
            $stmt_datas = $conn->prepare($sql_datas);
            $stmt_datas->bind_param("ss", $data_inicio, $data_fim);
            $stmt_datas->execute();
            $result_datas = $stmt_datas->get_result();
            $datas_culto = [];
            while ($row = $result_datas->fetch_assoc()) {
                $datas_culto[] = $row['data'];
            }
            
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
            
            while ($usuario = $result_usuarios->fetch_assoc()) {
                foreach ($datas_culto as $data) {
                    $sql_presenca = "SELECT id FROM presencas_culto WHERE id_usuario = ? AND data = ?";
                    $stmt_presenca = $conn->prepare($sql_presenca);
                    $stmt_presenca->bind_param("is", $usuario['id'], $data);
                    $stmt_presenca->execute();
                    $result_presenca = $stmt_presenca->get_result();
                    
                    if ($result_presenca->num_rows == 0) {
                        $sql_just = "SELECT motivo, status FROM justificativas_culto WHERE id_usuario = ? AND data_falta = ?";
                        $stmt_just = $conn->prepare($sql_just);
                        $stmt_just->bind_param("is", $usuario['id'], $data);
                        $stmt_just->execute();
                        $result_just = $stmt_just->get_result();
                        $justificativa = $result_just->fetch_assoc();
                        
                        $dados[] = [
                            'data' => $data,
                            'nome_usuario' => $usuario['nome'],
                            'justificada' => $justificativa !== null,
                            'motivo' => $justificativa['motivo'] ?? null
                        ];
                    }
                }
            }
            break;
            
        case 'justificativas':
            $sql = "SELECT 
                        j.data_falta,
                        j.motivo,
                        j.status,
                        u.nome as nome_usuario
                    FROM justificativas_culto j
                    INNER JOIN usuarios u ON j.id_usuario = u.id
                    WHERE j.data_falta BETWEEN ? AND ?";
            
            if ($usuario_id) {
                $sql .= " AND j.id_usuario = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $data_inicio, $data_fim, $usuario_id);
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $data_inicio, $data_fim);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $dados[] = $row;
            }
            break;
    }
    
    return $dados;
}

function gerarHTMLRelatorio($tipo, $dados, $data_inicio, $data_fim) {
    $titulos = [
        'presencas' => 'Relatório de Presenças',
        'faltas' => 'Relatório de Faltas',
        'justificativas' => 'Relatório de Justificativas'
    ];
    
    $titulo = $titulos[$tipo] ?? 'Relatório de Culto';
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; font-size: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { text-align: center; margin-bottom: 20px; }
            .total { font-weight: bold; background-color: #f9f9f9; }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>' . htmlspecialchars($titulo) . '</h2>
            <p>Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim)) . '</p>
        </div>
        <table>
            <thead>
                <tr>';
    
    switch($tipo) {
        case 'faltas':
            $html .= '<th>Data</th><th>Usuário</th><th>Justificada</th><th>Motivo</th>';
            break;
        case 'justificativas':
            $html .= '<th>Data Falta</th><th>Usuário</th><th>Motivo</th><th>Status</th>';
            break;
    }
    
    $html .= '
                </tr>
            </thead>
            <tbody>';
    
    foreach ($dados as $row) {
        $html .= '<tr>';
        switch($tipo) {
            case 'faltas':
                $html .= '<td>' . date('d/m/Y', strtotime($row['data'])) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['nome_usuario']) . '</td>';
                $html .= '<td>' . ($row['justificada'] ? 'Sim' : 'Não') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['motivo'] ?? '-') . '</td>';
                break;
            case 'justificativas':
                $html .= '<td>' . date('d/m/Y', strtotime($row['data_falta'])) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['nome_usuario']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['motivo']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['status']) . '</td>';
                break;
        }
        $html .= '</tr>';
    }
    
    $html .= '
            </tbody>
        </table>
    </body>
    </html>';
    
    return $html;
}
?>
