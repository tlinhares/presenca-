<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

// Aceita JSON (mobile) ou form-data (web)
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($content_type, 'application/json') !== false) {
    $_POST = json_decode(file_get_contents('php://input'), true) ?: [];
}

if (!isset($_SESSION['usuario_id'])) {
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
        exit;
    }
}

$id_usuario = $_SESSION['usuario_id'] ?? '';
if (empty($id_usuario)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não logado']);
    exit;
}

$data_inicio = $_POST['data_inicio'] ?? '';
$data_fim = $_POST['data_fim'] ?? '';
$dependentes = $_POST['dependentes'] ?? [];
$fora_do_horario = $_POST['fora_do_horario'] ?? false;
if (is_string($fora_do_horario)) {
    $fora_do_horario = in_array(strtolower($fora_do_horario), ['true', '1', 'sim']);
}

if (empty($data_inicio) || empty($data_fim)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Datas não fornecidas']);
    exit;
}

// Validar datas
$dataInicio = DateTime::createFromFormat('Y-m-d', $data_inicio);
$dataFim = DateTime::createFromFormat('Y-m-d', $data_fim);

if (!$dataInicio || !$dataFim) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Formato de data inválido']);
    exit;
}

if ($dataInicio > $dataFim) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Data de início deve ser anterior à data de fim']);
    exit;
}

// Buscar configurações do sistema
$config = [];
$result = $conn->query("SELECT chave, valor FROM configuracoes");
while ($row = $result->fetch_assoc()) {
    $config[$row['chave']] = $row['valor'];
}

$valor_refeicao_config = floatval($config['valor_refeicao'] ?? 0);
$valor_fora_horario = floatval($config['valor_fora_horario'] ?? 30);
$hora_limite = $config['hora_limite'] ?? '10:00';

// Buscar valor do grupo_valor do usuário (igual ao reservar.php)
$valor_grupo = null;
$stmt_grupo = $conn->prepare("SELECT gv.valor FROM usuarios u LEFT JOIN grupo_valor gv ON u.id_valor = gv.id WHERE u.id = ?");
if ($stmt_grupo) {
    $stmt_grupo->bind_param("i", $id_usuario);
    $stmt_grupo->execute();
    $stmt_grupo->bind_result($vg);
    if ($stmt_grupo->fetch() && $vg !== null) {
        $valor_grupo = floatval($vg);
    }
    $stmt_grupo->close();
}
$valor_normal = ($valor_grupo !== null) ? $valor_grupo : $valor_refeicao_config;

