<?php
/**
 * API para buscar detalhes completos de presença de um dia específico
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once '../../auth/verifica_sessao.php';
require_once '../../api/conexao.php';

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$data = $_GET['data'] ?? '';

if (!$usuario_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

if (empty($data)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Data não informada']);
    exit;
}

try {
    if (!$conn) {
        throw new Exception('Erro de conexão com o banco de dados');
    }
    
    // Validar formato da data
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    if (!$data_obj) {
        throw new Exception('Data inválida');
    }
    
    $data_formatada = $data_obj->format('d/m/Y');
    $dia_semana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'][$data_obj->format('w')];
    
    // Buscar presença do usuário neste dia
    $sql_presenca = "
        SELECT 
            pc.status,
            pc.horario_confirmacao,
            pc.tipo_confirmacao,
            pc.data as data_registro
        FROM presencas_culto pc
        WHERE pc.id_usuario = ? 
        AND DATE(pc.data) = ?
        LIMIT 1
    ";
    
    $stmt_presenca = $conn->prepare($sql_presenca);
    if (!$stmt_presenca) {
        throw new Exception('Erro ao preparar consulta: ' . $conn->error);
    }
    
    $stmt_presenca->bind_param("is", $usuario_id, $data);
    $stmt_presenca->execute();
    $result_presenca = $stmt_presenca->get_result();
    $presenca = $result_presenca->fetch_assoc();
    $stmt_presenca->close();
    
    // Buscar justificativa deste dia
    $sql_justificativa = "
        SELECT 
            j.motivo,
            j.observacoes,
            j.status,
            j.data_cadastro,
            j.data_aprovacao,
            j.observacoes_admin
        FROM justificativas_culto j
        WHERE j.id_usuario = ? 
        AND DATE(j.data_falta) = ?
        LIMIT 1
    ";
    
    $stmt_justificativa = $conn->prepare($sql_justificativa);
    if (!$stmt_justificativa) {
        throw new Exception('Erro ao preparar consulta de justificativa: ' . $conn->error);
    }
    
    $stmt_justificativa->bind_param("is", $usuario_id, $data);
    $stmt_justificativa->execute();
    $result_justificativa = $stmt_justificativa->get_result();
    $justificativa = $result_justificativa->fetch_assoc();
    $stmt_justificativa->close();
    
    // Verificar se é dia de culto configurado
    $sql_config = "SELECT valor FROM configuracoes_culto WHERE chave = 'dias_semana'";
    $stmt_config = $conn->prepare($sql_config);
    $stmt_config->execute();
    $result_config = $stmt_config->get_result();
    
    $dias_semana_config = '1,2,3,4,5';
    if ($result_config->num_rows > 0) {
        $config = $result_config->fetch_assoc();
        $dias_semana_config = $config['valor'];
    }
    $stmt_config->close();
    
    $dias_culto_semana = array_map('trim', explode(',', $dias_semana_config));
    $dia_semana_numero = $data_obj->format('N');
    $eh_dia_culto = in_array($dia_semana_numero, $dias_culto_semana);
    
    // Verificar se houve culto neste dia (pelo menos uma presença de qualquer usuário)
    $sql_houve_culto = "
        SELECT COUNT(*) as total
        FROM presencas_culto 
        WHERE DATE(data) = ?
        AND status IN ('presente', 'atrasado')
        LIMIT 1
    ";
    
    $stmt_houve_culto = $conn->prepare($sql_houve_culto);
    $stmt_houve_culto->bind_param("s", $data);
    $stmt_houve_culto->execute();
    $result_houve_culto = $stmt_houve_culto->get_result();
    $houve_culto = $result_houve_culto->fetch_assoc()['total'] > 0;
    $stmt_houve_culto->close();
    
    // Montar resposta
    $detalhes = [
        'data' => $data,
        'data_formatada' => $data_formatada,
        'dia_semana' => $dia_semana,
        'eh_dia_culto' => $eh_dia_culto,
        'houve_culto' => $houve_culto,
        'presenca' => null,
        'justificativa' => null,
        'status_geral' => 'sem_dados'
    ];
    
    // Processar justificativa (tem prioridade sobre presença)
    if ($justificativa) {
        $detalhes['justificativa'] = [
            'motivo' => $justificativa['motivo'] ?? '',
            'observacoes' => $justificativa['observacoes'] ?? '',
            'status' => $justificativa['status'] ?? '',
            'data_cadastro' => $justificativa['data_cadastro'] ?? null,
            'data_aprovacao' => $justificativa['data_aprovacao'] ?? null,
            'observacoes_admin' => $justificativa['observacoes_admin'] ?? ''
        ];
        
        switch ($justificativa['status']) {
            case 'aprovada':
                $detalhes['status_geral'] = 'justificativa_aceita';
                break;
            case 'pendente':
                $detalhes['status_geral'] = 'justificativa_pendente';
                break;
            case 'rejeitada':
                $detalhes['status_geral'] = 'justificativa_rejeitada';
                break;
        }
    }
    
    // Processar presença (se não tem justificativa ou se tem presença mesmo com justificativa)
    if ($presenca) {
        $detalhes['presenca'] = [
            'status' => $presenca['status'] ?? '',
            'horario' => $presenca['horario_confirmacao'] ?? null,
            'tipo_confirmacao' => $presenca['tipo_confirmacao'] ?? '',
            'data_registro' => $presenca['data_registro'] ?? null
        ];
        
        // Se não tem justificativa, usar status da presença
        if (!$justificativa) {
            $detalhes['status_geral'] = $presenca['status'] ?? 'sem_dados';
        }
    } else {
        // Não tem presença registrada
        if (!$justificativa && $eh_dia_culto && $houve_culto) {
            $detalhes['status_geral'] = 'falta';
        } elseif (!$eh_dia_culto) {
            $detalhes['status_geral'] = 'nao_culto';
        } elseif (!$houve_culto) {
            $detalhes['status_geral'] = 'sem_culto';
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'detalhes' => $detalhes
    ]);
    
} catch (Exception $e) {
    error_log("Erro em detalhes_presenca_dia.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar detalhes: ' . $e->getMessage()
    ]);
}
?>

