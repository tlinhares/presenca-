<?php
/**
 * Script de debug para investigar por que não aparecem faltas do mês 10
 */

session_start();
require_once 'auth/verifica_sessao.php';
require_once 'api/conexao.php';

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$mes = 10; // Mês 10 (outubro)
$ano = date('Y'); // Ano atual (2025)

echo "<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Debug - Faltas Mês 10</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
<div class='container mt-4'>
    <h2>🔍 Debug - Faltas do Mês 10 (Outubro 2024)</h2>
    <p class='text-muted'>Investigando por que não aparecem faltas do mês 10</p>
";

try {
    if (!$conn) {
        throw new Exception('Erro de conexão com o banco de dados');
    }
    
    $conn->set_charset("utf8");
    
    echo "<div class='row'>
        <div class='col-12'>
            <div class='card'>
                <div class='card-header'>
                    <h5>📊 Dados do Usuário</h5>
                </div>
                <div class='card-body'>
                    <p><strong>ID do Usuário:</strong> $usuario_id</p>
                    <p><strong>Mês Analisado:</strong> $mes (Outubro)</p>
                    <p><strong>Ano Analisado:</strong> $ano</p>
                </div>
            </div>
        </div>
    </div>";
    
    // 1. Verificar presenças do usuário no mês 10
    $sql_presencas = "
        SELECT DATE(data) as data_presenca,
               status,
               horario_confirmacao
        FROM presencas_culto 
        WHERE id_usuario = ?
        AND YEAR(data) = ? AND MONTH(data) = ?
        ORDER BY data DESC
    ";
    
    $stmt_presencas = $conn->prepare($sql_presencas);
    $stmt_presencas->bind_param("iii", $usuario_id, $ano, $mes);
    $stmt_presencas->execute();
    $result_presencas = $stmt_presencas->get_result();
    
    $presencas = [];
    while ($row = $result_presencas->fetch_assoc()) {
        $presencas[$row['data_presenca']] = [
            'status' => $row['status'],
            'horario' => $row['horario_confirmacao']
        ];
    }
    
    echo "<div class='row mt-3'>
        <div class='col-12'>
            <div class='card'>
                <div class='card-header'>
                    <h5>👤 Presenças do Usuário no Mês 10</h5>
                </div>
                <div class='card-body'>
                    <p><strong>Total de presenças encontradas:</strong> " . count($presencas) . "</p>";
    
    if (count($presencas) > 0) {
        echo "<table class='table table-sm'>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Status</th>
                    <th>Horário</th>
                </tr>
            </thead>
            <tbody>";
        
        foreach ($presencas as $data => $presenca) {
            echo "<tr>
                <td>$data</td>
                <td><span class='badge bg-info'>{$presenca['status']}</span></td>
                <td>{$presenca['horario']}</td>
            </tr>";
        }
        
        echo "</tbody></table>";
    } else {
        echo "<p class='text-muted'>Nenhuma presença encontrada para o usuário no mês 10</p>";
    }
    
    echo "</div></div></div></div>";
    
    // 2. Verificar justificativas do usuário no mês 10
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
    
    echo "<div class='row mt-3'>
        <div class='col-12'>
            <div class='card'>
                <div class='card-header'>
                    <h5>📝 Justificativas do Usuário no Mês 10</h5>
                </div>
                <div class='card-body'>
                    <p><strong>Total de justificativas encontradas:</strong> " . count($justificativas) . "</p>";
    
    if (count($justificativas) > 0) {
        echo "<table class='table table-sm'>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Motivo</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>";
        
        foreach ($justificativas as $data => $justificativa) {
            echo "<tr>
                <td>$data</td>
                <td>{$justificativa['motivo']}</td>
                <td><span class='badge bg-warning'>{$justificativa['status']}</span></td>
            </tr>";
        }
        
        echo "</tbody></table>";
    } else {
        echo "<p class='text-muted'>Nenhuma justificativa encontrada para o usuário no mês 10</p>";
    }
    
    echo "</div></div></div></div>";
    
    // 3. Verificar configuração de dias da semana
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
    
    echo "<div class='row mt-3'>
        <div class='col-12'>
            <div class='card'>
                <div class='card-header'>
                    <h5>⚙️ Configuração de Culto</h5>
                </div>
                <div class='card-body'>
                    <p><strong>Dias da semana configurados:</strong> " . implode(', ', $dias_culto_semana) . "</p>
                    <p><strong>Dias configurados:</strong> ";
    
    $nomes_dias = ['', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
    foreach ($dias_culto_semana as $dia) {
        echo $nomes_dias[$dia] . " ";
    }
    
    echo "</p></div></div></div></div>";
    
    // 4. Verificar dias onde houve culto no mês 10
    $sql_dias_culto = "
        SELECT DISTINCT DATE(data) as data_culto
        FROM presencas_culto 
        WHERE status IN ('presente', 'atrasado')
        AND YEAR(data) = ? AND MONTH(data) = ?
        ORDER BY data_culto
    ";
    
    $stmt_dias_culto = $conn->prepare($sql_dias_culto);
    $stmt_dias_culto->bind_param("ii", $ano, $mes);
    $stmt_dias_culto->execute();
    $resultado_dias_culto = $stmt_dias_culto->get_result();
    
    $dias_com_culto = [];
    while ($dia = $resultado_dias_culto->fetch_assoc()) {
        $dias_com_culto[] = $dia['data_culto'];
    }
    
    echo "<div class='row mt-3'>
        <div class='col-12'>
            <div class='card'>
                <div class='card-header'>
                    <h5>⛪ Dias com Culto no Mês 10</h5>
                </div>
                <div class='card-body'>
                    <p><strong>Total de dias com culto:</strong> " . count($dias_com_culto) . "</p>";
    
    if (count($dias_com_culto) > 0) {
        echo "<p><strong>Dias com culto:</strong> " . implode(', ', $dias_com_culto) . "</p>";
    } else {
        echo "<p class='text-warning'>⚠️ Nenhum dia com culto encontrado no mês 10!</p>";
    }
    
    echo "</div></div></div></div>";
    
    // 5. Gerar dias de culto configurados para o mês 10
    $dias_culto_configurados = [];
    $dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
    
    for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
        $data_completa = sprintf('%s-%02d-%02d', $ano, $mes, $dia);
        $data_obj = new DateTime($data_completa);
        $dia_semana = $data_obj->format('N');
        
        if (in_array($dia_semana, $dias_culto_semana)) {
            $dias_culto_configurados[] = $data_completa;
        }
    }
    
    echo "<div class='row mt-3'>
        <div class='col-12'>
            <div class='card'>
                <div class='card-header'>
                    <h5>📅 Dias de Culto Configurados no Mês 10</h5>
                </div>
                <div class='card-body'>
                    <p><strong>Total de dias configurados para culto:</strong> " . count($dias_culto_configurados) . "</p>
                    <p><strong>Dias configurados:</strong> " . implode(', ', $dias_culto_configurados) . "</p>
                </div>
            </div>
        </div>
    </div>";
    
    // 6. Aplicar lógica de faltas para o mês 10
    $faltas_encontradas = [];
    
    foreach ($dias_culto_configurados as $dia_culto) {
        $data_obj = new DateTime($dia_culto);
        $dia_semana = $data_obj->format('N');
        
        if (in_array($dia_semana, $dias_culto_semana)) {
            if (in_array($dia_culto, $dias_com_culto)) {
                // Houve culto - verificar se o usuário tem presença
                $tem_presenca = isset($presencas[$dia_culto]);
                $status_presenca = $tem_presenca ? $presencas[$dia_culto]['status'] : null;
                
                // Determinar se é falta
                $eh_falta = false;
                
                if (!$tem_presenca) {
                    $eh_falta = true;
                } elseif ($status_presenca === 'falta') {
                    $eh_falta = true;
                }
                
                if ($eh_falta) {
                    $justificativa = isset($justificativas[$dia_culto]) ? $justificativas[$dia_culto] : null;
                    
                    $faltas_encontradas[] = [
                        'data' => $dia_culto,
                        'tipo' => $tem_presenca ? 'explícita' : 'implícita',
                        'justificativa' => $justificativa ? $justificativa['status'] : null
                    ];
                }
            }
        }
    }
    
    echo "<div class='row mt-3'>
        <div class='col-12'>
            <div class='card'>
                <div class='card-header'>
                    <h5>❌ Faltas Encontradas no Mês 10</h5>
                </div>
                <div class='card-body'>
                    <p><strong>Total de faltas encontradas:</strong> " . count($faltas_encontradas) . "</p>";
    
    if (count($faltas_encontradas) > 0) {
        echo "<table class='table table-sm'>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Justificativa</th>
                </tr>
            </thead>
            <tbody>";
        
        foreach ($faltas_encontradas as $falta) {
            $badge_class = $falta['tipo'] === 'implícita' ? 'bg-secondary' : 'bg-info';
            $justificativa_text = $falta['justificativa'] ? $falta['justificativa'] : 'Nenhuma';
            
            echo "<tr>
                <td>{$falta['data']}</td>
                <td><span class='badge $badge_class'>{$falta['tipo']}</span></td>
                <td>$justificativa_text</td>
            </tr>";
        }
        
        echo "</tbody></table>";
    } else {
        echo "<p class='text-muted'>Nenhuma falta encontrada para o usuário no mês 10</p>";
    }
    
    echo "</div></div></div></div>";
    
    // 7. Resumo e diagnóstico
    echo "<div class='row mt-3'>
        <div class='col-12'>
            <div class='card border-primary'>
                <div class='card-header bg-primary text-white'>
                    <h5>🔍 Diagnóstico</h5>
                </div>
                <div class='card-body'>";
    
    if (count($dias_com_culto) == 0) {
        echo "<div class='alert alert-warning'>
            <strong>⚠️ PROBLEMA IDENTIFICADO:</strong> Não há registros de culto no mês 10. 
            Isso significa que não houve presenças registradas por nenhum usuário neste mês.
        </div>";
    } elseif (count($faltas_encontradas) == 0) {
        echo "<div class='alert alert-info'>
            <strong>ℹ️ SITUAÇÃO NORMAL:</strong> O usuário não tem faltas no mês 10. 
            Todas as presenças foram registradas corretamente.
        </div>";
    } else {
        echo "<div class='alert alert-success'>
            <strong>✅ FALTAS ENCONTRADAS:</strong> O sistema encontrou " . count($faltas_encontradas) . " faltas no mês 10. 
            Verifique se a página de justificativas está aplicando os filtros corretamente.
        </div>";
    }
    
    echo "</div></div></div></div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
        <h5>❌ Erro</h5>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
    </div>";
}

echo "</div></body></html>";
?>
