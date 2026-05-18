<?php
/**
 * Script para testar a consistência entre calendário e justificativas
 * Execute este script para verificar se os dias com falta estão batendo
 */

session_start();
require_once 'auth/verifica_sessao.php';
require_once 'api/conexao.php';

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');

echo "<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Teste de Consistência - Faltas de Culto</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
<div class='container mt-4'>
    <h2>🔍 Teste de Consistência - Faltas de Culto</h2>
    <p class='text-muted'>Verificando consistência entre calendário e sistema de justificativas</p>
";

try {
    if (!$conn) {
        throw new Exception('Erro de conexão com o banco de dados');
    }
    
    $conn->set_charset("utf8");
    
    // Buscar dados do calendário (mesma lógica do resumo.php)
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
    
    // Buscar justificativas
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
    
    // Buscar configuração de dias da semana
    $sql_config = "SELECT valor FROM configuracoes_culto WHERE chave = 'dias_semana'";
    $stmt_config = $conn->prepare($sql_config);
    $stmt_config->execute();
    $resultado_config = $stmt_config->get_result();
    
    $dias_semana_config = '1,2,3,4,5';
    if ($resultado_config->num_rows > 0) {
        $config = $resultado_config->fetch_assoc();
        $dias_semana_config = $config['valor'];
    }
    
    $dias_culto_semana = array_map('trim', explode(',', $dias_semana_config));
    
    // Buscar dias onde houve culto
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
    
    // Gerar dados para o mês
    $dados_calendario = [];
    $dados_justificativas = [];
    
    $dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
    
    for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
        $data_completa = sprintf('%s-%02d-%02d', $ano, $mes, $dia);
        
        $data_obj = new DateTime($data_completa);
        $dia_semana = $data_obj->format('N');
        
        // LÓGICA DO CALENDÁRIO
        $status_calendario = 'nao_culto';
        if (in_array($dia_semana, $dias_culto_semana)) {
            if (in_array($data_completa, $dias_com_culto)) {
                if (isset($presencas[$data_completa])) {
                    $status_calendario = $presencas[$data_completa]['status'];
                } else {
                    $status_calendario = 'falta';
                }
                
                if (isset($justificativas[$data_completa])) {
                    $justificativa = $justificativas[$data_completa];
                    if ($justificativa['status'] === 'aprovada') {
                        $status_calendario = 'justificativa_aceita';
                    } else if ($justificativa['status'] === 'pendente') {
                        $status_calendario = 'justificativa_pendente';
                    }
                }
            } else {
                $status_calendario = 'sem_culto';
            }
        }
        
        // LÓGICA DAS JUSTIFICATIVAS (CORRIGIDA)
        $status_justificativas = 'nao_culto';
        if (in_array($dia_semana, $dias_culto_semana)) {
            if (in_array($data_completa, $dias_com_culto)) {
                // Houve culto - verificar se o usuário tem presença
                $tem_presenca = isset($presencas[$data_completa]);
                $status_presenca = $tem_presenca ? $presencas[$data_completa]['status'] : null;
                
                // Determinar se é falta baseado na lógica corrigida:
                // - Se não tem presença registrada = falta implícita
                // - Se tem presença com status 'falta' = falta explícita
                // - Se tem presença com status 'presente' ou 'atrasado' = não é falta
                $eh_falta = false;
                
                if (!$tem_presenca) {
                    // Usuário não tem presença registrada = falta implícita
                    $eh_falta = true;
                } elseif ($status_presenca === 'falta') {
                    // Usuário tem presença registrada com status 'falta' = falta explícita
                    $eh_falta = true;
                }
                
                if ($eh_falta) {
                    $status_justificativas = 'falta';
                    
                    // Verificar se tem justificativa
                    if (isset($justificativas[$data_completa])) {
                        $justificativa = $justificativas[$data_completa];
                        if ($justificativa['status'] === 'aprovada') {
                            $status_justificativas = 'justificativa_aceita';
                        } else if ($justificativa['status'] === 'pendente') {
                            $status_justificativas = 'justificativa_pendente';
                        }
                    }
                }
            }
        }
        
        $dados_calendario[$data_completa] = $status_calendario;
        $dados_justificativas[$data_completa] = $status_justificativas;
    }
    
    // Comparar resultados
    $inconsistencias = [];
    $total_dias = 0;
    $dias_falta_calendario = 0;
    $dias_falta_justificativas = 0;
    
    foreach ($dados_calendario as $data => $status_cal) {
        $status_just = $dados_justificativas[$data];
        
        if (in_array($status_cal, ['falta', 'justificativa_pendente', 'justificativa_aceita'])) {
            $total_dias++;
            if ($status_cal === 'falta') $dias_falta_calendario++;
        }
        
        if (in_array($status_just, ['falta', 'justificativa_pendente', 'justificativa_aceita'])) {
            if ($status_just === 'falta') $dias_falta_justificativas++;
        }
        
        if ($status_cal !== $status_just) {
            $inconsistencias[] = [
                'data' => $data,
                'calendario' => $status_cal,
                'justificativas' => $status_just
            ];
        }
    }
    
    // Exibir resultados
    echo "<div class='row'>
        <div class='col-md-6'>
            <div class='card'>
                <div class='card-header'>
                    <h5>📊 Estatísticas do Mês</h5>
                </div>
                <div class='card-body'>
                    <p><strong>Total de dias com culto:</strong> $total_dias</p>
                    <p><strong>Faltas no calendário:</strong> $dias_falta_calendario</p>
                    <p><strong>Faltas nas justificativas:</strong> $dias_falta_justificativas</p>
                    <p><strong>Inconsistências:</strong> " . count($inconsistencias) . "</p>
                </div>
            </div>
        </div>
        <div class='col-md-6'>
            <div class='card'>
                <div class='card-header'>
                    <h5>⚙️ Configurações</h5>
                </div>
                <div class='card-body'>
                    <p><strong>Dias de culto:</strong> " . implode(', ', $dias_culto_semana) . "</p>
                    <p><strong>Dias com culto no mês:</strong> " . count($dias_com_culto) . "</p>
                    <p><strong>Presenças do usuário:</strong> " . count($presencas) . "</p>
                    <p><strong>Justificativas do usuário:</strong> " . count($justificativas) . "</p>
                </div>
            </div>
        </div>
    </div>";
    
    if (count($inconsistencias) > 0) {
        echo "<div class='alert alert-warning mt-3'>
            <h5>⚠️ Inconsistências Encontradas</h5>
            <p>Os seguintes dias apresentam diferenças entre o calendário e o sistema de justificativas:</p>
        </div>";
        
        echo "<div class='table-responsive'>
            <table class='table table-striped'>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Calendário</th>
                        <th>Justificativas</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>";
        
        foreach ($inconsistencias as $inc) {
            $status_class = 'text-warning';
            $status_text = 'Inconsistente';
            
            if ($inc['calendario'] === 'falta' && $inc['justificativas'] !== 'falta') {
                $status_class = 'text-danger';
                $status_text = 'Falta não aparece nas justificativas';
            } elseif ($inc['calendario'] !== 'falta' && $inc['justificativas'] === 'falta') {
                $status_class = 'text-info';
                $status_text = 'Falta aparece apenas nas justificativas';
            }
            
            echo "<tr>
                <td>" . date('d/m/Y', strtotime($inc['data'])) . "</td>
                <td><span class='badge bg-secondary'>" . ucfirst(str_replace('_', ' ', $inc['calendario'])) . "</span></td>
                <td><span class='badge bg-secondary'>" . ucfirst(str_replace('_', ' ', $inc['justificativas'])) . "</span></td>
                <td class='$status_class'>$status_text</td>
            </tr>";
        }
        
        echo "</tbody></table></div>";
    } else {
        echo "<div class='alert alert-success mt-3'>
            <h5>✅ Consistência Perfeita!</h5>
            <p>Não foram encontradas inconsistências entre o calendário e o sistema de justificativas.</p>
        </div>";
    }
    
    // Exibir detalhes dos dias de culto
    echo "<div class='card mt-3'>
        <div class='card-header'>
            <h5>📅 Detalhes dos Dias de Culto</h5>
        </div>
        <div class='card-body'>
            <div class='table-responsive'>
                <table class='table table-sm'>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Dia</th>
                            <th>Calendário</th>
                            <th>Justificativas</th>
                            <th>Presença</th>
                            <th>Justificativa</th>
                        </tr>
                    </thead>
                    <tbody>";
    
    foreach ($dados_calendario as $data => $status_cal) {
        $data_obj = new DateTime($data);
        $dia_semana = $data_obj->format('N');
        
        if (in_array($dia_semana, $dias_culto_semana)) {
            $status_just = $dados_justificativas[$data];
            $presenca = isset($presencas[$data]) ? $presencas[$data]['status'] : 'N/A';
            $justificativa = isset($justificativas[$data]) ? $justificativas[$data]['status'] : 'N/A';
            
            $row_class = '';
            if ($status_cal !== $status_just) {
                $row_class = 'table-warning';
            }
            
            echo "<tr class='$row_class'>
                <td>" . date('d/m/Y', strtotime($data)) . "</td>
                <td>" . ['', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'][$dia_semana] . "</td>
                <td><span class='badge bg-secondary'>" . ucfirst(str_replace('_', ' ', $status_cal)) . "</span></td>
                <td><span class='badge bg-secondary'>" . ucfirst(str_replace('_', ' ', $status_just)) . "</span></td>
                <td><span class='badge bg-info'>" . ucfirst($presenca) . "</span></td>
                <td><span class='badge bg-warning'>" . ucfirst($justificativa) . "</span></td>
            </tr>";
        }
    }
    
    echo "</tbody></table></div></div></div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
        <h5>❌ Erro</h5>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
    </div>";
}

echo "</div></body></html>";
?>