try {
    $sucessos = 0;
    $falhas = 0;
    $erros = [];
    $hoje_str = (new DateTime())->format('Y-m-d');
    
    // Gerar array de datas (pular fins de semana)
    $datas = [];
    $current = clone $dataInicio;
    while ($current <= $dataFim) {
        $diaSemana = $current->format('N');
        if ($diaSemana < 6) {
            $datas[] = $current->format('Y-m-d');
        }
        $current->add(new DateInterval('P1D'));
    }
    
    // Pré-buscar dias fechados no intervalo (evita N queries dentro do loop)
    $dias_fechados = [];
    $stmt_df = $conn->prepare("SELECT data, motivo FROM dias_fechado WHERE data BETWEEN ? AND ? AND ativo = 1");
    if ($stmt_df) {
        $di = $dataInicio->format('Y-m-d');
        $df = $dataFim->format('Y-m-d');
        $stmt_df->bind_param("ss", $di, $df);
        $stmt_df->execute();
        $res_df = $stmt_df->get_result();
        while ($r = $res_df->fetch_assoc()) {
            $dias_fechados[$r['data']] = trim($r['motivo'] ?? '');
        }
        $stmt_df->close();
    }

    foreach ($datas as $data) {
        $eh_hoje = ($data === $hoje_str);

        // Bloquear se o refeitório está fechado nesta data
        if (isset($dias_fechados[$data])) {
            $falhas++;
            $motivo_df = $dias_fechados[$data];
            $erros[] = "Refeitório fechado em {$data}" . ($motivo_df !== '' ? " ({$motivo_df})" : '');
            continue;
        }

        // Para o dia atual: verificar horário limite (só bloqueia se NÃO aceitou fora_do_horario)
        if ($eh_hoje) {
            $horaAtual = (new DateTime())->format('H:i');
            if ($horaAtual > $hora_limite && !$fora_do_horario) {
                $falhas++;
                $erros[] = "Horário limite ultrapassado para {$data}";
                continue;
            }
        }
        
        // Determinar valor da refeição para esta data
        // Fora do horário aplica-se APENAS ao dia atual
        $aplicar_fora = ($eh_hoje && $fora_do_horario && (new DateTime())->format('H:i') > $hora_limite);
        $valor_dia = $aplicar_fora ? $valor_fora_horario : $valor_normal;
        
        // Se não há dependentes, criar reserva principal para o usuário
        if (empty($dependentes) || !is_array($dependentes)) {
            $stmt = $conn->prepare("SELECT id FROM reservas_almoco WHERE id_usuario = ? AND data = ?");
            $stmt->bind_param("is", $id_usuario, $data);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $falhas++;
                $erros[] = "Já existe reserva para {$data}";
                $stmt->close();
                continue;
            }
            $stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO reservas_almoco (id_usuario, data, valor_refeicao) VALUES (?, ?, ?)");
            if (!$stmt) {
                $falhas++;
                $erros[] = "Erro ao preparar reserva principal para {$data}: " . $conn->error;
                continue;
            }
            
            $stmt->bind_param("isd", $id_usuario, $data, $valor_dia);
            
            if ($stmt->execute()) {
                $sucessos++;
            } else {
                $falhas++;
                $erros[] = "Erro ao inserir reserva principal para {$data}: " . $stmt->error;
            }
            $stmt->close();
        }
        
        // Inserir reservas para dependentes
        if (!empty($dependentes) && is_array($dependentes)) {
            $dependentesProcessados = 0;
            foreach ($dependentes as $dependente_id) {
                $stmt = $conn->prepare("SELECT id, cobrar, nascimento FROM dependentes WHERE id = ? AND id_usuario = ? AND ativo = 1");
                $stmt->bind_param("ii", $dependente_id, $id_usuario);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $dependente = $result->fetch_assoc();
                    $stmt->close();

                    $cobrar = intval($dependente['cobrar']);
                    // Defesa em profundidade: recalcula pela idade real.
                    if (!empty($dependente['nascimento'])) {
                        try {
                            $idade_dep = (new DateTime())->diff(new DateTime($dependente['nascimento']))->y;
                            $cobrar = ($idade_dep <= 12) ? 1 : 0;
                        } catch (Exception $e) { /* fallback silencioso */ }
                    }
                    
                    // Verificar duplicata: mesmo dependente + mesma data
                    $stmt = $conn->prepare("SELECT id FROM reservas_adicionais WHERE id_usuario = ? AND id_dependente = ? AND data = ?");
                    $stmt->bind_param("iis", $id_usuario, $dependente_id, $data);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows == 0) {
                        $stmt->close();
                        
                        // cobrar=1 significa NÃO cobrar (menor), cobrar=0 significa COBRAR
                        $valor_dependente = ($cobrar == 1) ? 0.00 : $valor_dia;
                        
                        $stmt = $conn->prepare("INSERT INTO reservas_adicionais 
                            (id_usuario, id_dependente, data, quantidade, detalhe, tipo, data_cadastro, valor_refeicao, valor_marmitex) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                        if ($stmt) {
                            $quantidade = 1;
                            $detalhe = 'Reserva múltipla';
                            $tipo = 'presencial';
                            $valor_marmitex = 0.00;
                            
                            $stmt->bind_param("iissssdd", $id_usuario, $dependente_id, $data, $quantidade, $detalhe, $tipo, $valor_dependente, $valor_marmitex);
                            if ($stmt->execute()) {
                                $dependentesProcessados++;
                            } else {
                                error_log("Erro ao inserir reserva adicional: " . $stmt->error);
                            }
                            $stmt->close();
                        }
                    } else {
                        $stmt->close();
                    }
                } else {
                    $stmt->close();
                }
            }
            
            if ($dependentesProcessados > 0) {
                $sucessos += $dependentesProcessados;
            }
        }
    }
    
    $mensagem = "Reservas processadas: {$sucessos} sucessos, {$falhas} falhas";
    if (!empty($erros)) {
        $mensagem .= ". Erros: " . implode('; ', $erros);
    }
    
    // Enviar notificação se habilitada e houver sucessos
    if ($sucessos > 0) {
        require_once __DIR__ . '/../notificacao/enviar_notificacao_reserva.php';
        $tipo_reserva = empty($dependentes) || !is_array($dependentes) ? 'propria' : 'adicional';
        $tipo_texto = $tipo_reserva;
        $horario_atual = date('H:i');
        $dados_notificacao = [
            'data_inicio' => date('d/m/Y', strtotime($data_inicio)),
            'data_fim' => date('d/m/Y', strtotime($data_fim)),
            'horario' => $horario_atual,
            'total_reservas' => $sucessos,
            'tipo' => $tipo_texto
        ];
        enviarNotificacaoReserva($id_usuario, 'multipla', $dados_notificacao, $conn);
    }
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => $mensagem,
        'sucessos' => $sucessos,
        'falhas' => $falhas,
        'erros' => $erros
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro: ' . $e->getMessage()]);
}

$conn->close();
?>
