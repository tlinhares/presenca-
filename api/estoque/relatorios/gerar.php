<?php
/**
 * API - Gerar Relatórios de Estoque
 */
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAcesso('estoque_relatorios');

require_once(__DIR__ . '/../../../vendor/autoload.php');

try {
    $tipo = $_GET['tipo'] ?? '';
    $formato = $_GET['formato'] ?? 'html';
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
    $departamento = isset($_GET['departamento']) && $_GET['departamento'] !== '' ? intval($_GET['departamento']) : null;
    $categoria = isset($_GET['categoria']) && $_GET['categoria'] !== '' ? intval($_GET['categoria']) : null;
    
    $tipos_validos = [
        'posicao_estoque',
        'estoque_baixo',
        'movimentacoes',
        'requisicoes',
        'por_categoria',
        'por_departamento',
        'valorizacao',
        'curva_abc'
    ];
    
    if (!in_array($tipo, $tipos_validos)) {
        throw new Exception('Tipo de relatório inválido');
    }
    
    // Buscar dados conforme o tipo
    $dados = buscarDadosRelatorio($tipo, $data_inicio, $data_fim, $departamento, $categoria, $conn);
    
    // Gerar HTML (será convertido para PDF)
    $html = gerarHTMLRelatorio($tipo, $dados, $data_inicio, $data_fim, $departamento, $categoria, $conn);
    
    // Sempre gerar PDF usando mPDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'L', // Paisagem para tabelas grandes
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'margin_header' => 10,
        'margin_footer' => 10
    ]);
    
    $titulo = obterTituloRelatorio($tipo);
    $mpdf->SetTitle($titulo);
    $mpdf->SetAuthor('Sistema de Estoque');
    $mpdf->WriteHTML($html);
    
    $filename = 'relatorio_' . $tipo . '_' . date('Y-m-d') . '.pdf';
    $mpdf->Output($filename, 'I');
    
} catch (Exception $e) {
    error_log("Erro em relatorios/gerar.php: " . $e->getMessage());
    die('Erro ao gerar relatório: ' . $e->getMessage());
}

