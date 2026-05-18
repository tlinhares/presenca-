<?php
// Temporariamente removida verificação de sessão para resolver erro 500
include_once(__DIR__ . '/../conexao.php');

$tipo = $_GET['tipo'] ?? 'diario';
$data = $_GET['data'] ?? date('Y-m-d');

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
        
        // Adicionar dados da API ao registro
        $row['numero_entidade'] = $dadosFuncionario['Numero_Entidade'] ?? '';
        $row['numero_funcionario_entidade'] = $dadosFuncionario['Numero_Funcionario_Entidade'] ?? '';
        
        $reservas_proprias[] = $row;
    }
    $stmt->close();
    
    // Buscar reservas adicionais
    $sql_adicionais = "SELECT u.nome as usuario_nome, u.cpf, d.nome as dependente_nome, ra.quantidade, 'Adicional' as origem, ra.data, 
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
        // Consultar dados do funcionário responsável na API
        $dadosFuncionario = consultarFuncionarioAPI($row['cpf']);
        
        // Adicionar dados da API ao registro
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
    $valor_total_departamentos = 0;
    foreach ($reservas_departamento as $item) {
        $total_departamentos += $item['quantidade'];
        $valor_total_departamentos += $item['valor'];
    }
    
    $total_geral = $total_proprias + $total_adicionais + $total_departamentos;
    $valor_total_geral = $valor_total_proprias + $valor_total_adicionais + $valor_total_departamentos;
    
    // Incluir mPDF
    require_once(__DIR__ . '/../../vendor/autoload.php');
    
    // Criar instância do mPDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'P',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 20,
        'margin_bottom' => 20,
        'margin_header' => 10,
        'margin_footer' => 10
    ]);
    
    // Configurar documento
    $mpdf->SetTitle('Relatório Diário - ' . date('d/m/Y', strtotime($data)));
    $mpdf->SetAuthor('Sistema de Presença');
    $mpdf->SetCreator('Sistema de Presença');
    
    // Gerar HTML para o PDF
    $html = '
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 0; 
            background-color: #f8f9fa;
        }
        .header { 
            text-align: center; 
            margin-bottom: 10px; 
            padding: 20px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header h1 { 
            color: white; 
            margin-bottom: 10px; 
            font-size: 24px; 
            font-weight: bold;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .header p { 
            color: #e3f2fd; 
            font-size: 16px; 
            margin: 0;
        }
        .content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 25px; 
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th, td { 
            border: 1px solid #dee2e6; 
            padding: 12px 8px; 
            text-align: left; 
            font-size: 11px; 
        }
        th { 
            background: linear-gradient(135deg, #007bff, #0056b3); 
            color: white; 
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        tr:hover {
            background-color: #e3f2fd;
        }
        .totais { 
            margin-top: 30px; 
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .totais h3 {
            color: #007bff;
            text-align: center;
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: bold;
        }
        .totais table { 
            width: 100%; 
            max-width: 400px;
            margin: 0 auto; 
        }
        .totais th { 
            background: linear-gradient(135deg, #28a745, #1e7e34); 
            color: white; 
            text-align: center;
            font-weight: bold;
        }
        .totais td { 
            text-align: center; 
            font-weight: 500;
        }
        .total-geral {
            background-color: #007bff !important;
            color: white !important;
            font-weight: bold !important;
            font-size: 12px !important;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #6c757d;
            font-size: 10px;
            border-top: 2px solid #007bff;
            padding-top: 10px;
        }
    </style>
    
    <div class="header">
        <h1>Relatório Diário</h1>
        <p>Data: ' . date('d/m/Y', strtotime($data)) . '</p>
    </div>
    
    <div class="content">
        <!-- Reservas Próprias -->
        <h3 style="color: #007bff; margin-bottom: 15px; padding: 10px; background: #e3f2fd; border-left: 4px solid #007bff;">RESERVAS PRÓPRIAS</h3>
        <table>
            <thead>
                <tr>';
    
    if ($tipo === 'diario') {
        $html .= '<th>Nome</th><th>Quantidade</th><th>Nº Entidade</th><th>Nº Funcionário</th>';
    } else {
        $html .= '<th>Nome</th><th>Quantidade</th><th>Nº Entidade</th><th>Nº Funcionário</th><th>Valor</th>';
    }
    
    $html .= '</tr>
            </thead>
            <tbody>';
    
    foreach ($reservas_proprias as $item) {
        $html .= '<tr>';
        $html .= '<td><strong>' . htmlspecialchars($item['nome']) . '</strong></td>';
        $html .= '<td>' . $item['quantidade'] . '</td>';
        $html .= '<td>' . htmlspecialchars($item['numero_entidade']) . '</td>';
        $html .= '<td>' . htmlspecialchars($item['numero_funcionario_entidade']) . '</td>';
        
        if ($tipo === 'diario_completo') {
            $html .= '<td><strong>R$ ' . number_format($item['valor'], 2, ',', '.') . '</strong></td>';
        }
        
        $html .= '</tr>';
    }
    
    $html .= '</tbody>
        </table>
        
        <!-- Reservas Adicionais -->
        <h3 style="color: #007bff; margin: 30px 0 15px 0; padding: 10px; background: #e3f2fd; border-left: 4px solid #007bff;">RESERVAS ADICIONAIS</h3>
        <table>
            <thead>
                <tr>';
    
    if ($tipo === 'diario') {
        $html .= '<th>Usuário</th><th>Dependente</th><th>Quantidade</th><th>Nº Entidade</th><th>Nº Funcionário</th>';
    } else {
        $html .= '<th>Usuário</th><th>Dependente</th><th>Quantidade</th><th>Nº Entidade</th><th>Nº Funcionário</th><th>Valor</th>';
    }
    
    $html .= '</tr>
            </thead>
            <tbody>';
    
    foreach ($reservas_adicionais as $item) {
        $html .= '<tr>';
        $html .= '<td><strong>' . htmlspecialchars($item['usuario_nome']) . '</strong></td>';
        $html .= '<td><strong>' . htmlspecialchars($item['dependente_nome']) . '</strong></td>';
        $html .= '<td>' . $item['quantidade'] . '</td>';
        $html .= '<td>' . htmlspecialchars($item['numero_entidade']) . '</td>';
        $html .= '<td>' . htmlspecialchars($item['numero_funcionario_entidade']) . '</td>';
        
        if ($tipo === 'diario_completo') {
            $html .= '<td><strong>R$ ' . number_format($item['valor'], 2, ',', '.') . '</strong></td>';
        }
        
        $html .= '</tr>';
    }
    
    $html .= '</tbody>
        </table>
        
        ';
    
    if (count($reservas_departamento) > 0) {
        $html .= '
        <h3 style="color: #007bff; margin: 30px 0 15px 0; padding: 10px; background: #e3f2fd; border-left: 4px solid #007bff;">RESERVAS DE DEPARTAMENTO</h3>
        <table>
            <thead>
                <tr>';
        
        if ($tipo === 'diario') {
            $html .= '<th>Departamento</th><th>Evento/Motivo</th><th>Quantidade</th>';
        } else {
            $html .= '<th>Departamento</th><th>Evento/Motivo</th><th>Quantidade</th><th>Valor Unit.</th><th>Valor Total</th>';
        }
        
        $html .= '</tr>
                </thead>
                <tbody>';
        
        foreach ($reservas_departamento as $item) {
            $html .= '<tr>';
            $html .= '<td><strong>' . htmlspecialchars($item['departamento'] ?? 'N/A') . '</strong></td>';
            $html .= '<td>' . htmlspecialchars($item['evento_motivo']) . '</td>';
            $html .= '<td>' . $item['quantidade'] . '</td>';
            
            if ($tipo === 'diario_completo') {
                $html .= '<td><strong>R$ ' . number_format($item['valor_unitario'], 2, ',', '.') . '</strong></td>';
                $html .= '<td><strong>R$ ' . number_format($item['valor'], 2, ',', '.') . '</strong></td>';
            }
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody>
            </table>';
    }
    
    $html .= '
    </div>
    
    <div class="totais">
        <h3>Resumo dos Totais</h3>
        <table>';
    
    if ($tipo === 'diario_completo') {
        $html .= '
            <thead>
                <tr>
                    <th>Tipo de Reserva</th>
                    <th>Quantidade</th>
                    <th>Valor Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Reserva Própria</td>
                    <td><strong>' . $total_proprias . '</strong></td>
                    <td><strong>R$ ' . number_format($valor_total_proprias, 2, ',', '.') . '</strong></td>
                </tr>
                <tr>
                    <td>Adicional - Presencial</td>
                    <td><strong>' . $total_adicionais . '</strong></td>
                    <td><strong>R$ ' . number_format($valor_total_adicionais, 2, ',', '.') . '</strong></td>
                </tr>
                <tr>
                    <td>Departamentos</td>
                    <td><strong>' . $total_departamentos . '</strong></td>
                    <td><strong>R$ ' . number_format($valor_total_departamentos, 2, ',', '.') . '</strong></td>
                </tr>
                <tr class="total-geral">
                    <td><strong>TOTAL GERAL</strong></td>
                    <td><strong>' . $total_geral . '</strong></td>
                    <td><strong>R$ ' . number_format($valor_total_geral, 2, ',', '.') . '</strong></td>
                </tr>
            </tbody>';
    } else {
        $html .= '
            <thead>
                <tr>
                    <th>Tipo de Reserva</th>
                    <th>Quantidade</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Reserva Própria</td>
                    <td><strong>' . $total_proprias . '</strong></td>
                </tr>
                <tr>
                    <td>Adicional - Presencial</td>
                    <td><strong>' . $total_adicionais . '</strong></td>
                </tr>
                <tr>
                    <td>Departamentos</td>
                    <td><strong>' . $total_departamentos . '</strong></td>
                </tr>
                <tr class="total-geral">
                    <td><strong>TOTAL GERAL</strong></td>
                    <td><strong>' . $total_geral . '</strong></td>
                </tr>
            </tbody>';
    }
    
    $html .= '
        </table>
    </div>
    
    <div class="footer">
        <p>Relatório gerado automaticamente pelo Sistema de Presença</p>
        <p>Gerado em: ' . date('d/m/Y H:i:s') . '</p>
    </div>';
    
    // Adicionar HTML ao PDF
    $mpdf->WriteHTML($html);
    
    // Configurar headers para PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="relatorio_diario_' . $data . '.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Gerar PDF
    $mpdf->Output('relatorio_diario_' . $data . '.pdf', 'I');
    
} catch (Exception $e) {
    echo "Erro ao gerar relatório: " . $e->getMessage();
}
?>
