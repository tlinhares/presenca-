<?php
session_start();

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

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit;
}

$tipo = $_GET['tipo'] ?? 'diario';
$data = $_GET['data'] ?? date('Y-m-d');

try {
    $dados = [];
    
    // Buscar reservas próprias
    $sql_proprias = "SELECT u.nome, u.cpf, 1 as quantidade, 'Própria' as origem, ra.data, ra.valor_refeicao as valor
                    FROM reservas_almoco ra
                    JOIN usuarios u ON ra.id_usuario = u.id
                    WHERE ra.data = ?
                    ORDER BY u.nome ASC";
    
    $stmt = $conn->prepare($sql_proprias);
    $stmt->bind_param("s", $data);
    $stmt->execute();
    $result_proprias = $stmt->get_result();
    
    $reservas_proprias = [];
    while ($row = $result_proprias->fetch_assoc()) {
        // Consultar dados do funcionário na API
        $dadosFuncionario = consultarFuncionarioAPI($row['cpf']);
        $row['numero_entidade'] = $dadosFuncionario['Numero_Entidade'] ?? '';
        $row['numero_funcionario_entidade'] = $dadosFuncionario['Numero_Funcionario_Entidade'] ?? '';
        
        $reservas_proprias[] = $row;
    }
    $stmt->close();
    
    // Buscar reservas adicionais
    $sql_adicionais = "SELECT u.nome as usuario_nome, u.cpf, d.nome as dependente_nome, ra.quantidade, 'Adicional' as origem, ra.data, 
                      (ra.valor_refeicao + ra.valor_marmitex) as valor
                      FROM reservas_adicionais ra
                      JOIN usuarios u ON ra.id_usuario = u.id
                      JOIN dependentes d ON ra.id_dependente = d.id
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
        // Consultar dados do funcionário responsável na API
        $dadosFuncionario = consultarFuncionarioAPI($row['cpf']);
        $row['numero_entidade'] = $dadosFuncionario['Numero_Entidade'] ?? '';
        $row['numero_funcionario_entidade'] = $dadosFuncionario['Numero_Funcionario_Entidade'] ?? '';
        
        $reservas_adicionais[] = $row;
    }
    $stmt->close();
    
    // Buscar reservas de departamento
    $reservas_departamento = [];
    $sql_departamentos = "SELECT e.entidade_nome as departamento, rd.evento_motivo, rd.quantidade, 
                         'Departamento' as origem, rd.data, rd.valor_unitario, rd.valor_total as valor
                         FROM reservas_departamento rd
                         LEFT JOIN entidade e ON rd.entidade_id = e.entidade_id
                         WHERE rd.data = ?
                         ORDER BY e.entidade_nome ASC";
    
    $stmt = $conn->prepare($sql_departamentos);
    if ($stmt) {
        $stmt->bind_param("s", $data);
        $stmt->execute();
        $result_departamentos = $stmt->get_result();
        
        while ($row = $result_departamentos->fetch_assoc()) {
            $reservas_departamento[] = $row;
        }
        $stmt->close();
    }
    
    // Calcular totais
    $total_proprias = count($reservas_proprias);
    $total_adicionais = 0;
    $valor_total_proprias = 0;
    $valor_total_adicionais = 0;
    $valor_total_departamentos = 0;
    
    // Calcular valores das reservas próprias
    foreach ($reservas_proprias as $item) {
        $valor_total_proprias += $item['valor'];
    }
    
    // Calcular valores das reservas adicionais
    foreach ($reservas_adicionais as $item) {
        $total_adicionais += $item['quantidade'];
        $valor_total_adicionais += $item['valor'];
    }
    
    // Calcular valores das reservas de departamento
    $total_departamentos = 0;
    foreach ($reservas_departamento as $item) {
        $total_departamentos += $item['quantidade'];
        $valor_total_departamentos += $item['valor'];
    }
    
    $total_geral = $total_proprias + $total_adicionais + $total_departamentos;
    $valor_total_geral = $valor_total_proprias + $valor_total_adicionais + $valor_total_departamentos;
    
    // Configurar headers para download
    $filename = 'relatorio_diario_' . $data . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Criar arquivo CSV
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Função para escrever CSV com ponto e vírgula como separador
    function write_csv_line($output, $fields) {
        $line = '';
        foreach ($fields as $i => $field) {
            if ($i > 0) $line .= ';';
            // Se o campo contém vírgula, ponto e vírgula ou aspas, colocar entre aspas
            if (strpos($field, ',') !== false || strpos($field, ';') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
                $field = '"' . str_replace('"', '""', $field) . '"';
            }
            $line .= $field;
        }
        fwrite($output, $line . "\n");
    }
    
    // Cabeçalho do relatório
    write_csv_line($output, ['RELATÓRIO DIÁRIO - ' . date('d/m/Y', strtotime($data))]);
    write_csv_line($output, []);
    
    // Reservas Próprias
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
    
    // Linha em branco
    write_csv_line($output, []);
    
    // Reservas Adicionais
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
    
    // Linha em branco
    write_csv_line($output, []);
    
    // Reservas de Departamento
    write_csv_line($output, ['RESERVAS DE DEPARTAMENTO']);
    if ($tipo === 'diario') {
        write_csv_line($output, ['Departamento', 'Evento/Motivo', 'Quantidade']);
    } else {
        write_csv_line($output, ['Departamento', 'Evento/Motivo', 'Quantidade', 'Valor Unit. (R$)', 'Valor Total (R$)']);
    }
    
    foreach ($reservas_departamento as $item) {
        if ($tipo === 'diario') {
            write_csv_line($output, [
                $item['departamento'] ?? 'N/A',
                $item['evento_motivo'],
                $item['quantidade']
            ]);
        } else {
            write_csv_line($output, [
                $item['departamento'] ?? 'N/A',
                $item['evento_motivo'],
                $item['quantidade'],
                'R$ ' . number_format($item['valor_unitario'], 2, ',', '.'),
                'R$ ' . number_format($item['valor'], 2, ',', '.')
            ]);
        }
    }
    
    // Linha em branco
    write_csv_line($output, []);
    
    // Seção de totais
    write_csv_line($output, ['RESUMO DOS TOTAIS']);
    
    if ($tipo === 'diario_completo') {
        write_csv_line($output, ['Tipo de Reserva', 'Quantidade', 'Valor Total']);
        write_csv_line($output, ['Reserva Própria', $total_proprias, 'R$ ' . number_format($valor_total_proprias, 2, ',', '.')]);
        write_csv_line($output, ['Adicional - Presencial', $total_adicionais, 'R$ ' . number_format($valor_total_adicionais, 2, ',', '.')]);
        write_csv_line($output, ['Departamentos', $total_departamentos, 'R$ ' . number_format($valor_total_departamentos, 2, ',', '.')]);
        write_csv_line($output, ['TOTAL GERAL', $total_geral, 'R$ ' . number_format($valor_total_geral, 2, ',', '.')]);
    } else {
        write_csv_line($output, ['Tipo de Reserva', 'Quantidade']);
        write_csv_line($output, ['Reserva Própria', $total_proprias]);
        write_csv_line($output, ['Adicional - Presencial', $total_adicionais]);
        write_csv_line($output, ['Departamentos', $total_departamentos]);
        write_csv_line($output, ['TOTAL GERAL', $total_geral]);
    }
    
    // Linha em branco
    write_csv_line($output, []);
    
    // Rodapé
    write_csv_line($output, ['Relatório gerado automaticamente pelo Sistema de Presença']);
    write_csv_line($output, ['Gerado em: ' . date('d/m/Y H:i:s')]);
    
    fclose($output);
    
} catch (Exception $e) {
    echo "Erro ao gerar relatório: " . $e->getMessage();
}
?>