function buscarDadosRelatorio($tipo, $data_inicio, $data_fim, $departamento, $categoria, $conn) {
    $dados = [];
    
    switch ($tipo) {
        case 'posicao_estoque':
            $sql = "SELECT 
                        p.id,
                        p.codigo,
                        p.nome,
                        p.quantidade_atual,
                        p.quantidade_minima,
                        p.valor_unitario,
                        p.valor_medio,
                        (p.quantidade_atual * p.valor_unitario) as valor_total,
                        d.nome as departamento,
                        c.nome as categoria,
                        u.sigla as unidade
                    FROM estoque_produtos p
                    JOIN estoque_departamentos d ON p.id_departamento = d.id
                    JOIN estoque_unidades u ON p.id_unidade = u.id
                    LEFT JOIN estoque_categorias c ON p.id_categoria = c.id
                    WHERE p.ativo = 1";
            
            $params = [];
            $types = "";
            
            if ($departamento) {
                $sql .= " AND p.id_departamento = ?";
                $params[] = $departamento;
                $types .= "i";
            }
            
            if ($categoria) {
                $sql .= " AND p.id_categoria = ?";
                $params[] = $categoria;
                $types .= "i";
            }
            
            $sql .= " ORDER BY p.nome ASC";
            
            $stmt = $conn->prepare($sql);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $dados[] = $row;
            }
            $stmt->close();
            break;
            
        case 'estoque_baixo':
            $sql = "SELECT 
                        p.id,
                        p.codigo,
                        p.nome,
                        p.quantidade_atual,
                        p.quantidade_minima,
                        p.quantidade_ideal,
                        d.nome as departamento,
                        c.nome as categoria,
                        u.sigla as unidade,
                        CASE 
                            WHEN p.quantidade_atual <= 0 THEN 'Zerado'
                            WHEN p.quantidade_atual <= p.quantidade_minima THEN 'Crítico'
                            ELSE 'Baixo'
                        END as nivel
                    FROM estoque_produtos p
                    JOIN estoque_departamentos d ON p.id_departamento = d.id
                    JOIN estoque_unidades u ON p.id_unidade = u.id
                    LEFT JOIN estoque_categorias c ON p.id_categoria = c.id
                    WHERE p.ativo = 1 
                    AND (p.quantidade_atual <= p.quantidade_minima OR p.quantidade_atual <= 0)";
            
            $params = [];
            $types = "";
            
            if ($departamento) {
                $sql .= " AND p.id_departamento = ?";
                $params[] = $departamento;
                $types .= "i";
            }
            
            if ($categoria) {
                $sql .= " AND p.id_categoria = ?";
                $params[] = $categoria;
                $types .= "i";
            }
            
            $sql .= " ORDER BY p.quantidade_atual ASC, p.nome ASC";
            
            $stmt = $conn->prepare($sql);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $dados[] = $row;
            }
            $stmt->close();
            break;
            
        case 'movimentacoes':
            $sql = "SELECT 
                        m.id,
                        m.tipo,
                        m.quantidade,
                        m.quantidade_anterior,
                        m.quantidade_posterior,
                        m.valor_unitario,
                        m.valor_total,
                        m.origem,
                        m.data_movimentacao,
                        DATE_FORMAT(m.data_movimentacao, '%d/%m/%Y %H:%i') as data_formatada,
                        p.nome as produto,
                        p.codigo,
                        d.nome as departamento,
                        us.nome as usuario,
                        u.sigla as unidade
                    FROM estoque_movimentacoes m
                    JOIN estoque_produtos p ON m.id_produto = p.id
                    JOIN estoque_departamentos d ON m.id_departamento = d.id
                    JOIN estoque_unidades u ON p.id_unidade = u.id
                    LEFT JOIN usuarios us ON m.id_usuario = us.id
                    WHERE DATE(m.data_movimentacao) BETWEEN ? AND ?";
            
            $params = [$data_inicio, $data_fim];
            $types = "ss";
            
            if ($departamento) {
                $sql .= " AND m.id_departamento = ?";
                $params[] = $departamento;
                $types .= "i";
            }
            
            $sql .= " ORDER BY m.data_movimentacao DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $dados[] = $row;
            }
            $stmt->close();
            break;
            
        case 'requisicoes':
            $sql = "SELECT 
                        r.id,
                        r.numero,
                        r.status,
                        r.prioridade,
                        r.data_solicitacao,
                        DATE_FORMAT(r.data_solicitacao, '%d/%m/%Y') as data_formatada,
                        d.nome as departamento_destino,
                        u.nome as solicitante,
                        (SELECT COUNT(*) FROM estoque_requisicoes_itens WHERE id_requisicao = r.id) as total_itens
                    FROM estoque_requisicoes r
                    JOIN estoque_departamentos d ON r.id_departamento_destino = d.id
                    JOIN usuarios u ON r.id_solicitante = u.id
                    WHERE DATE(r.data_solicitacao) BETWEEN ? AND ?";
            
            $params = [$data_inicio, $data_fim];
            $types = "ss";
            
            if ($departamento) {
                $sql .= " AND r.id_departamento_destino = ?";
                $params[] = $departamento;
                $types .= "i";
            }
            
            $sql .= " ORDER BY r.data_solicitacao DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $dados[] = $row;
            }
            $stmt->close();
            break;
            
        case 'por_categoria':
            $sql = "SELECT 
                        c.id,
                        c.nome as categoria,
                        COUNT(p.id) as total_produtos,
                        SUM(p.quantidade_atual) as quantidade_total,
                        SUM(p.quantidade_atual * p.valor_unitario) as valor_total
                    FROM estoque_categorias c
                    LEFT JOIN estoque_produtos p ON c.id = p.id_categoria AND p.ativo = 1";
            
            $params = [];
            $types = "";
            
            if ($departamento) {
                $sql .= " AND p.id_departamento = ?";
                $params[] = $departamento;
                $types .= "i";
            }
            
            $sql .= " GROUP BY c.id, c.nome
                    HAVING total_produtos > 0
                    ORDER BY valor_total DESC";
            
            $stmt = $conn->prepare($sql);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $dados[] = $row;
            }
            $stmt->close();
            break;
            
        case 'por_departamento':
            $sql = "SELECT 
                        d.id,
                        d.nome as departamento,
                        COUNT(p.id) as total_produtos,
                        SUM(p.quantidade_atual) as quantidade_total,
                        SUM(p.quantidade_atual * p.valor_unitario) as valor_total
                    FROM estoque_departamentos d
                    LEFT JOIN estoque_produtos p ON d.id = p.id_departamento AND p.ativo = 1
                    WHERE d.ativo = 1";
            
            $params = [];
            $types = "";
            
            if ($departamento) {
                $sql .= " AND d.id = ?";
                $params[] = $departamento;
                $types .= "i";
            }
            
            $sql .= " GROUP BY d.id, d.nome
                    HAVING total_produtos > 0
                    ORDER BY valor_total DESC";
            
            $stmt = $conn->prepare($sql);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $dados[] = $row;
            }
            $stmt->close();
            break;
            
        case 'valorizacao':
            $sql = "SELECT 
                        SUM(p.quantidade_atual * p.valor_unitario) as valor_total_estoque,
                        COUNT(p.id) as total_produtos,
                        SUM(p.quantidade_atual) as quantidade_total
                    FROM estoque_produtos p
                    WHERE p.ativo = 1";
            
            $params = [];
            $types = "";
            
            if ($departamento) {
                $sql .= " AND p.id_departamento = ?";
                $params[] = $departamento;
                $types .= "i";
            }
            
            if ($categoria) {
                $sql .= " AND p.id_categoria = ?";
                $params[] = $categoria;
                $types .= "i";
            }
            
            $stmt = $conn->prepare($sql);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $dados = $result->fetch_assoc();
            $stmt->close();
            break;
            
        case 'curva_abc':
            $sql = "SELECT 
                        p.id,
                        p.codigo,
                        p.nome,
                        p.quantidade_atual,
                        p.valor_unitario,
                        (p.quantidade_atual * p.valor_unitario) as valor_total,
                        d.nome as departamento,
                        c.nome as categoria
                    FROM estoque_produtos p
                    JOIN estoque_departamentos d ON p.id_departamento = d.id
                    LEFT JOIN estoque_categorias c ON p.id_categoria = c.id
                    WHERE p.ativo = 1";
            
            $params = [];
            $types = "";
            
            if ($departamento) {
                $sql .= " AND p.id_departamento = ?";
                $params[] = $departamento;
                $types .= "i";
            }
            
            if ($categoria) {
                $sql .= " AND p.id_categoria = ?";
                $params[] = $categoria;
                $types .= "i";
            }
            
            $sql .= " ORDER BY valor_total DESC";
            
            $stmt = $conn->prepare($sql);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $produtos = [];
            $valor_total_geral = 0;
            
            while ($row = $result->fetch_assoc()) {
                $produtos[] = $row;
                $valor_total_geral += floatval($row['valor_total']);
            }
            
            // Calcular percentual acumulado
            $acumulado = 0;
            foreach ($produtos as &$produto) {
                $acumulado += floatval($produto['valor_total']);
                $percentual = ($acumulado / $valor_total_geral) * 100;
                $produto['percentual_acumulado'] = $percentual;
                $produto['classe'] = $percentual <= 80 ? 'A' : ($percentual <= 95 ? 'B' : 'C');
            }
            
            $dados = $produtos;
            $stmt->close();
            break;
    }
    
    return $dados;
}

