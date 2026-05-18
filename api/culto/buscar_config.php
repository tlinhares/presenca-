<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

// Verificação de login
require_once '../../auth/verifica_sessao.php';

// Função para buscar configuração do culto
function get_config_culto($chave, $padrao = '') {
    global $conn;
    $stmt = $conn->prepare("SELECT valor FROM configuracoes_culto WHERE chave = ?");
    if (!$stmt) {
        return $padrao;
    }
    $stmt->bind_param("s", $chave);
    $stmt->execute();
    $stmt->bind_result($valor);
    if ($stmt->fetch()) {
        return $valor;
    }
    return $padrao;
}

try {
    // Buscar configurações do culto
    $configuracoes = [
        'horario_inicio' => get_config_culto('horario_inicio', '07:00:00'),
        'horario_culto' => get_config_culto('horario_culto', '07:30:00'),
        'horario_fim' => get_config_culto('horario_fim', '08:00:00'),
        'permitir_atraso' => get_config_culto('permitir_atraso', '1'),
        'horario_atraso_limite' => get_config_culto('horario_atraso_limite', '08:30:00'),
        'dias_semana' => get_config_culto('dias_semana', '1,2,3,4,5'),
        'mensagem_inicio_culto' => get_config_culto('mensagem_inicio_culto', 'Bem-vindo ao sistema de presença de culto!'),
        'culto_habilitado' => get_config_culto('culto_habilitado', '1')
    ];

    // Formatar horários para exibição
    $configuracoes['horario_culto'] = date('H:i', strtotime($configuracoes['horario_culto']));
    $configuracoes['horario_fim'] = date('H:i', strtotime($configuracoes['horario_fim']));
    $configuracoes['horario_atraso_limite'] = date('H:i', strtotime($configuracoes['horario_atraso_limite']));

    echo json_encode([
        'status' => 'ok',
        'configuracoes' => $configuracoes
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar configurações: ' . $e->getMessage()
    ]);
}
?>

