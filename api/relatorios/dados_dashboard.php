<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../conexao.php';

try {
    $hoje = date('Y-m-d');
    $mes_atual = date('Y-m');
    
    // Total de usuários
    $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE ativo = 1");
    $stmt->execute();
    $total_usuarios = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    
    // Reservas de hoje
    $stmt = $conn->prepare("SELECT COUNT(*) FROM reservas_almoco WHERE data = ?");
    $stmt->bind_param("s", $hoje);
    $stmt->execute();
    $reservas_hoje = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    
    // Reservas adicionais de hoje
    $stmt = $conn->prepare("SELECT COUNT(*) FROM reservas_adicionais WHERE data = ?");
    $stmt->bind_param("s", $hoje);
    $stmt->execute();
    $reservas_adicionais_hoje = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    
    // Total de reservas do mês
    $mes_pattern = $mes_atual . '%';
    $stmt = $conn->prepare("SELECT COUNT(*) FROM reservas_almoco WHERE data LIKE ?");
    $stmt->bind_param("s", $mes_pattern);
    $stmt->execute();
    $reservas_mes = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    
    // Presenças de hoje
    $stmt = $conn->prepare("SELECT COUNT(*) FROM presencas_culto WHERE data = ?");
    $stmt->bind_param("s", $hoje);
    $stmt->execute();
    $presencas_hoje = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    
    // Receita de hoje - Reservas próprias
    $stmt = $conn->prepare("SELECT SUM(valor_refeicao) FROM reservas_almoco WHERE data = ?");
    $stmt->bind_param("s", $hoje);
    $stmt->execute();
    $receita_proprias_hoje = $stmt->get_result()->fetch_row()[0] ?? 0;
    $stmt->close();
    
    // Receita de hoje - Reservas adicionais
    $stmt = $conn->prepare("SELECT SUM(valor_refeicao + valor_marmitex) FROM reservas_adicionais WHERE data = ?");
    $stmt->bind_param("s", $hoje);
    $stmt->execute();
    $receita_adicionais_hoje = $stmt->get_result()->fetch_row()[0] ?? 0;
    $stmt->close();
    
    // Quantidade de reservas de departamento de hoje
    $stmt = $conn->prepare("SELECT SUM(quantidade) FROM reservas_departamento WHERE data = ?");
    $stmt->bind_param("s", $hoje);
    $stmt->execute();
    $reservas_departamentos_hoje = $stmt->get_result()->fetch_row()[0] ?? 0;
    $stmt->close();
    
    // Receita de hoje - Reservas de departamento
    $stmt = $conn->prepare("SELECT SUM(quantidade * valor_unitario) FROM reservas_departamento WHERE data = ?");
    $stmt->bind_param("s", $hoje);
    $stmt->execute();
    $receita_departamentos_hoje = $stmt->get_result()->fetch_row()[0] ?? 0;
    $stmt->close();
    
    // Receita total de hoje
    $receita_hoje = $receita_proprias_hoje + $receita_adicionais_hoje + $receita_departamentos_hoje;
    
    // Receita do mês - Reservas próprias
    $mes_pattern = $mes_atual . '%';
    $stmt = $conn->prepare("SELECT SUM(valor_refeicao) FROM reservas_almoco WHERE data LIKE ?");
    $stmt->bind_param("s", $mes_pattern);
    $stmt->execute();
    $receita_proprias_mes = $stmt->get_result()->fetch_row()[0] ?? 0;
    $stmt->close();
    
    // Receita do mês - Reservas adicionais
    $stmt = $conn->prepare("SELECT SUM(valor_refeicao + valor_marmitex) FROM reservas_adicionais WHERE data LIKE ?");
    $stmt->bind_param("s", $mes_pattern);
    $stmt->execute();
    $receita_adicionais_mes = $stmt->get_result()->fetch_row()[0] ?? 0;
    $stmt->close();
    
    // Receita do mês - Reservas de departamento
    $stmt = $conn->prepare("SELECT SUM(quantidade * valor_unitario) FROM reservas_departamento WHERE data LIKE ?");
    $stmt->bind_param("s", $mes_pattern);
    $stmt->execute();
    $receita_departamentos_mes = $stmt->get_result()->fetch_row()[0] ?? 0;
    $stmt->close();
    
    // Receita total do mês
    $receita_mes = $receita_proprias_mes + $receita_adicionais_mes + $receita_departamentos_mes;
    
    echo json_encode([
        'status' => 'sucesso',
        'dados' => [
            'total_usuarios' => $total_usuarios,
            'reservas_hoje' => $reservas_hoje,
            'reservas_adicionais_hoje' => $reservas_adicionais_hoje,
            'reservas_mes' => $reservas_mes,
            'presencas_hoje' => $presencas_hoje,
            'receita_hoje' => $receita_hoje,
            'receita_mes' => $receita_mes,
            'receita_proprias_hoje' => $receita_proprias_hoje,
            'receita_adicionais_hoje' => $receita_adicionais_hoje,
            'receita_departamentos_hoje' => $receita_departamentos_hoje,
            'reservas_departamentos_hoje' => $reservas_departamentos_hoje
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar dados: ' . $e->getMessage()
    ]);
}
?>