function obterTituloRelatorio($tipo) {
    $titulos = [
        'posicao_estoque' => 'Posição de Estoque',
        'estoque_baixo' => 'Estoque Baixo',
        'movimentacoes' => 'Movimentações',
        'requisicoes' => 'Requisições',
        'por_categoria' => 'Relatório por Categoria',
        'por_departamento' => 'Relatório por Departamento',
        'valorizacao' => 'Valorização do Estoque',
        'curva_abc' => 'Curva ABC'
    ];
    
    return $titulos[$tipo] ?? 'Relatório de Estoque';
}

function gerarHTMLRelatorio($tipo, $dados, $data_inicio, $data_fim, $departamento, $categoria, $conn) {
    $titulo = obterTituloRelatorio($tipo);
    
    // Buscar nomes de departamento e categoria se necessário
    $nome_departamento = '';
    if ($departamento) {
        $stmt = $conn->prepare("SELECT nome FROM estoque_departamentos WHERE id = ?");
        $stmt->bind_param("i", $departamento);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $nome_departamento = $row['nome'];
        }
        $stmt->close();
    }
    
    $nome_categoria = '';
    if ($categoria) {
        $stmt = $conn->prepare("SELECT nome FROM estoque_categorias WHERE id = ?");
        $stmt->bind_param("i", $categoria);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $nome_categoria = $row['nome'];
        }
        $stmt->close();
    }
    
    $html = '<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($titulo) . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; padding: 15px; background: white; }
        .header { background: #434343; color: white; padding: 15px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: bold; }
        .header p { margin: 5px 0 0 0; font-size: 11px; }
        .card { background: white; padding: 15px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9pt; }
        th { background: #434343; color: white; padding: 8px; text-align: left; font-weight: bold; font-size: 9pt; }
        td { padding: 6px; border-bottom: 1px solid #ddd; font-size: 9pt; }
        tfoot tr { background: #e9ecef; font-weight: bold; }
        .badge { padding: 3px 6px; border-radius: 3px; font-size: 9pt; display: inline-block; }
        .badge-success { background: #28a745; color: white; }
        .badge-warning { background: #ffc107; color: #212529; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-info { background: #17a2b8; color: white; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .resumo { background: #e9ecef; padding: 12px; margin-top: 15px; }
        .resumo h3 { margin-top: 0; font-size: 12pt; }
        .resumo h4 { margin-top: 0; font-size: 11pt; }
        .resumo-item { display: table; width: 100%; margin-bottom: 8px; }
        .resumo-item span { display: table-cell; }
        .resumo-item span:last-child { text-align: right; font-weight: bold; }
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; text-align: center; font-size: 8pt; color: #6c757d; }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . htmlspecialchars($titulo) . '</h1>
        <p>';
    
    if ($data_inicio && $data_fim) {
        $html .= 'Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim));
    }
    
    if ($nome_departamento) {
        $html .= ' | Departamento: ' . htmlspecialchars($nome_departamento);
    }
    
    if ($nome_categoria) {
        $html .= ' | Categoria: ' . htmlspecialchars($nome_categoria);
    }
    
    $html .= ' | Gerado em: ' . date('d/m/Y H:i:s') . '
        </p>
    </div>
    
    <div class="card">';
    
    // Gerar conteúdo específico por tipo
    switch ($tipo) {
        case 'posicao_estoque':
            $html .= gerarTabelaPosicaoEstoque($dados);
            break;
        case 'estoque_baixo':
            $html .= gerarTabelaEstoqueBaixo($dados);
            break;
        case 'movimentacoes':
            $html .= gerarTabelaMovimentacoes($dados);
            break;
        case 'requisicoes':
            $html .= gerarTabelaRequisicoes($dados);
            break;
        case 'por_categoria':
            $html .= gerarTabelaPorCategoria($dados);
            break;
        case 'por_departamento':
            $html .= gerarTabelaPorDepartamento($dados);
            break;
        case 'valorizacao':
            $html .= gerarResumoValorizacao($dados);
            break;
        case 'curva_abc':
            $html .= gerarTabelaCurvaABC($dados);
            break;
    }
    
    $html .= '
    </div>
    
    <div class="footer" style="margin-top: 30px; padding-top: 15px; border-top: 2px solid #dee2e6; text-align: center; font-size: 8pt; color: #6c757d;">
        <p>Relatório gerado em ' . date('d/m/Y H:i:s') . ' pelo Sistema de Estoque</p>
    </div>
</body>
</html>';
    
    return $html;
}

function gerarTabelaPosicaoEstoque($dados) {
    $html = '<table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Produto</th>
                <th>Departamento</th>
                <th>Categoria</th>
                <th class="text-right">Quantidade</th>
                <th class="text-right">Mínimo</th>
                <th class="text-right">Valor Unit.</th>
                <th class="text-right">Valor Total</th>
            </tr>
        </thead>
        <tbody>';
    
    $valor_total = 0;
    foreach ($dados as $item) {
        $valor_item = floatval($item['quantidade_atual']) * floatval($item['valor_unitario']);
        $valor_total += $valor_item;
        
        $html .= '<tr>
            <td>' . htmlspecialchars($item['codigo'] ?? '-') . '</td>
            <td>' . htmlspecialchars($item['nome']) . '</td>
            <td>' . htmlspecialchars($item['departamento']) . '</td>
            <td>' . htmlspecialchars($item['categoria'] ?? '-') . '</td>
            <td class="text-right">' . number_format($item['quantidade_atual'], 2, ',', '.') . ' ' . htmlspecialchars($item['unidade']) . '</td>
            <td class="text-right">' . number_format($item['quantidade_minima'], 2, ',', '.') . '</td>
            <td class="text-right">R$ ' . number_format($item['valor_unitario'], 2, ',', '.') . '</td>
            <td class="text-right">R$ ' . number_format($valor_item, 2, ',', '.') . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
        <tfoot>
            <tr style="background: #e9ecef; font-weight: bold;">
                <td colspan="7" class="text-right">TOTAL:</td>
                <td class="text-right">R$ ' . number_format($valor_total, 2, ',', '.') . '</td>
            </tr>
        </tfoot>
    </table>';
    
    return $html;
}

function gerarTabelaEstoqueBaixo($dados) {
    $html = '<table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Produto</th>
                <th>Departamento</th>
                <th>Categoria</th>
                <th class="text-right">Quantidade</th>
                <th class="text-right">Mínimo</th>
                <th class="text-right">Ideal</th>
                <th>Nível</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($dados as $item) {
        $badge_class = $item['nivel'] === 'Zerado' ? 'badge-danger' : ($item['nivel'] === 'Crítico' ? 'badge-warning' : 'badge-info');
        
        $html .= '<tr>
            <td>' . htmlspecialchars($item['codigo'] ?? '-') . '</td>
            <td>' . htmlspecialchars($item['nome']) . '</td>
            <td>' . htmlspecialchars($item['departamento']) . '</td>
            <td>' . htmlspecialchars($item['categoria'] ?? '-') . '</td>
            <td class="text-right">' . number_format($item['quantidade_atual'], 2, ',', '.') . ' ' . htmlspecialchars($item['unidade']) . '</td>
            <td class="text-right">' . number_format($item['quantidade_minima'], 2, ',', '.') . '</td>
            <td class="text-right">' . number_format($item['quantidade_ideal'], 2, ',', '.') . '</td>
            <td><span class="badge ' . $badge_class . '">' . htmlspecialchars($item['nivel']) . '</span></td>
        </tr>';
    }
    
    $html .= '</tbody>
    </table>
    <div class="resumo">
        <div class="resumo-item">
            <span>Total de produtos com estoque baixo:</span>
            <span><strong>' . count($dados) . '</strong></span>
        </div>
    </div>';
    
    return $html;
}

function gerarTabelaMovimentacoes($dados) {
    $html = '<table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Produto</th>
                <th>Tipo</th>
                <th class="text-right">Quantidade</th>
                <th class="text-right">Valor Unit.</th>
                <th class="text-right">Valor Total</th>
                <th>Origem</th>
                <th>Usuário</th>
            </tr>
        </thead>
        <tbody>';
    
    $total_entradas = 0;
    $total_saidas = 0;
    $valor_total = 0;
    
    foreach ($dados as $item) {
        $tipo_badge = $item['tipo'] === 'entrada' ? 'badge-success' : ($item['tipo'] === 'saida' ? 'badge-danger' : 'badge-info');
        
        if ($item['tipo'] === 'entrada') {
            $total_entradas += floatval($item['quantidade']);
        } else {
            $total_saidas += floatval($item['quantidade']);
        }
        
        $valor_total += floatval($item['valor_total']);
        
        $html .= '<tr>
            <td>' . htmlspecialchars($item['data_formatada']) . '</td>
            <td>' . htmlspecialchars($item['produto']) . '</td>
            <td><span class="badge ' . $tipo_badge . '">' . strtoupper($item['tipo']) . '</span></td>
            <td class="text-right">' . number_format($item['quantidade'], 2, ',', '.') . ' ' . htmlspecialchars($item['unidade'] ?? '') . '</td>
            <td class="text-right">R$ ' . number_format($item['valor_unitario'], 2, ',', '.') . '</td>
            <td class="text-right">R$ ' . number_format($item['valor_total'], 2, ',', '.') . '</td>
            <td>' . htmlspecialchars($item['origem']) . '</td>
            <td>' . htmlspecialchars($item['usuario'] ?? '-') . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
        <tfoot>
            <tr style="background: #e9ecef;">
                <td colspan="3" class="text-right"><strong>Totais:</strong></td>
                <td class="text-right"><strong>Entradas: ' . number_format($total_entradas, 2, ',', '.') . '<br>Saídas: ' . number_format($total_saidas, 2, ',', '.') . '</strong></td>
                <td colspan="2" class="text-right"><strong>R$ ' . number_format($valor_total, 2, ',', '.') . '</strong></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>';
    
    return $html;
}

function gerarTabelaRequisicoes($dados) {
    $html = '<table>
        <thead>
            <tr>
                <th>Número</th>
                <th>Data</th>
                <th>Departamento</th>
                <th>Solicitante</th>
                <th>Status</th>
                <th>Prioridade</th>
                <th class="text-center">Itens</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($dados as $item) {
        $status_badge = $item['status'] === 'aprovada' ? 'badge-success' : ($item['status'] === 'pendente' ? 'badge-warning' : ($item['status'] === 'rejeitada' ? 'badge-danger' : 'badge-info'));
        $prioridade_badge = $item['prioridade'] === 'urgente' ? 'badge-danger' : ($item['prioridade'] === 'alta' ? 'badge-warning' : 'badge-info');
        
        $html .= '<tr>
            <td>' . htmlspecialchars($item['numero']) . '</td>
            <td>' . htmlspecialchars($item['data_formatada']) . '</td>
            <td>' . htmlspecialchars($item['departamento_destino']) . '</td>
            <td>' . htmlspecialchars($item['solicitante']) . '</td>
            <td><span class="badge ' . $status_badge . '">' . strtoupper($item['status']) . '</span></td>
            <td><span class="badge ' . $prioridade_badge . '">' . strtoupper($item['prioridade']) . '</span></td>
            <td class="text-center">' . $item['total_itens'] . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
    </table>
    <div class="resumo">
        <div class="resumo-item">
            <span>Total de requisições:</span>
            <span><strong>' . count($dados) . '</strong></span>
        </div>
    </div>';
    
    return $html;
}

function gerarTabelaPorCategoria($dados) {
    $html = '<table>
        <thead>
            <tr>
                <th>Categoria</th>
                <th class="text-center">Total de Produtos</th>
                <th class="text-right">Quantidade Total</th>
                <th class="text-right">Valor Total</th>
            </tr>
        </thead>
        <tbody>';
    
    $valor_total_geral = 0;
    foreach ($dados as $item) {
        $valor_total_geral += floatval($item['valor_total']);
        
        $html .= '<tr>
            <td>' . htmlspecialchars($item['categoria']) . '</td>
            <td class="text-center">' . $item['total_produtos'] . '</td>
            <td class="text-right">' . number_format($item['quantidade_total'], 2, ',', '.') . '</td>
            <td class="text-right">R$ ' . number_format($item['valor_total'], 2, ',', '.') . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
        <tfoot>
            <tr style="background: #e9ecef; font-weight: bold;">
                <td colspan="3" class="text-right">TOTAL GERAL:</td>
                <td class="text-right">R$ ' . number_format($valor_total_geral, 2, ',', '.') . '</td>
            </tr>
        </tfoot>
    </table>';
    
    return $html;
}

function gerarTabelaPorDepartamento($dados) {
    $html = '<table>
        <thead>
            <tr>
                <th>Departamento</th>
                <th class="text-center">Total de Produtos</th>
                <th class="text-right">Quantidade Total</th>
                <th class="text-right">Valor Total</th>
            </tr>
        </thead>
        <tbody>';
    
    $valor_total_geral = 0;
    foreach ($dados as $item) {
        $valor_total_geral += floatval($item['valor_total']);
        
        $html .= '<tr>
            <td>' . htmlspecialchars($item['departamento']) . '</td>
            <td class="text-center">' . $item['total_produtos'] . '</td>
            <td class="text-right">' . number_format($item['quantidade_total'], 2, ',', '.') . '</td>
            <td class="text-right">R$ ' . number_format($item['valor_total'], 2, ',', '.') . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
        <tfoot>
            <tr style="background: #e9ecef; font-weight: bold;">
                <td colspan="3" class="text-right">TOTAL GERAL:</td>
                <td class="text-right">R$ ' . number_format($valor_total_geral, 2, ',', '.') . '</td>
            </tr>
        </tfoot>
    </table>';
    
    return $html;
}

function gerarResumoValorizacao($dados) {
    $valor_total = floatval($dados['valor_total_estoque'] ?? 0);
    $total_produtos = intval($dados['total_produtos'] ?? 0);
    $quantidade_total = floatval($dados['quantidade_total'] ?? 0);
    
    $html = '<div class="resumo">
        <h3>Valorização do Estoque</h3>
        <div class="resumo-item">
            <span>Total de Produtos:</span>
            <span><strong>' . number_format($total_produtos, 0, ',', '.') . '</strong></span>
        </div>
        <div class="resumo-item">
            <span>Quantidade Total:</span>
            <span><strong>' . number_format($quantidade_total, 2, ',', '.') . '</strong></span>
        </div>
        <div class="resumo-item">
            <span>Valor Total do Estoque:</span>
            <span><strong style="font-size: 24px; color: #28a745;">R$ ' . number_format($valor_total, 2, ',', '.') . '</strong></span>
        </div>
    </div>';
    
    return $html;
}

function gerarTabelaCurvaABC($dados) {
    $html = '<table>
        <thead>
            <tr>
                <th>#</th>
                <th>Código</th>
                <th>Produto</th>
                <th>Departamento</th>
                <th>Categoria</th>
                <th class="text-right">Quantidade</th>
                <th class="text-right">Valor Unit.</th>
                <th class="text-right">Valor Total</th>
                <th class="text-right">% Acumulado</th>
                <th>Classe</th>
            </tr>
        </thead>
        <tbody>';
    
    $contador = 1;
    foreach ($dados as $item) {
        $classe_badge = $item['classe'] === 'A' ? 'badge-danger' : ($item['classe'] === 'B' ? 'badge-warning' : 'badge-info');
        
        $html .= '<tr>
            <td>' . $contador . '</td>
            <td>' . htmlspecialchars($item['codigo'] ?? '-') . '</td>
            <td>' . htmlspecialchars($item['nome']) . '</td>
            <td>' . htmlspecialchars($item['departamento']) . '</td>
            <td>' . htmlspecialchars($item['categoria'] ?? '-') . '</td>
            <td class="text-right">' . number_format($item['quantidade_atual'], 2, ',', '.') . '</td>
            <td class="text-right">R$ ' . number_format($item['valor_unitario'], 2, ',', '.') . '</td>
            <td class="text-right">R$ ' . number_format($item['valor_total'], 2, ',', '.') . '</td>
            <td class="text-right">' . number_format($item['percentual_acumulado'], 2, ',', '.') . '%</td>
            <td><span class="badge ' . $classe_badge . '">' . $item['classe'] . '</span></td>
        </tr>';
        $contador++;
    }
    
    $html .= '</tbody>
    </table>
    <div class="resumo">
        <h4>Legenda Curva ABC</h4>
        <p><span class="badge badge-danger">Classe A</span>: Até 80% do valor total (itens mais importantes)</p>
        <p><span class="badge badge-warning">Classe B</span>: De 80% a 95% do valor total</p>
        <p><span class="badge badge-info">Classe C</span>: Acima de 95% do valor total</p>
    </div>';
    
    return $html;
}

$conn->close();

