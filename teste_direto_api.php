<?php
/**
 * Teste direto da lógica da API do calendário
 */

require_once 'api/conexao.php';

$usuario_id = 22222;
$mes = 10;
$ano = 2025;

echo "=== TESTE DIRETO DA API DO CALENDÁRIO ===\n";
echo "Usuário: $usuario_id\n";
echo "Mês: $mes\n";
echo "Ano: $ano\n\n";

try {
    if (!$conn) {
        throw new Exception('Erro de conexão com o banco de dados');
    }
    
    $conn->set_charset("utf8");
    
    // 1. Buscar presenças do usuário
    echo "1. Buscando presenças...\n";
    $sql_presencas = "
        SELECT DATE(data) as data_presenca, status, observacoes, 
               TIME(data) as horario_confirmacao
        FROM presencas_culto 
        WHERE id_usuario = ? 
        AND YEAR(data) = ? AND MONTH(data) = ?
    ";
    
    $stmt_presencas = $conn->prepare($sql_presencas);
    $stmt_presencas->bind_param("iii", $usuario_id, $ano, $mes);
    $stmt_presencas->execute();
    $result_presencas = $stmt_presencas->get_result();
    
    $presencas = [];
    while ($row = $result_presencas->fetch_assoc()) {
        $presencas[$row['data_presenca']] = [
            'status' => $row['status'],
            'observacoes' => $row['observacoes'],
            'horario' => $row['horario_confirmacao']
        ];
    }
    
    echo "Presenças encontradas: " . count($presencas) . "\n";
    foreach ($presencas as $data => $presenca) {
        echo "  - $data: {$presenca['status']}\n";
    }
    echo "\n";
    
    // 2. Buscar justificativas do usuário
    echo "2. Buscando justificativas...\n";
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
    
    echo "Justificativas encontradas: " . count($justificativas) . "\n";
    foreach ($justificativas as $data => $justificativa) {
        echo "  - $data: {$justificativa['status']} - {$justificativa['motivo']}\n";
    }
    echo "\n";
    
    // 3. Buscar configuração de dias da semana
    echo "3. Buscando configuração de dias da semana...\n";
    $sql_config = "SELECT valor FROM configuracoes_culto WHERE chave = 'dias_semana'";
    $stmt_config = $conn->prepare($sql_config);
    $stmt_config->execute();
    $resultado_config = $stmt_config->get_result();
    
    $dias_semana_config = '1,2,3,4,5'; // Padrão: segunda a sexta
    if ($resultado_config->num_rows > 0) {
        $config = $resultado_config->fetch_assoc();
        $dias_semana_config = $config['valor'];
    }
    
    echo "Dias da semana configurados: $dias_semana_config\n";
    $dias_culto_semana = array_map('trim', explode(',', $dias_semana_config));
    echo "Array de dias: " . implode(', ', $dias_culto_semana) . "\n\n";
    
    // 4. Buscar dias onde houve culto
    echo "4. Buscando dias onde houve culto...\n";
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
    
    echo "Dias com culto: " . count($dias_com_culto) . "\n";
    foreach ($dias_com_culto as $dia) {
        echo "  - $dia\n";
    }
    echo "\n";
    
    // 5. Testar especificamente o dia 13/10/2025
    echo "5. Testando dia específico: 2025-10-13\n";
    $data_especifica = '2025-10-13';
    $dia_semana = date('N', strtotime($data_especifica)); // 1=segunda, 7=domingo
    
    echo "Dia da semana: $dia_semana\n";
    echo "É dia de culto configurado? " . (in_array($dia_semana, $dias_culto_semana) ? 'SIM' : 'NÃO') . "\n";
    echo "Houve culto neste dia? " . (in_array($data_especifica, $dias_com_culto) ? 'SIM' : 'NÃO') . "\n";
    
    if (in_array($dia_semana, $dias_culto_semana) && in_array($data_especifica, $dias_com_culto)) {
        echo "Processando dados do dia...\n";
        
        $dados_dia = [
            'data' => $data_especifica,
            'status' => 'sem_dados',
            'justificativa' => null
        ];
        
        // Verificar presença
        if (isset($presencas[$data_especifica])) {
            $presenca = $presencas[$data_especifica];
            echo "Presença encontrada: {$presenca['status']}\n";
            
            if ($presenca['status'] === 'presente') {
                $dados_dia['status'] = 'presente';
            } else if ($presenca['status'] === 'atrasado') {
                $dados_dia['status'] = 'atrasado';
            } else if ($presenca['status'] === 'falta') {
                $dados_dia['status'] = 'falta';
            }
        } else {
            echo "Nenhuma presença encontrada\n";
        }
        
        // Verificar justificativa
        if (isset($justificativas[$data_especifica])) {
            $justificativa = $justificativas[$data_especifica];
            echo "Justificativa encontrada: {$justificativa['status']} - {$justificativa['motivo']}\n";
            
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
            echo "Nenhuma justificativa encontrada\n";
        }
        
        // Se não tem presença nem justificativa, é falta
        if ($dados_dia['status'] === 'sem_dados') {
            $dados_dia['status'] = 'falta';
        }
        
        echo "\nRESULTADO FINAL:\n";
        echo "Status: {$dados_dia['status']}\n";
        echo "Justificativa: " . ($dados_dia['justificativa'] ?: 'Nenhuma') . "\n";
        
        if ($dados_dia['status'] === 'justificativa_rejeitada') {
            echo "✅ JUSTIFICATIVA REJEITADA DETECTADA!\n";
        } else {
            echo "❌ Justificativa rejeitada NÃO detectada\n";
        }
    } else {
        echo "Este dia não é um dia de culto válido\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>