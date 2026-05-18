<?php
// Versão sem verificação de sessão para teste
include_once(__DIR__ . '/../conexao.php');

$tipo = $_GET['tipo'] ?? 'diario';
$data = $_GET['data'] ?? date('Y-m-d');

try {
    // Buscar reservas próprias
    $sql_proprias = "SELECT u.nome, 1 as quantidade, 'Própria' as origem, ra.data, ra.valor_refeicao as valor
                    FROM reservas_almoco ra
                    JOIN usuarios u ON ra.id_usuario = u.id
                    WHERE ra.data = ?
                    ORDER BY u.nome ASC";
    
    $stmt = $conn->prepare($sql_proprias);
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta: " . $conn->error);
    }
    $stmt->bind_param("s", $data);
    $stmt->execute();
    $result_proprias = $stmt->get_result();
    
    $reservas_proprias = [];
    while ($row = $result_proprias->fetch_assoc()) {
        $reservas_proprias[] = $row;
    }
    $stmt->close();
    
    // Buscar reservas adicionais
    $sql_adicionais = "SELECT u.nome as usuario_nome, d.nome as dependente_nome, ra.quantidade, 'Adicional' as origem, ra.data, 
                      (ra.valor_refeicao + ra.valor_marmitex) as valor
                      FROM reservas_adicionais ra
                      JOIN dependentes d ON ra.id_dependente = d.id
                      JOIN usuarios u ON d.id_usuario = u.id
                      WHERE ra.data = ?
                      ORDER BY u.nome ASC, d.nome ASC";
    
    $stmt = $conn->prepare($sql_adicionais);
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta de reservas adicionais: " . $conn->error);
    }
    $stmt->bind_param("s", $data);
    $stmt->execute();
    $result_adicionais = $stmt->get_result();
    
    $reservas_adicionais = [];
    while ($row = $result_adicionais->fetch_assoc()) {
        $reservas_adicionais[] = $row;
    }
    $stmt->close();
    
    // Calcular totais
    $total_proprias = count($reservas_proprias);
    $total_adicionais = 0;
    $valor_total_proprias = 0;
    $valor_total_adicionais = 0;
    
    // Calcular valores das reservas próprias
    foreach ($reservas_proprias as $item) {
        $valor_total_proprias += $item['valor'];
    }
    
    // Calcular valores das reservas adicionais
    foreach ($reservas_adicionais as $item) {
        $total_adicionais += $item['quantidade'];
        $valor_total_adicionais += $item['valor'];
    }
    
    $total_geral = $total_proprias + $total_adicionais;
    $valor_total_geral = $valor_total_proprias + $valor_total_adicionais;
    
    // Retornar JSON
    echo json_encode([
        'status' => 'ok',
        'data' => $data,
        'tipo' => $tipo,
        'reservas_proprias' => $reservas_proprias,
        'reservas_adicionais' => $reservas_adicionais,
        'total_proprias' => $total_proprias,
        'total_adicionais' => $total_adicionais,
        'valor_total_proprias' => $valor_total_proprias,
        'valor_total_adicionais' => $valor_total_adicionais,
        'total_geral' => $total_geral,
        'valor_total_geral' => $valor_total_geral
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}
?>
