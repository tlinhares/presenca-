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

// Resumo das reservas adicionais POR DEPENDENTE (pedido do admin 21/08):
// "10 reservas para Pedro (menor de 12 — não cobra), 4 para Maria (maior)".
// Idade calculada NA DATA de cada reserva (não hoje) e SEM filtro de
// dependente ativo — reserva histórica de dependente inativado continua
// aparecendo (o valor dela já está no total).
function buscarResumoDependentes($usuario_id, $data_inicio, $data_fim, $conn) {
    $sql = "SELECT d.nome,
                   SUM(ra.quantidade) qtd,
                   SUM(CASE WHEN d.nascimento IS NOT NULL
                             AND TIMESTAMPDIFF(YEAR, d.nascimento, ra.data) <= 12
                        THEN ra.quantidade ELSE 0 END) qtd_isenta,
                   SUM(ra.valor_refeicao + COALESCE(ra.valor_marmitex, 0)) valor
              FROM reservas_adicionais ra
        INNER JOIN dependentes d ON ra.id_dependente = d.id
             WHERE ra.id_usuario = ? AND ra.data BETWEEN ? AND ?
          GROUP BY d.id, d.nome
          ORDER BY d.nome";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $usuario_id, $data_inicio, $data_fim);
    $stmt->execute();
    $result = $stmt->get_result();
    $deps = [];
    while ($row = $result->fetch_assoc()) $deps[] = $row;
    $stmt->close();
    return $deps;
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
            u.id as usuario_id,
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
                SUM(valor_refeicao + COALESCE(valor_marmitex, 0)) as total_valor
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
        body { font-family: Arial, sans-serif; color: #1f2937; font-size: 11px; }

        /* Cabeçalho institucional */
        .header { background: #1d4e8f; color: #fff; padding: 16px 20px; margin-bottom: 14px; }
        .title { font-size: 20px; font-weight: bold; margin: 0; }
        .subtitle { font-size: 11px; margin: 3px 0 0; color: #c8d8ee; }
        .period { font-size: 11px; color: #eaf1fa; margin-top: 6px; }

        /* Cartões de resumo */
        .cards { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 14px; }
        .cards td { width: 25%; background: #f4f6f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; text-align: center; }
        .cards .lab { font-size: 8.5px; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
        .cards .val { font-size: 15px; font-weight: bold; color: #1d4e8f; margin-top: 2px; }

        table.dados { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 10px; }
        table.dados th {
            background: #1d4e8f; color: #fff; padding: 8px 6px; font-size: 9.5px;
            text-transform: uppercase; letter-spacing: .3px;
        }
        table.dados th.esq, table.dados td.user-name { text-align: left; }
        table.dados th.num, table.dados td.qtd { text-align: center; }
        table.dados th.dinheiro, table.dados td.money { text-align: right; }
        table.dados td { padding: 7px 6px; border-bottom: 1px solid #e2e8f0; }
        table.dados tr.zebra td { background: #f8fafc; }

        .user-name { font-weight: bold; color: #1f2937; }
        .qtd { color: #334155; }
        .money { color: #1f2937; }
        .money.total { font-weight: bold; color: #1d4e8f; }

        /* Sub-linha dos dependentes: texto discreto, sem "etiquetas" coloridas */
        .resumo-reservas td {
            background: #fbfcfe; color: #475569; font-size: 8.8px;
            padding: 4px 6px 7px 24px; border-bottom: 1px solid #e2e8f0;
        }
        .dep-isento { color: #0f766e; }
        .dep-cobra  { color: #92400e; font-weight: bold; }

        .total-row td {
            background: #eaf1fa; border-top: 2px solid #1d4e8f; border-bottom: none;
            font-weight: bold; font-size: 10.5px; padding: 9px 6px;
        }

        .footer { text-align: center; font-size: 9px; color: #94a3b8; margin-top: 24px; padding-top: 12px; border-top: 1px solid #e2e8f0; }
        h3.secao { font-size: 13px; color: #1d4e8f; border-bottom: 2px solid #1d4e8f; padding-bottom: 6px; margin: 18px 0 8px; }
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
$valor_total_proprias = 0;
$valor_total_adicionais = 0;
$usuarios_count = 0;

// Armazenar dados em array para evitar re-executar query
$dados_usuarios = [];

// Processar dados por usuário e armazenar
while ($row = $result->fetch_assoc()) {
    $total_geral_qtd += $row['total_geral_usuario'];
    $total_geral_valor += $row['valor_total_usuario'];
    $total_proprias += $row['qtd_proprias'];
    $total_adicionais += $row['qtd_adicionais'];
    $valor_total_proprias += $row['valor_proprias'];
    $valor_total_adicionais += $row['valor_adicionais'];
    $usuarios_count++;
    
    // Armazenar para uso posterior
    $dados_usuarios[] = $row;
}

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

$total_geral_qtd += $total_departamentos;
$total_geral_valor += $valor_total_departamentos;

$html .= '
    <table class="cards">
        <tr>
            <td><div class="lab">Funcionários</div><div class="val">' . $usuarios_count . '</div></td>
            <td><div class="lab">Refeições Próprias</div><div class="val">' . $total_proprias . ' &middot; R$ ' . number_format($valor_total_proprias, 2, ',', '.') . '</div></td>
            <td><div class="lab">Adicionais (dependentes)</div><div class="val">' . $total_adicionais . ' &middot; R$ ' . number_format($valor_total_adicionais, 2, ',', '.') . '</div></td>
            <td><div class="lab">Total Geral</div><div class="val">' . $total_geral_qtd . ' &middot; R$ ' . number_format($total_geral_valor, 2, ',', '.') . '</div></td>
        </tr>
    </table>
    <table class="dados">
        <thead>
            <tr>
                <th class="esq">Usuário</th>
                <th class="num">Nº Entidade</th>
                <th class="num">Nº Func.</th>
                <th class="num">Próprias</th>
                <th class="dinheiro">Valor Próprias</th>
                <th class="num">Adicionais</th>
                <th class="dinheiro">Valor Adicionais</th>
                <th class="num">Qtd</th>
                <th class="dinheiro">Total</th>
            </tr>
        </thead>
        <tbody>';

// Processar dados por usuário (usando array em vez de re-executar query)
foreach ($dados_usuarios as $row) {
    // Consultar dados do funcionário na API
    $dadosFuncionario = consultarFuncionarioAPI($row['cpf']);
    $numero_entidade = $dadosFuncionario['Numero_Entidade'] ?? 'N/A';
    $numero_funcionario = $dadosFuncionario['Numero_Funcionario_Entidade'] ?? 'N/A';
    
    // Linha principal do usuário (zebra alternada)
    static $linha_par = false;
    $linha_par = !$linha_par;
    $html .= '<tr' . ($linha_par ? '' : ' class="zebra"') . '>
        <td class="user-name">' . htmlspecialchars($row['nome']) . '</td>
        <td class="qtd">' . htmlspecialchars($numero_entidade) . '</td>
        <td class="qtd">' . htmlspecialchars($numero_funcionario) . '</td>
        <td class="qtd">' . $row['qtd_proprias'] . '</td>
        <td class="money">R$ ' . number_format($row['valor_proprias'], 2, ',', '.') . '</td>
        <td class="qtd">' . $row['qtd_adicionais'] . '</td>
        <td class="money">R$ ' . number_format($row['valor_adicionais'], 2, ',', '.') . '</td>
        <td class="qtd">' . $row['total_geral_usuario'] . '</td>
        <td class="money total">R$ ' . number_format($row['valor_total_usuario'], 2, ',', '.') . '</td>
    </tr>';
    
    // Buscar reservas adicionais por idade
    if ($row['qtd_adicionais'] > 0) {
        $resumo_deps = buscarResumoDependentes($row['usuario_id'], $data_inicio, $data_fim, $conn);
        
        // Linha de resumo das reservas adicionais por idade
        $resumo_parts = [];
        foreach ($resumo_deps as $dep) {
            $qtd     = (int) $dep['qtd'];
            $isenta  = (int) $dep['qtd_isenta'];
            $nomeDep = htmlspecialchars($dep['nome']);
            if ($isenta >= $qtd) {
                $resumo_parts[] = '<span class="dep-isento">' . $qtd . '&times; ' . $nomeDep . ' — isento (menor de 12)</span>';
            } elseif ($isenta === 0) {
                $resumo_parts[] = '<span class="dep-cobra">' . $qtd . '&times; ' . $nomeDep . ' — R$ ' . number_format((float) $dep['valor'], 2, ',', '.') . '</span>';
            } else {
                $cobradas = $qtd - $isenta;
                $resumo_parts[] = '<span class="dep-cobra">' . $nomeDep . ' — ' . $cobradas . '&times; R$ ' . number_format((float) $dep['valor'], 2, ',', '.') . '</span> <span class="dep-isento">+ ' . $isenta . '&times; isenta(s)</span>';
            }
        }
        
        if (!empty($resumo_parts)) {
            $html .= '<tr class="resumo-reservas">
                <td colspan="9">&#9492; Dependentes: ' . implode(' &nbsp;&middot;&nbsp; ', $resumo_parts) . '</td>
            </tr>';
        }
    }
}

$html .= '
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td><strong>TOTAL USUÁRIOS</strong></td>
                <td><strong>-</strong></td>
                <td><strong>-</strong></td>
                <td><strong>' . $total_proprias . '</strong></td>
                <td><strong>R$ ' . number_format($valor_total_proprias, 2, ',', '.') . '</strong></td>
                <td><strong>' . $total_adicionais . '</strong></td>
                <td><strong>R$ ' . number_format($valor_total_adicionais, 2, ',', '.') . '</strong></td>
                <td><strong>' . ($total_proprias + $total_adicionais) . '</strong></td>
                <td><strong>R$ ' . number_format($valor_total_proprias + $valor_total_adicionais, 2, ',', '.') . '</strong></td>
            </tr>
        </tfoot>
    </table>';

if (count($reservas_departamento) > 0) {
    $html .= '
    <div style="margin-top: 20px;">
        <h3 class="secao">Reservas de Departamento</h3>
        <table class="dados">
            <thead>
                <tr>
                    <th>Departamento</th>
                    <th>Evento/Motivo</th>
                    <th>Data</th>
                    <th>Quantidade</th>
                    <th>Valor Unit.</th>
                    <th>Valor Total</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($reservas_departamento as $item_dept) {
        $html .= '<tr>
            <td class="user-name">' . htmlspecialchars($item_dept['departamento'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($item_dept['evento_motivo']) . '</td>
            <td>' . date('d/m/Y', strtotime($item_dept['data'])) . '</td>
            <td><span class="number">' . $item_dept['quantidade'] . '</span></td>
            <td><span class="currency">R$ ' . number_format($item_dept['valor_unitario'], 2, ',', '.') . '</span></td>
            <td><span class="currency">R$ ' . number_format($item_dept['valor'], 2, ',', '.') . '</span></td>
        </tr>';
    }
    
    $html .= '
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3"><strong>TOTAL DEPARTAMENTOS</strong></td>
                    <td><strong>' . $total_departamentos . '</strong></td>
                    <td><strong>-</strong></td>
                    <td><strong>R$ ' . number_format($valor_total_departamentos, 2, ',', '.') . '</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>';
}

$html .= '
    <div style="margin-top: 20px; background: #333; color: white; padding: 12px; border-radius: 4px; text-align: center;">
        <strong>TOTAL GERAL: ' . $total_geral_qtd . ' refeições | R$ ' . number_format($total_geral_valor, 2, ',', '.') . '</strong>
    </div>
    
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