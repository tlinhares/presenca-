<?php
/**
 * Script de debug específico para mês 10 de 2025
 */

session_start();
require_once 'auth/verifica_sessao.php';
require_once 'api/conexao.php';

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$mes = 10; // Outubro
$ano = 2025; // Ano correto

echo "<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Debug - Mês 10 de 2025</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
<div class='container mt-4'>
    <h2>🔍 Debug - Mês 10 de 2025 (Outubro)</h2>
    <p class='text-muted'>Investigando faltas do mês 10 de 2025</p>
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
                    <h5>📊 Informações do Debug</h5>
                </div>
                <div class='card-body'>
                    <p><strong>ID do Usuário:</strong> $usuario_id</p>
                    <p><strong>Mês Analisado:</strong> $mes (Outubro)</p>
                    <p><strong>Ano Analisado:</strong> $ano</p>
                    <p><strong>Data Atual:</strong> " . date('Y-m-d H:i:s') . "</p>
                </div>
            </div>
        </div>
    </div>";
    
    // 1. Verificar TODAS as presenças de culto no mês 10 de 2025
    $sql_todas_presencas = "
        SELECT DATE(data) as data_presenca,
               COUNT(*) as total_presencas,
               GROUP_CONCAT(DISTINCT status) as status_unicos
        FROM presencas_culto 
        WHERE YEAR(data) = ? AND MONTH(data) = ?
        GROUP BY DATE(data)
        ORDER BY data_presenca
    ";
    
    $stmt_todas = $conn->prepare($sql_todas_presencas);
    $stmt_todas->bind_param("ii", $ano, $mes);
    $stmt_todas->execute();
    $resultado_todas = $stmt_todas->get_result();
    
    $dias_com_culto = [];
    while ($row = $resultado_todas->fetch_assoc()) {
        $dias_com_culto[] = $row['data_presenca'];
    }
    
    echo "<div class='row mt-3'>
        <div class='col-12'>
            <div class='card'>
                <div class='card-header'>
                    <h5>⛪ Todas as Presenças de Culto no Mês 10 de 2025</h5>
                </div>
                <div class='card-body'>
                    <p><strong>Total de dias com culto:</strong> " . count($dias_com_culto) . "</p>";
    
    if (count($dias_com_culto) > 0) {
        echo "<table class='table table-sm'>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Total Presenças</th>
                    <th>Status Únicos</th>
                </tr>
            </thead>
            <tbody>";
        
        $stmt_todas->execute();
        $resultado_todas = $stmt_todas->get_result();
        
        while ($row = $resultado_todas->fetch_assoc()) {
            echo "<tr>
                <td>{$row['data_presenca']}</td>
                <td><span class='badge bg-primary'>{$row['total_presencas']}</span></td>
                <td>{$row['status_unicos']}</td>
            </tr>";
        }
        
        echo "</tbody></table>";
    } else {
        echo "<p class='text-warning'>⚠️ Nenhuma presença encontrada no mês 10 de 2025!</p>";
    }
    
    echo "</div></div></div></div>";
    
    // 2. Verificar presenças específicas do usuário
    $sql_presencas_usuario = "
        SELECT DATE(data) as data_presenca,
               status,
               horario_confirmacao
        FROM presencas_culto 
        WHERE id_usuario = ?
        AND YEAR(data) = ? AND MONTH(data) = ?
        ORDER BY data DESC
    ";
    
    $stmt_usuario = $conn->prepare($sql_presencas_usuario);
    $stmt_usuario->bind_param("iii", $usuario_id, $ano, $mes);
    $stmt_usuario->execute();
    $resultado_usuario = $stmt_usuario->get_result();
    
    $presencas_usuario = [];
    while ($row = $resultado_usuario->fetch_assoc()) {
        $presencas_usuario[$row['data_presenca']] = [
            'status' => $row['status'],
            'horario' => $row['horario_confirmacao']
        ];
    }
    
    echo "<div class='row mt-3'>
        <div class='col-12'>
            <div class='card'>
                <div class='card-header'>
                    <h5>👤 Presenças do Usuário no Mês 10 de 2025</h5>
                </div>
                <div class='card-body'>
                    <p><strong>Total de presenças do usuário:</strong> " . count($presencas_usuario) . "</p>";
    
    if (count($presencas_usuario) > 0) {
        echo "<table class='table table-sm'>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Status</th>
                    <th>Horário</th>
                </tr>
            </thead>
            <tbody>";
        
        foreach ($presencas_usuario as $data => $presenca) {
            $badge_class = $presenca['status'] === 'presente' ? 'bg-success' : 
                          ($presenca['status'] === 'atrasado' ? 'bg-warning' : 'bg-danger');
            
            echo "<tr>
                <td>$data</td>
                <td><span class='badge $badge_class'>{$presenca['status']}</span></td>
                <td>{$presenca['horario']}</td>
            </tr>";
        }
        
        echo "</tbody></table>";
    } else {
        echo "<p class='text-info'>ℹ️ Usuário não tem presenças registradas no mês 10 de 2025</p>";
    }
    
    echo "</div></div></div></div>";
    
    // 3. Verificar justificativas do usuário
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
    $resultado_justificativas = $stmt_justificativas->get_result();
    
    $justificativas = [];
    while ($row = $resultado_justificativas->fetch_assoc()) {
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
                    <h5>📝 Justificativas do Usuário no Mês 10 de 2025</h5>
                </div>
                <div class='card-body'>
                    <p><strong>Total de justificativas:</strong> " . count($justificativas) . "</p>";
    
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
            $badge_class = $justificativa['status'] === 'aprovada' ? 'bg-success' : 
                          ($justificativa['status'] === 'pendente' ? 'bg-warning' : 'bg-danger');
            
            echo "<tr>
                <td>$data</td>
                <td>{$justificativa['motivo']}</td>
                <td><span class='badge $badge_class'>{$justificativa['status']}</span></td>
            </tr>";
        }
        
        echo "</tbody></table>";
    } else {
        echo "<p class='text-muted'>Nenhuma justificativa encontrada</p>";
    }
    
    echo "</div></div></div></div>";
    
    // 4. Aplicar lógica de faltas para o mês 10 de 2025
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
    
    // Gerar dias de culto configurados para outubro 2025
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
                    <h5>📅 Dias de Culto Configurados - Outubro 2025</h5>
                </div>
                <div class='card-body'>
                    <p><strong>Dias configurados para culto:</strong> " . count($dias_culto_configurados) . "</p>
                    <p><strong>Dias:</strong> " . implode(', ', $dias_culto_configurados) . "</p>
                </div>
            </div>
        </div>
    </div>";
    
    // 5. Calcular faltas do usuário
    $faltas_encontradas = [];
    
    foreach ($dias_culto_configurados as $dia_culto) {
        if (in_array($dia_culto, $dias_com_culto)) {
            // Houve culto neste dia
            $tem_presenca = isset($presencas_usuario[$dia_culto]);
            $status_presenca = $tem_presenca ? $presencas_usuario[$dia_culto]['status'] : null;
            
            // Determinar se é falta
            $eh_falta = false;
            
            if (!$tem_presenca) {
                $eh_falta = true; // Falta implícita
            } elseif ($status_presenca === 'falta') {
                $eh_falta = true; // Falta explícita
            }
            
            if ($eh_falta) {
                $justificativa = isset($justificativas[$dia_culto]) ? $justificativas[$dia_culto] : null;
                
                $faltas_encontradas[] = [
                    'data' => $dia_culto,
                    'tipo' => $tem_presenca ? 'explícita' : 'implícita',
                    'justificativa' => $justificativa ? $justificativa['status'] : null,
                    'motivo' => $justificativa ? $justificativa['motivo'] : null
                ];
            }
        }
    }
    
    echo "<div class='row mt-3'>
        <div class='col-12'>
            <div class='card border-danger'>
                <div class='card-header bg-danger text-white'>
                    <h5>❌ Faltas do Usuário no Mês 10 de 2025</h5>
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
                    <th>Motivo</th>
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
                <td>" . ($falta['motivo'] ?: '-') . "</td>
            </tr>";
        }
        
        echo "</tbody></table>";
        
        echo "<div class='alert alert-success mt-3'>
            <strong>✅ RESULTADO:</strong> O usuário tem " . count($faltas_encontradas) . " faltas no mês 10 de 2025 que podem ser justificadas!
        </div>";
    } else {
        echo "<p class='text-success'>✅ Usuário não tem faltas no mês 10 de 2025</p>";
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
