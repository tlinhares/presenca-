<?php
include_once(__DIR__ . '/../conexao.php');
header('Content-Type: application/json');

function mergeParams($types, $params) {
    $bind = [];
    $bind[] = $types;
    foreach ($params as $key => $val) {
        $bind[] = &$params[$key];
    }
    return $bind;
}

// Filtros
$data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
$categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';

// ======= TOTAL DE RESERVAS PRÓPRIAS E VALOR =======
$sqlProprias = "SELECT COUNT(*), SUM(r.valor_refeicao) FROM reservas_almoco r
                INNER JOIN usuarios u ON u.id = r.id_usuario
                WHERE r.data = ?";
$params = [$data];
$types = "s";

if (!empty($categoria)) {
    $sqlProprias .= " AND u.categoria = ?";
    $params[] = $categoria;
    $types .= "s";
}

$stmt = $conn->prepare($sqlProprias);
call_user_func_array([$stmt, 'bind_param'], mergeParams($types, $params));
$stmt->execute();
$stmt->bind_result($total_proprias, $soma_proprias);
$stmt->fetch();
$stmt->close();
$total_proprias = intval($total_proprias);
$soma_proprias = floatval($soma_proprias);

// ======= TOTAL DE ADICIONAIS E VALORES =======
$sqlAdicionais = "SELECT SUM(quantidade), SUM(valor_refeicao), SUM(valor_marmitex)
                  FROM reservas_adicionais r
                  INNER JOIN usuarios u ON u.id = r.id_usuario
                  WHERE r.data = ?";
$params2 = [$data];
$types2 = "s";

if (!empty($categoria)) {
    $sqlAdicionais .= " AND u.categoria = ?";
    $params2[] = $categoria;
    $types2 .= "s";
}

$stmt2 = $conn->prepare($sqlAdicionais);
call_user_func_array([$stmt2, 'bind_param'], mergeParams($types2, $params2));
$stmt2->execute();
$stmt2->bind_result($total_adicionais, $soma_refeicao_ad, $soma_marmitex_ad);
$stmt2->fetch();
$stmt2->close();
$total_adicionais = $total_adicionais ? intval($total_adicionais) : 0;

$soma_refeicao_ad = floatval($soma_refeicao_ad);
$soma_marmitex_ad = floatval($soma_marmitex_ad);

// ======= VALOR ESTIMADO FINAL =======
$valor_total = $soma_proprias + $soma_refeicao_ad + $soma_marmitex_ad;
$valor_estimado = number_format($valor_total, 2, ',', '.');

// ======= ÚLTIMAS RESERVAS =======
$sqlUltimas = "SELECT u.nome, r.data FROM reservas_almoco r 
               INNER JOIN usuarios u ON u.id = r.id_usuario 
               WHERE r.data = ?
               ORDER BY r.horario_confirmacao DESC
               LIMIT 5";

$stmt3 = $conn->prepare($sqlUltimas);
$stmt3->bind_param("s", $data);
$stmt3->execute();
$stmt3->bind_result($nome, $data_reserva);

$ultimas = [];
while ($stmt3->fetch()) {
    $ultimas[] = [
        'nome' => $nome,
        'data' => $data_reserva
    ];
}
$stmt3->close();

// ======= SAÍDA FINAL =======
echo json_encode([
    'total_refeicoes' => $total_proprias + $total_adicionais,
    'valor_estimado' => $valor_estimado,
    'ultimas_reservas' => $ultimas
]);
?>