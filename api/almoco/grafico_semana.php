<?php
include_once(__DIR__ . '/../conexao.php');
header('Content-Type: application/json');

// Receber parâmetros de data ou usar padrão (últimos 7 dias)
$dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d', strtotime('-6 days'));
$dataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');

// Dias da semana ordenados
$dias = ['Mon' => 'Seg', 'Tue' => 'Ter', 'Wed' => 'Qua', 'Thu' => 'Qui', 'Fri' => 'Sex', 'Sat' => 'Sáb', 'Sun' => 'Dom'];
$resultado = [];

// Inicia com 0 para todos os dias
foreach ($dias as $sigla => $nome) {
    $resultado[$nome] = 0;
}

// Consulta refeições simples (reservas_almoco)
$sql1 = "SELECT data FROM reservas_almoco WHERE data BETWEEN ? AND ?";
$stmt1 = $conn->prepare($sql1);
$stmt1->bind_param("ss", $dataInicio, $dataFim);
$stmt1->execute();
$res1 = $stmt1->get_result();

while ($row = $res1->fetch_assoc()) {
    $dia = $dias[date('D', strtotime($row['data']))];
    $resultado[$dia]++;
}

// Consulta reservas adicionais (reservas_adicionais)
$sql2 = "SELECT data, quantidade FROM reservas_adicionais WHERE data BETWEEN ? AND ?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("ss", $dataInicio, $dataFim);
$stmt2->execute();
$res2 = $stmt2->get_result();

while ($row = $res2->fetch_assoc()) {
    $dia = $dias[date('D', strtotime($row['data']))];
    $resultado[$dia] += (int)$row['quantidade'];
}

// Prepara resposta com os últimos 7 dias
$retorno = [];
$dataAtual = new DateTime($dataInicio);

while ($dataAtual <= new DateTime($dataFim)) {
    $sigla = $dataAtual->format('D');
    $nome = $dias[$sigla];
    
    $retorno[] = [
        'dia' => $nome,
        'quantidade' => $resultado[$nome],
        'data' => $dataAtual->format('Y-m-d')
    ];
    
    $dataAtual->add(new DateInterval('P1D'));
}

echo json_encode($retorno);
?>
