<?php
/**
 * API - Gerar PDF da Requisição Autorizada
 */
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAcesso('estoque_requisicoes');

require_once(__DIR__ . '/../../../vendor/autoload.php');

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Buscar requisição
    $sql = "SELECT 
                r.id,
                r.numero,
                r.status,
                r.prioridade,
                r.motivo,
                r.finalidade,
                r.observacoes_solicitante,
                r.observacoes_aprovador,
                r.data_solicitacao,
                r.data_necessidade,
                r.data_aprovacao,
                DATE_FORMAT(r.data_solicitacao, '%d/%m/%Y %H:%i') as data_solicitacao_formatada,
                DATE_FORMAT(r.data_aprovacao, '%d/%m/%Y %H:%i') as data_aprovacao_formatada,
                do.nome as departamento_origem,
                dd.nome as departamento_destino,
                us.nome as solicitante,
                ua.nome as aprovador
            FROM estoque_requisicoes r
            LEFT JOIN estoque_departamentos do ON r.id_departamento_origem = do.id
            JOIN estoque_departamentos dd ON r.id_departamento_destino = dd.id
            JOIN usuarios us ON r.id_solicitante = us.id
            LEFT JOIN usuarios ua ON r.id_aprovador = ua.id
            WHERE r.id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Requisição não encontrada');
    }
    
    $requisicao = $result->fetch_assoc();
    
    // Verificar se está aprovada
    if ($requisicao['status'] !== 'aprovada' && $requisicao['status'] !== 'parcial' && $requisicao['status'] !== 'entregue') {
        throw new Exception('Apenas requisições aprovadas podem ser impressas');
    }
    
    // Formatar número da requisição
    if (empty($requisicao['numero'])) {
        $requisicao['numero'] = 'REQ-' . str_pad($requisicao['id'], 6, '0', STR_PAD_LEFT);
    }
    
    // Buscar itens
    $sql_itens = "SELECT 
                    ri.id,
                    ri.quantidade_solicitada,
                    ri.quantidade_aprovada,
                    ri.quantidade_entregue,
                    ri.observacoes,
                    ri.status as status_item,
                    p.nome as produto_nome,
                    p.codigo as produto_codigo,
                    u.sigla as unidade_sigla,
                    u.nome as unidade_nome,
                    c.nome as categoria_nome
                FROM estoque_requisicoes_itens ri
                JOIN estoque_produtos p ON ri.id_produto = p.id
                JOIN estoque_unidades u ON p.id_unidade = u.id
                LEFT JOIN estoque_categorias c ON p.id_categoria = c.id
                WHERE ri.id_requisicao = ?
                ORDER BY p.nome";
    
    $stmt_itens = $conn->prepare($sql_itens);
    if (!$stmt_itens) {
        throw new Exception('Erro ao preparar consulta de itens: ' . $conn->error);
    }
    $stmt_itens->bind_param("i", $id);
    $stmt_itens->execute();
    $result_itens = $stmt_itens->get_result();
    
    $itens = [];
    $total_itens = 0;
    while ($item = $result_itens->fetch_assoc()) {
        $itens[] = $item;
        $total_itens++;
    }
    
    $stmt->close();
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
    
    // Mapear status e prioridade
    $status_labels = [
        'pendente' => 'Pendente',
        'aprovada' => 'Aprovada',
        'parcial' => 'Parcialmente Entregue',
        'entregue' => 'Entregue',
        'cancelada' => 'Cancelada',
        'rejeitada' => 'Rejeitada'
    ];
    
    $prioridade_labels = [
        'baixa' => 'Baixa',
        'normal' => 'Normal',
        'alta' => 'Alta',
        'urgente' => 'Urgente'
    ];
    
    $status_texto = $status_labels[$requisicao['status']] ?? $requisicao['status'];
    $prioridade_texto = $prioridade_labels[$requisicao['prioridade']] ?? $requisicao['prioridade'];
    
    // Gerar HTML
    $html = '
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24pt;
            font-weight: bold;
        }
        .header h2 {
            margin: 5px 0;
            font-size: 18pt;
            color: #666;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .info-row-dupla {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .info-coluna {
            display: table-cell;
            width: 50%;
            padding-right: 20px;
        }
        .info-coluna:last-child {
            padding-right: 0;
        }
        .info-label {
            display: table-cell;
            width: 30%;
            font-weight: bold;
            color: #555;
        }
        .info-value {
            display: table-cell;
            width: 70%;
        }
        .info-label-inline {
            font-weight: bold;
            color: #555;
            margin-right: 5px;
        }
        .info-value-inline {
            margin-right: 25px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 10pt;
        }
        .status-aprovada {
            background-color: #c6f6d5;
            color: #22543d;
        }
        .status-parcial {
            background-color: #bee3f8;
            color: #2a4365;
        }
        .status-entregue {
            background-color: #9ae6b4;
            color: #1c4532;
        }
        .prioridade-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 10pt;
            margin-left: 10px;
        }
        .prioridade-baixa { background-color: #718096; color: white; }
        .prioridade-normal { background-color: #3182ce; color: white; }
        .prioridade-alta { background-color: #dd6b20; color: white; }
        .prioridade-urgente { background-color: #e53e3e; color: white; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 20px;
        }
        th {
            background-color: #333;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            font-size: 10pt;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            font-size: 10pt;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .observacoes {
            margin-top: 20px;
            padding: 10px;
            background-color: #f5f5f5;
            border-left: 4px solid #333;
        }
        .observacoes h4 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        .assinaturas {
            margin-top: 40px;
            width: 100%;
        }
        .assinaturas-table {
            width: 100%;
            border-collapse: collapse;
        }
        .assinatura-box {
            width: 50%;
            padding: 20px;
            text-align: center;
            border-top: 2px solid #333;
            vertical-align: top;
        }
        .assinatura-box:first-child {
            padding-right: 30px;
        }
        .assinatura-box:last-child {
            padding-left: 30px;
        }
        .assinatura-nome {
            margin-top: 50px;
            font-weight: bold;
        }
        .assinatura-cargo {
            margin-top: 5px;
            font-size: 9pt;
            color: #666;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 9pt;
            color: #666;
        }
    </style>
    
    <div class="header">
        <h1>REQUISIÇÃO DE MATERIAL</h1>
        <h2>Nº ' . htmlspecialchars($requisicao['numero']) . '</h2>
    </div>
    
    <div class="info-section">
        <div class="info-row">
            <div class="info-label">Status:</div>
            <div class="info-value">
                <span class="status-badge status-' . htmlspecialchars($requisicao['status']) . '">' . htmlspecialchars($status_texto) . '</span>
                <span class="prioridade-badge prioridade-' . htmlspecialchars($requisicao['prioridade']) . '">Prioridade: ' . htmlspecialchars($prioridade_texto) . '</span>
            </div>
        </div>
        <div class="info-row-dupla">
            <div class="info-coluna">
                <span class="info-label-inline">Data da Solicitação:</span>
                <span class="info-value-inline">' . htmlspecialchars($requisicao['data_solicitacao_formatada']) . '</span>
            </div>';
    
    if ($requisicao['data_aprovacao']) {
        $html .= '
            <div class="info-coluna">
                <span class="info-label-inline">Data da Aprovação:</span>
                <span class="info-value-inline">' . htmlspecialchars($requisicao['data_aprovacao_formatada']) . '</span>
            </div>';
    } else {
        if ($requisicao['data_necessidade']) {
            $html .= '
            <div class="info-coluna">
                <span class="info-label-inline">Data de Necessidade:</span>
                <span class="info-value-inline">' . htmlspecialchars(date('d/m/Y', strtotime($requisicao['data_necessidade']))) . '</span>
            </div>';
        } else {
            $html .= '<div class="info-coluna"></div>';
        }
    }
    
    $html .= '
        </div>';
    
    if ($requisicao['data_aprovacao'] && $requisicao['data_necessidade']) {
        $html .= '
        <div class="info-row-dupla">
            <div class="info-coluna">
                <span class="info-label-inline">Data de Necessidade:</span>
                <span class="info-value-inline">' . htmlspecialchars(date('d/m/Y', strtotime($requisicao['data_necessidade']))) . '</span>
            </div>
            <div class="info-coluna"></div>
        </div>';
    }
    
    $html .= '
        <div class="info-row-dupla">
            <div class="info-coluna">
                <span class="info-label-inline">Solicitante:</span>
                <span class="info-value-inline">' . htmlspecialchars($requisicao['solicitante']) . '</span>
            </div>
            <div class="info-coluna">
                <span class="info-label-inline">Departamento:</span>
                <span class="info-value-inline">' . htmlspecialchars($requisicao['departamento_destino']) . '</span>
            </div>
        </div>';
    
    if ($requisicao['aprovador'] || $requisicao['finalidade']) {
        $html .= '
        <div class="info-row-dupla">
            <div class="info-coluna">';
        
        if ($requisicao['aprovador']) {
            $html .= '
                <span class="info-label-inline">Aprovador:</span>
                <span class="info-value-inline">' . htmlspecialchars($requisicao['aprovador']) . '</span>';
        } else {
            $html .= '<span></span>';
        }
        
        $html .= '
            </div>
            <div class="info-coluna">';
        
        if ($requisicao['finalidade']) {
            $html .= '
                <span class="info-label-inline">Finalidade:</span>
                <span class="info-value-inline">' . htmlspecialchars($requisicao['finalidade']) . '</span>';
        } else {
            $html .= '<span></span>';
        }
        
        $html .= '
            </div>
        </div>';
    }
    
    if ($requisicao['motivo']) {
        $html .= '
        <div class="info-row">
            <div class="info-label">Motivo:</div>
            <div class="info-value">' . htmlspecialchars($requisicao['motivo']) . '</div>
        </div>';
    }
    
    $html .= '
    </div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 40%;">Produto</th>
                <th style="width: 15%;" class="text-center">Código</th>
                <th style="width: 15%;" class="text-center">Solicitado</th>
                <th style="width: 15%;" class="text-center">Aprovado</th>
                <th style="width: 10%;" class="text-center">Unidade</th>
            </tr>
        </thead>
        <tbody>';
    
    $contador = 1;
    foreach ($itens as $item) {
        $solicitado = number_format($item['quantidade_solicitada'], 2, ',', '.');
        $aprovado = $item['quantidade_aprovada'] ? number_format($item['quantidade_aprovada'], 2, ',', '.') : '-';
        
        $html .= '
            <tr>
                <td>' . $contador . '</td>
                <td><strong>' . htmlspecialchars($item['produto_nome']) . '</strong><br><small style="color: #666;">' . htmlspecialchars($item['categoria_nome'] ?? 'Sem categoria') . '</small></td>
                <td class="text-center">' . htmlspecialchars($item['produto_codigo'] ?? '-') . '</td>
                <td class="text-center">' . $solicitado . '</td>
                <td class="text-center">' . $aprovado . '</td>
                <td class="text-center">' . htmlspecialchars($item['unidade_sigla']) . '</td>
            </tr>';
        $contador++;
    }
    
    $html .= '
        </tbody>
    </table>
    
    <div class="info-row">
        <div class="info-label"><strong>Total de Itens:</strong></div>
        <div class="info-value"><strong>' . $total_itens . '</strong></div>
    </div>';
    
    if ($requisicao['observacoes_solicitante']) {
        $html .= '
    <div class="observacoes">
        <h4>Observações do Solicitante:</h4>
        <p>' . nl2br(htmlspecialchars($requisicao['observacoes_solicitante'])) . '</p>
    </div>';
    }
    
    if ($requisicao['observacoes_aprovador']) {
        $html .= '
    <div class="observacoes">
        <h4>Observações do Aprovador:</h4>
        <p>' . nl2br(htmlspecialchars($requisicao['observacoes_aprovador'])) . '</p>
    </div>';
    }
    
    $html .= '
    <table class="assinaturas-table" style="margin-top: 40px;">
        <tr>
            <td class="assinatura-box" style="width: 50%; padding-right: 30px; text-align: center; border-top: 2px solid #333; vertical-align: top;">
                <div style="margin-top: 50px; font-weight: bold;">' . htmlspecialchars($requisicao['solicitante']) . '</div>
                <div style="margin-top: 5px; font-size: 9pt; color: #666;">Solicitante</div>
            </td>
            <td class="assinatura-box" style="width: 50%; padding-left: 30px; text-align: center; border-top: 2px solid #333; vertical-align: top;">
                <div style="margin-top: 50px; font-weight: bold;">' . ($requisicao['aprovador'] ? htmlspecialchars($requisicao['aprovador']) : '_________________') . '</div>
                <div style="margin-top: 5px; font-size: 9pt; color: #666;">Gestor do Estoque</div>
            </td>
        </tr>
    </table>
    
    <div class="footer">
        <p>Documento gerado em ' . date('d/m/Y H:i:s') . ' pelo Sistema de Estoque</p>
        <p>Requisição #' . htmlspecialchars($requisicao['numero']) . ' - ' . htmlspecialchars($requisicao['departamento_destino']) . '</p>
    </div>';
    
    // Configurar PDF
    $mpdf->SetTitle('Requisição #' . $requisicao['numero']);
    $mpdf->SetAuthor('Sistema de Estoque');
    $mpdf->WriteHTML($html);
    
    // Gerar nome do arquivo
    $filename = 'requisicao_' . $requisicao['numero'] . '_' . date('Y-m-d') . '.pdf';
    
    // Output PDF
    $mpdf->Output($filename, 'I');
    
} catch (Exception $e) {
    error_log("Erro em requisicoes/pdf.php: " . $e->getMessage());
    die('Erro ao gerar PDF: ' . $e->getMessage());
}

$conn->close();

