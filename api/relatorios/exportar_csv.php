<?php
include_once(__DIR__ . '/../conexao.php');
include_once(__DIR__ . '/../../utils/config.php');

// Função para consultar dados do funcionário na API
function consultarFuncionarioAPI($cpf) {
    // Remover formatação do CPF (pontos, traços, espaços)
    $cpfLimpo = preg_replace('/\D+/', '', $cpf);
    
    if (empty($cpfLimpo)) {
        return null;
    }
    
    $url = "https://presenca.aom.org.br/api/aps/funcionarios.php?cpf=" . urlencode($cpfLimpo);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET',
            'header' => 'Content-Type: application/json'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['ok']) && $data['ok'] === true && isset($data['data'][0])) {
        return $data['data'][0];
    }
    
    return null;
}

header('Content-Type: text/csv; charset=UTF-8');
$nome_arquivo = 'relatorio_mensal_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');

// Adiciona BOM UTF-8
echo "\xEF\xBB\xBF";

// Abre o output com separador personalizado
$output = fopen('php://output', 'w');
$delimitador = ';';

// Cabeçalho - mesmas colunas do PDF
fputcsv($output, ['Usuário', 'Nº Entidade', 'Nº Funcionário', 'Próprias', 'Valor Próprias', 'Adicionais', 'Valor Adicionais', 'Total Qtd', 'Total Valor'], $delimitador);

// Filtros
$data_inicio = isset($_GET['inicio']) ? $_GET['inicio'] : (isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01'));
$data_fim = isset($_GET['fim']) ? $_GET['fim'] : (isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d'));

// Buscar dados agregados por usuário - mesma query do PDF
$sql = "SELECT 
            u.nome,
            u.cpf,
            COALESCE(proprias.total_quantidade, 0) as qtd_proprias,
            COALESCE(proprias.total_valor, 0) as valor_proprias,
            COALESCE(adicionais.total_quantidade, 0) as qtd_adicionais,
            COALESCE(adicionais.total_valor, 0) as valor_adicionais,
            (COALESCE(proprias.total_quantidade, 0) + COALESCE(adicionais.total_quantidade, 0)) as total_geral_usuario,
            (COALESCE(proprias.total_valor, 0) + COALESCE(adicionais.total_valor, 0)) as valor_total_usuario
        FROM usuarios u
        LEFT JOIN (
            SELECT 
                id_usuario,
                COUNT(*) as total_quantidade,
                SUM(valor_refeicao) as total_valor
            FROM reservas_almoco 
            WHERE data BETWEEN ? AND ?
            GROUP BY id_usuario
        ) proprias ON u.id = proprias.id_usuario
        LEFT JOIN (
            SELECT 
                id_usuario,
                SUM(quantidade) as total_quantidade,
                SUM(valor_refeicao) as total_valor
            FROM reservas_adicionais 
            WHERE data BETWEEN ? AND ?
            GROUP BY id_usuario
        ) adicionais ON u.id = adicionais.id_usuario
        WHERE (proprias.total_quantidade > 0 OR adicionais.total_quantidade > 0)
        ORDER BY u.nome";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $data_inicio, $data_fim, $data_inicio, $data_fim);
$stmt->execute();
$result = $stmt->get_result();

$total_geral_qtd = 0;
$total_geral_valor = 0;
$total_proprias = 0;
$total_adicionais = 0;

// Processar dados por usuário
while ($row = $result->fetch_assoc()) {
    // Consultar dados do funcionário na API
    $dadosFuncionario = consultarFuncionarioAPI($row['cpf']);
    $numero_entidade = $dadosFuncionario['Numero_Entidade'] ?? 'N/A';
    $numero_funcionario = $dadosFuncionario['Numero_Funcionario_Entidade'] ?? 'N/A';
    
    // Adicionar linha ao CSV
    fputcsv($output, [
        $row['nome'],
        $numero_entidade,
        $numero_funcionario,
        $row['qtd_proprias'],
        number_format($row['valor_proprias'], 2, ',', '.'),
        $row['qtd_adicionais'],
        number_format($row['valor_adicionais'], 2, ',', '.'),
        $row['total_geral_usuario'],
        number_format($row['valor_total_usuario'], 2, ',', '.')
    ], $delimitador);
    
    // Calcular totais
    $total_geral_qtd += $row['total_geral_usuario'];
    $total_geral_valor += $row['valor_total_usuario'];
    $total_proprias += $row['qtd_proprias'];
    $total_adicionais += $row['qtd_adicionais'];
}

$stmt->close();

// Linha em branco
fputcsv($output, [], $delimitador);

// Linha de totais de usuários
fputcsv($output, ['TOTAL USUÁRIOS', '-', '-', $total_proprias, number_format($total_proprias * 10, 2, ',', '.'), $total_adicionais, number_format($total_adicionais * 10, 2, ',', '.'), ($total_proprias + $total_adicionais), number_format($total_geral_valor, 2, ',', '.')], $delimitador);

// Buscar reservas de departamento no período
$reservas_departamento = [];
$sql_dept = "SELECT e.entidade_nome as departamento, rd.evento_motivo, rd.quantidade, 
             rd.valor_unitario, rd.valor_total as valor, rd.data
             FROM reservas_departamento rd
             LEFT JOIN entidade e ON rd.entidade_id = e.entidade_id
             WHERE rd.data BETWEEN ? AND ?
             ORDER BY e.entidade_nome ASC, rd.data ASC";

$stmt_dept = $conn->prepare($sql_dept);
if ($stmt_dept) {
    $stmt_dept->bind_param("ss", $data_inicio, $data_fim);
    $stmt_dept->execute();
    $result_dept = $stmt_dept->get_result();
    
    while ($row_dept = $result_dept->fetch_assoc()) {
        $reservas_departamento[] = $row_dept;
    }
    $stmt_dept->close();
}

$total_departamentos = 0;
$valor_total_departamentos = 0;
foreach ($reservas_departamento as $item_dept) {
    $total_departamentos += $item_dept['quantidade'];
    $valor_total_departamentos += $item_dept['valor'];
}

// Seção de departamentos
fputcsv($output, [], $delimitador);
fputcsv($output, ['RESERVAS DE DEPARTAMENTO'], $delimitador);
fputcsv($output, ['Departamento', 'Evento/Motivo', 'Data', 'Quantidade', 'Valor Unit.', 'Valor Total'], $delimitador);

foreach ($reservas_departamento as $item_dept) {
    fputcsv($output, [
        $item_dept['departamento'] ?? 'N/A',
        $item_dept['evento_motivo'],
        date('d/m/Y', strtotime($item_dept['data'])),
        $item_dept['quantidade'],
        number_format($item_dept['valor_unitario'], 2, ',', '.'),
        number_format($item_dept['valor'], 2, ',', '.')
    ], $delimitador);
}

fputcsv($output, ['TOTAL DEPARTAMENTOS', '', '', $total_departamentos, '-', number_format($valor_total_departamentos, 2, ',', '.')], $delimitador);

// Total geral (usuários + departamentos)
$total_geral_qtd += $total_departamentos;
$total_geral_valor += $valor_total_departamentos;

fputcsv($output, [], $delimitador);
fputcsv($output, ['TOTAL GERAL', '', '', '', 'Quantidade', 'Valor'], $delimitador);
fputcsv($output, ['', '', '', '', $total_geral_qtd, number_format($total_geral_valor, 2, ',', '.')], $delimitador);

fclose($output);
exit;
?>