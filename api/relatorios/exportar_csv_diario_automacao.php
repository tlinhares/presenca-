<?php
/**
 * Versão do exportar_csv_diario.php para automações (sem sessão)
 */

include_once(__DIR__ . '/../conexao.php');

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

// Função para escrever linha CSV
function write_csv_line($output, $data) {
    fputcsv($output, $data, ';', '"');
}

// Parâmetros
$tipo = $_GET['tipo'] ?? 'diario';
$data = $_GET['data'] ?? date('Y-m-d');

// Configurar headers para download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="relatorio_diario_' . $data . '.csv"');

// Abrir output
$output = fopen('php://output', 'w');

// Escrever cabeçalho
write_csv_line($output, ['RELATÓRIO DIÁRIO - ' . date('d/m/Y', strtotime($data))]);
write_csv_line($output, ['Gerado em: ' . date('d/m/Y H:i:s')]);
write_csv_line($output, []);

// Consultar reservas próprias
$sql_proprias = "SELECT u.nome, u.cpf, 1 as quantidade, 'Própria' as origem, ra.data, ra.valor_refeicao as valor
                 FROM reservas_almoco ra
                 JOIN usuarios u ON ra.id_usuario = u.id
                 WHERE ra.data = ?
                 ORDER BY u.nome ASC";

$stmt1 = $conn->prepare($sql_proprias);
$stmt1->bind_param("s", $data);
$stmt1->execute();
$result1 = $stmt1->get_result();

$reservas_proprias = [];
while ($row = $result1->fetch_assoc()) {
    $dadosFuncionario = consultarFuncionarioAPI($row['cpf']);
    $row['numero_entidade'] = $dadosFuncionario['Numero_Entidade'] ?? '';
    $row['numero_funcionario_entidade'] = $dadosFuncionario['Numero_Funcionario_Entidade'] ?? '';
    $reservas_proprias[] = $row;
}

// Consultar reservas adicionais
$sql_adicionais = "SELECT u.nome as usuario_nome, u.cpf, d.nome as dependente_nome, ra.quantidade, 'Adicional' as origem, ra.data, 
                   (ra.valor_refeicao + ra.valor_marmitex) as valor
                   FROM reservas_adicionais ra
                   JOIN dependentes d ON ra.id_dependente = d.id
                   JOIN usuarios u ON d.id_usuario = u.id
                   WHERE ra.data = ?
                   ORDER BY u.nome ASC, d.nome ASC";

$stmt2 = $conn->prepare($sql_adicionais);
$stmt2->bind_param("s", $data);
$stmt2->execute();
$result2 = $stmt2->get_result();

$reservas_adicionais = [];
while ($row = $result2->fetch_assoc()) {
    $dadosFuncionario = consultarFuncionarioAPI($row['cpf']);
    $row['numero_entidade'] = $dadosFuncionario['Numero_Entidade'] ?? '';
    $row['numero_funcionario_entidade'] = $dadosFuncionario['Numero_Funcionario_Entidade'] ?? '';
    $reservas_adicionais[] = $row;
}

// Escrever seção de reservas próprias
write_csv_line($output, ['RESERVAS PRÓPRIAS']);
if ($tipo === 'diario') {
    write_csv_line($output, ['Nome', 'Quantidade', 'Nº Entidade', 'Nº Funcionário']);
} else {
    write_csv_line($output, ['Nome', 'Quantidade', 'Nº Entidade', 'Nº Funcionário', 'Valor (R$)']);
}

foreach ($reservas_proprias as $item) {
    if ($tipo === 'diario') {
        write_csv_line($output, [
            $item['nome'],
            $item['quantidade'],
            $item['numero_entidade'],
            $item['numero_funcionario_entidade']
        ]);
    } else {
        write_csv_line($output, [
            $item['nome'],
            $item['quantidade'],
            $item['numero_entidade'],
            $item['numero_funcionario_entidade'],
            'R$ ' . number_format($item['valor'], 2, ',', '.')
        ]);
    }
}

write_csv_line($output, []);

// Escrever seção de reservas adicionais
write_csv_line($output, ['RESERVAS ADICIONAIS']);
if ($tipo === 'diario') {
    write_csv_line($output, ['Usuário', 'Dependente', 'Quantidade', 'Nº Entidade', 'Nº Funcionário']);
} else {
    write_csv_line($output, ['Usuário', 'Dependente', 'Quantidade', 'Nº Entidade', 'Nº Funcionário', 'Valor (R$)']);
}

foreach ($reservas_adicionais as $item) {
    if ($tipo === 'diario') {
        write_csv_line($output, [
            $item['usuario_nome'],
            $item['dependente_nome'],
            $item['quantidade'],
            $item['numero_entidade'],
            $item['numero_funcionario_entidade']
        ]);
    } else {
        write_csv_line($output, [
            $item['usuario_nome'],
            $item['dependente_nome'],
            $item['quantidade'],
            $item['numero_entidade'],
            $item['numero_funcionario_entidade'],
            'R$ ' . number_format($item['valor'], 2, ',', '.')
        ]);
    }
}

// Escrever resumo
write_csv_line($output, []);
write_csv_line($output, ['RESUMO']);
write_csv_line($output, ['Total de reservas próprias: ' . count($reservas_proprias)]);
write_csv_line($output, ['Total de reservas adicionais: ' . count($reservas_adicionais)]);
write_csv_line($output, ['Total geral: ' . (count($reservas_proprias) + count($reservas_adicionais))]);

fclose($output);
?>
