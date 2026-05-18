<?php
require_once '../conexao.php';
include_once(__DIR__ . '/../../auth/verifica_sessao.php');

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit;
}

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

// Parâmetros
$data_inicio = $_GET['inicio'] ?? $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['fim'] ?? $_GET['data_fim'] ?? date('Y-m-d');

// Incluir mPDF
require_once('../../vendor/autoload.php');

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

// Buscar dados agregados por usuário
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

// HTML do relatório - Modelo Simples e Limpo
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            font-size: 12px; 
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .period {
            font-size: 12px;
            color: #888;
            background: #f5f5f5;
            padding: 8px 15px;
            border-radius: 4px;
            display: inline-block;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 11px;
        }
        
        th {
            background: #333;
            color: white;
            padding: 12px 8px;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
        }
        
        td {
            padding: 10px 8px;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
            font-size: 10px;
        }
        
        .user-name {
            text-align: left;
            font-weight: bold;
            color: #333;
        }
        
        .number {
            font-weight: bold;
            color: #333;
        }
        
        .currency {
            font-weight: bold;
            color: #28a745;
        }
        
        .total-row {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .total-row td {
            border-top: 2px solid #333;
            font-size: 12px;
        }
        
        .footer {
            text-align: center;
            font-size: 10px;
            color: #666;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">Relatório de Refeições</h1>
        <p class="subtitle">Sistema de Gestão de Presença - AOM</p>
        <div class="period">Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim)) . '</div>
    </div>';

$total_geral_qtd = 0;
$total_geral_valor = 0;
$total_proprias = 0;
$total_adicionais = 0;
$usuarios_count = 0;

// Processar dados por usuário
while ($row = $result->fetch_assoc()) {
    $total_geral_qtd += $row['total_geral_usuario'];
    $total_geral_valor += $row['valor_total_usuario'];
    $total_proprias += $row['qtd_proprias'];
    $total_adicionais += $row['qtd_adicionais'];
    $usuarios_count++;
}

// Reset result para usar novamente
$stmt->execute();
$result = $stmt->get_result();

$html .= '
    <table>
        <thead>
            <tr>
                <th>Usuário</th>
                <th>Nº Entidade</th>
                <th>Nº Funcionário</th>
                <th>Próprias</th>
                <th>Valor Próprias</th>
                <th>Adicionais</th>
                <th>Valor Adicionais</th>
                <th>Total Qtd</th>
                <th>Total Valor</th>
            </tr>
        </thead>
        <tbody>';

// Processar dados por usuário
while ($row = $result->fetch_assoc()) {
    // Consultar dados do funcionário na API
    $dadosFuncionario = consultarFuncionarioAPI($row['cpf']);
    $numero_entidade = $dadosFuncionario['Numero_Entidade'] ?? 'N/A';
    $numero_funcionario = $dadosFuncionario['Numero_Funcionario_Entidade'] ?? 'N/A';
    
    $html .= '<tr>
        <td class="user-name">' . htmlspecialchars($row['nome']) . '</td>
        <td><span class="number">' . $numero_entidade . '</span></td>
        <td><span class="number">' . $numero_funcionario . '</span></td>
        <td><span class="number">' . $row['qtd_proprias'] . '</span></td>
        <td><span class="currency">R$ ' . number_format($row['valor_proprias'], 2, ',', '.') . '</span></td>
        <td><span class="number">' . $row['qtd_adicionais'] . '</span></td>
        <td><span class="currency">R$ ' . number_format($row['valor_adicionais'], 2, ',', '.') . '</span></td>
        <td><span class="number">' . $row['total_geral_usuario'] . '</span></td>
        <td><span class="currency">R$ ' . number_format($row['valor_total_usuario'], 2, ',', '.') . '</span></td>
    </tr>';
}

$html .= '
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td><strong>TOTAL GERAL</strong></td>
                <td><strong>-</strong></td>
                <td><strong>-</strong></td>
                <td><strong>' . $total_proprias . '</strong></td>
                <td><strong>R$ ' . number_format($total_proprias * 10, 2, ',', '.') . '</strong></td>
                <td><strong>' . $total_adicionais . '</strong></td>
                <td><strong>R$ ' . number_format($total_adicionais * 10, 2, ',', '.') . '</strong></td>
                <td><strong>' . $total_geral_qtd . '</strong></td>
                <td><strong>R$ ' . number_format($total_geral_valor, 2, ',', '.') . '</strong></td>
            </tr>
        </tfoot>
    </table>
    
    <div class="footer">
        Relatório gerado em ' . date('d/m/Y H:i:s') . ' | Sistema AOM
    </div>
</body>
</html>';

$stmt->close();

// Gerar PDF
$mpdf->WriteHTML($html);
$nome_arquivo = 'relatorio_mensal_' . date('Y-m-d_H-i-s') . '.pdf';
$mpdf->Output($nome_arquivo, 'I');
exit;
?>