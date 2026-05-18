<?php
/**
 * API - Gerar PDF do Inventário Finalizado
 */
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAcesso('estoque_inventario');

require_once(__DIR__ . '/../../../vendor/autoload.php');

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Buscar dados do inventário
    $sql = "SELECT 
                i.id,
                i.id_departamento,
                i.data_inicio,
                DATE_FORMAT(i.data_inicio, '%d/%m/%Y %H:%i') as data_inicio_formatada,
                i.data_fim,
                DATE_FORMAT(i.data_fim, '%d/%m/%Y %H:%i') as data_fim_formatada,
                i.status,
                i.observacoes,
                d.nome as departamento_nome,
                u_inicio.nome as responsavel_inicio,
                u_fim.nome as responsavel_fim
            FROM estoque_inventarios i
            JOIN estoque_departamentos d ON i.id_departamento = d.id
            LEFT JOIN usuarios u_inicio ON i.id_usuario_inicio = u_inicio.id
            LEFT JOIN usuarios u_fim ON i.id_usuario_fim = u_fim.id
            WHERE i.id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Inventário não encontrado');
    }
    
    $inventario = $result->fetch_assoc();
    
    // Verificar se está finalizado
    if ($inventario['status'] !== 'finalizado') {
        throw new Exception('Apenas inventários finalizados podem ser visualizados em PDF');
    }
    
    $stmt->close();
    
    // Buscar itens do inventário
    $sql_itens = "SELECT 
                    ii.id_produto,
                    ii.quantidade_sistema,
                    ii.quantidade_contada,
                    ii.diferenca,
                    p.codigo,
                    p.nome as produto_nome,
                    u.sigla as unidade_sigla
                  FROM estoque_inventarios_itens ii
                  JOIN estoque_produtos p ON ii.id_produto = p.id
                  JOIN estoque_unidades u ON p.id_unidade = u.id
                  WHERE ii.id_inventario = ?
                  ORDER BY p.nome ASC";
    
    $stmt_itens = $conn->prepare($sql_itens);
    if (!$stmt_itens) {
        throw new Exception('Erro ao preparar query de itens: ' . $conn->error);
    }
    
    $stmt_itens->bind_param("i", $id);
    $stmt_itens->execute();
    $result_itens = $stmt_itens->get_result();
    
    $itens = [];
    $total_produtos = 0;
    $total_com_diferenca = 0;
    $total_diferenca_positiva = 0;
    $total_diferenca_negativa = 0;
    
    while ($row = $result_itens->fetch_assoc()) {
        $itens[] = $row;
        $total_produtos++;
        if ($row['diferenca'] != 0) {
            $total_com_diferenca++;
            if ($row['diferenca'] > 0) {
                $total_diferenca_positiva += $row['diferenca'];
            } else {
                $total_diferenca_negativa += abs($row['diferenca']);
            }
        }
    }
    
    $stmt_itens->close();
    
    // Inicializar mPDF
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
    
    // Gerar HTML
    $html = '
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        .header { 
            text-align: center; 
            margin-bottom: 20px; 
            padding: 15px;
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
            border-radius: 8px;
        }
        .header h1 { margin: 0; font-size: 20pt; font-weight: bold; }
        .header p { margin: 5px 0 0 0; font-size: 11pt; }
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .info-label { font-weight: bold; color: #495057; }
        .info-value { color: #212529; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th {
            background-color: #fa709a;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            font-size: 9pt;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
            font-size: 9pt;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .diferenca-positiva { color: #28a745; font-weight: bold; }
        .diferenca-negativa { color: #dc3545; font-weight: bold; }
        .diferenca-zero { color: #6c757d; }
        .resumo {
            margin-top: 20px;
            padding: 15px;
            background: #e9ecef;
            border-radius: 5px;
        }
        .resumo h3 {
            margin-top: 0;
            font-size: 12pt;
            color: #495057;
        }
        .resumo-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 10pt;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #dee2e6;
            text-align: center;
            font-size: 8pt;
            color: #6c757d;
        }
    </style>
    
    <div class="header">
        <h1>RELATÓRIO DE INVENTÁRIO FÍSICO</h1>
        <p>Inventário #' . $inventario['id'] . ' - ' . htmlspecialchars($inventario['departamento_nome']) . '</p>
    </div>
    
    <div class="info-box">
        <div class="info-row">
            <span class="info-label">Departamento:</span>
            <span class="info-value">' . htmlspecialchars($inventario['departamento_nome']) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Data de Início:</span>
            <span class="info-value">' . $inventario['data_inicio_formatada'] . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Data de Finalização:</span>
            <span class="info-value">' . ($inventario['data_fim_formatada'] ? $inventario['data_fim_formatada'] : 'Não finalizado') . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Responsável (Início):</span>
            <span class="info-value">' . htmlspecialchars($inventario['responsavel_inicio'] ?? 'N/A') . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Responsável (Finalização):</span>
            <span class="info-value">' . htmlspecialchars($inventario['responsavel_fim'] ?? 'N/A') . '</span>
        </div>';
    
    if (!empty($inventario['observacoes'])) {
        $html .= '
        <div class="info-row">
            <span class="info-label">Observações:</span>
            <span class="info-value">' . nl2br(htmlspecialchars($inventario['observacoes'])) . '</span>
        </div>';
    }
    
    $html .= '
    </div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 15%;">Código</th>
                <th style="width: 35%;">Produto</th>
                <th style="width: 12%;">Unidade</th>
                <th style="width: 13%;">Qtd. Sistema</th>
                <th style="width: 13%;">Qtd. Contada</th>
                <th style="width: 13%;">Diferença</th>
            </tr>
        </thead>
        <tbody>';
    
    $contador = 1;
    foreach ($itens as $item) {
        $diferenca = floatval($item['diferenca']);
        $classe_diferenca = '';
        $sinal = '';
        
        if ($diferenca > 0) {
            $classe_diferenca = 'diferenca-positiva';
            $sinal = '+';
        } elseif ($diferenca < 0) {
            $classe_diferenca = 'diferenca-negativa';
        } else {
            $classe_diferenca = 'diferenca-zero';
        }
        
        $html .= '
            <tr>
                <td>' . $contador . '</td>
                <td>' . htmlspecialchars($item['codigo'] ?? '-') . '</td>
                <td>' . htmlspecialchars($item['produto_nome']) . '</td>
                <td>' . htmlspecialchars($item['unidade_sigla']) . '</td>
                <td>' . number_format($item['quantidade_sistema'], 2, ',', '.') . '</td>
                <td>' . ($item['quantidade_contada'] !== null ? number_format($item['quantidade_contada'], 2, ',', '.') : '-') . '</td>
                <td class="' . $classe_diferenca . '">' . ($item['quantidade_contada'] !== null ? $sinal . number_format($diferenca, 2, ',', '.') : '-') . '</td>
            </tr>';
        $contador++;
    }
    
    $html .= '
        </tbody>
    </table>
    
    <div class="resumo">
        <h3>RESUMO</h3>
        <div class="resumo-item">
            <span><strong>Total de Produtos:</strong></span>
            <span>' . $total_produtos . '</span>
        </div>
        <div class="resumo-item">
            <span><strong>Produtos com Diferença:</strong></span>
            <span>' . $total_com_diferenca . '</span>
        </div>
        <div class="resumo-item">
            <span><strong>Diferença Total Positiva:</strong></span>
            <span class="diferenca-positiva">+' . number_format($total_diferenca_positiva, 2, ',', '.') . '</span>
        </div>
        <div class="resumo-item">
            <span><strong>Diferença Total Negativa:</strong></span>
            <span class="diferenca-negativa">-' . number_format($total_diferenca_negativa, 2, ',', '.') . '</span>
        </div>
    </div>
    
    <div class="footer">
        <p>Relatório gerado em ' . date('d/m/Y H:i:s') . ' pelo Sistema de Estoque</p>
        <p>Inventário #' . $inventario['id'] . ' - ' . htmlspecialchars($inventario['departamento_nome']) . '</p>
    </div>';
    
    // Configurar PDF
    $mpdf->SetTitle('Inventário #' . $id . ' - ' . $inventario['departamento_nome']);
    $mpdf->SetAuthor('Sistema de Estoque');
    $mpdf->WriteHTML($html);
    
    // Gerar nome do arquivo
    $filename = 'inventario_' . $id . '_' . date('Y-m-d') . '.pdf';
    
    // Output PDF
    $mpdf->Output($filename, 'I');
    
} catch (Exception $e) {
    error_log("Erro em inventarios/pdf.php: " . $e->getMessage());
    die('Erro ao gerar PDF: ' . $e->getMessage());
}

$conn->close();

