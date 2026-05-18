<?php
/**
 * API para Exportar Relatórios da Frota em PDF
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

// Verificar permissão
if (!MenuPermissaoService::podeAcessar('frota_relatorios')) {
    die('Acesso não autorizado');
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../conexao.php';

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'geral';
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$veiculo_id = isset($_GET['veiculo_id']) && !empty($_GET['veiculo_id']) ? intval($_GET['veiculo_id']) : null;
$usuario_id = isset($_GET['usuario_id']) && !empty($_GET['usuario_id']) ? intval($_GET['usuario_id']) : null;
$status = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : null;

try {
    // Inicializar mPDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => $tipo === 'geral' ? 'L' : 'P',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'margin_header' => 10,
        'margin_footer' => 10
    ]);
    
    // Construir WHERE base
    $where = "WHERE DATE(fu.data_saida) BETWEEN ? AND ?";
    $params = [$data_inicio, $data_fim];
    $types = "ss";
    
    if ($veiculo_id) {
        $where .= " AND fu.id_veiculo = ?";
        $params[] = $veiculo_id;
        $types .= "i";
    }
    if ($usuario_id) {
        $where .= " AND fu.id_usuario = ?";
        $params[] = $usuario_id;
        $types .= "i";
    }
    if ($status) {
        $where .= " AND fu.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    // Título do relatório
    $titulos = [
        'geral' => 'Relatório Geral de Utilizações',
        'veiculo' => 'Relatório por Veículo',
        'usuario' => 'Relatório por Usuário',
        'destino' => 'Relatório por Destino',
        'km' => 'Relatório de Quilometragem',
        'estatisticas' => 'Estatísticas da Frota'
    ];
    $titulo = $titulos[$tipo] ?? 'Relatório da Frota';
    
    // Buscar estatísticas
    $sql_stats = "SELECT 
                    COUNT(*) as total_viagens,
                    COALESCE(SUM(km_percorrido), 0) as km_total,
                    COALESCE(AVG(km_percorrido), 0) as media_km,
                    COALESCE(SUM(tempo_utilizacao), 0) as tempo_total,
                    COUNT(DISTINCT id_veiculo) as veiculos_unicos,
                    COUNT(DISTINCT id_usuario) as usuarios_unicos
                  FROM frota_utilizacoes fu
                  $where";
    $stmt_stats = $conn->prepare($sql_stats);
    $stmt_stats->bind_param($types, ...$params);
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
    
    // Formatar tempo total
    $minutos_total = intval($stats['tempo_total']);
    $horas = floor($minutos_total / 60);
    $mins = $minutos_total % 60;
    $tempo_formatado = $horas > 0 ? "{$horas}h {$mins}min" : "{$mins}min";
    
    // Iniciar HTML
    $html = '
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
        .header { 
            text-align: center; 
            padding: 15px; 
            background: linear-gradient(135deg, #17a2b8, #138496); 
            color: white; 
            border-radius: 8px; 
            margin-bottom: 15px;
        }
        .header h1 { margin: 0 0 5px 0; font-size: 18px; }
        .header p { margin: 0; font-size: 11px; opacity: 0.9; }
        .stats-box { 
            background: #f8f9fa; 
            border: 1px solid #dee2e6; 
            border-radius: 6px; 
            padding: 10px; 
            margin-bottom: 15px;
        }
        .stats-box table { width: 100%; border-collapse: collapse; }
        .stats-box td { padding: 5px 10px; }
        .stats-box .label { color: #6c757d; }
        .stats-box .value { font-weight: bold; text-align: right; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data th { 
            background: #343a40; 
            color: white; 
            padding: 8px; 
            text-align: left; 
            font-size: 10px;
        }
        table.data td { 
            border-bottom: 1px solid #dee2e6; 
            padding: 6px 8px; 
            font-size: 9px;
        }
        table.data tr:nth-child(even) { background: #f8f9fa; }
        .placa { 
            background: #ffc107; 
            color: #212529; 
            padding: 2px 6px; 
            border-radius: 3px; 
            font-weight: bold; 
            font-family: monospace;
        }
        .badge-success { background: #28a745; color: white; padding: 2px 6px; border-radius: 3px; }
        .badge-warning { background: #ffc107; color: #212529; padding: 2px 6px; border-radius: 3px; }
        .footer { 
            text-align: center; 
            margin-top: 20px; 
            padding-top: 10px; 
            border-top: 1px solid #dee2e6; 
            color: #6c757d; 
            font-size: 8px;
        }
        .section-title { 
            background: #e9ecef; 
            padding: 8px; 
            margin: 15px 0 10px 0; 
            border-left: 4px solid #17a2b8;
            font-weight: bold;
        }
    </style>
    
    <div class="header">
        <h1>🚛 ' . $titulo . '</h1>
        <p>Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim)) . '</p>
    </div>
    
    <div class="stats-box">
        <table>
            <tr>
                <td class="label">Total de Viagens:</td>
                <td class="value">' . $stats['total_viagens'] . '</td>
                <td class="label">KM Total:</td>
                <td class="value">' . number_format($stats['km_total'], 0, ',', '.') . ' km</td>
                <td class="label">Tempo Total:</td>
                <td class="value">' . $tempo_formatado . '</td>
            </tr>
            <tr>
                <td class="label">Média KM/Viagem:</td>
                <td class="value">' . number_format($stats['media_km'], 1, ',', '.') . ' km</td>
                <td class="label">Veículos:</td>
                <td class="value">' . $stats['veiculos_unicos'] . '</td>
                <td class="label">Usuários:</td>
                <td class="value">' . $stats['usuarios_unicos'] . '</td>
            </tr>
        </table>
    </div>';
    
    // Gerar conteúdo baseado no tipo
    switch ($tipo) {
        case 'destino':
            $sql = "SELECT 
                        COALESCE(fu.destino, 'Não informado') as destino,
                        COUNT(*) as total_viagens,
                        COALESCE(SUM(fu.km_percorrido), 0) as km_total,
                        COALESCE(SUM(fu.tempo_utilizacao), 0) as tempo_total
                    FROM frota_utilizacoes fu
                    $where
                    GROUP BY fu.destino
                    ORDER BY total_viagens DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $html .= '
            <div class="section-title">📍 Utilizações por Destino</div>
            <table class="data">
                <thead>
                    <tr>
                        <th style="width: 40%;">Destino</th>
                        <th style="width: 20%;">Viagens</th>
                        <th style="width: 20%;">KM Total</th>
                        <th style="width: 20%;">Tempo Total</th>
                    </tr>
                </thead>
                <tbody>';
            
            while ($row = $result->fetch_assoc()) {
                $min = intval($row['tempo_total']);
                $h = floor($min / 60);
                $m = $min % 60;
                $tempo = $h > 0 ? "{$h}h {$m}min" : "{$m}min";
                
                $html .= '
                    <tr>
                        <td><strong>' . htmlspecialchars($row['destino']) . '</strong></td>
                        <td>' . $row['total_viagens'] . '</td>
                        <td>' . number_format($row['km_total'], 0, ',', '.') . ' km</td>
                        <td>' . $tempo . '</td>
                    </tr>';
            }
            $html .= '</tbody></table>';
            break;
            
        case 'km':
            $sql = "SELECT 
                        v.placa, v.modelo, v.marca,
                        MIN(fu.km_saida) as km_inicial,
                        MAX(COALESCE(fu.km_entrada, fu.km_saida)) as km_final,
                        COALESCE(SUM(fu.km_percorrido), 0) as km_percorrido,
                        COUNT(*) as total_viagens
                    FROM frota_utilizacoes fu
                    JOIN frota_veiculos v ON fu.id_veiculo = v.id
                    $where
                    GROUP BY v.id
                    ORDER BY km_percorrido DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $html .= '
            <div class="section-title">🏎️ Quilometragem por Veículo</div>
            <table class="data">
                <thead>
                    <tr>
                        <th>Veículo</th>
                        <th>Modelo</th>
                        <th>KM Inicial</th>
                        <th>KM Final</th>
                        <th>KM Percorrido</th>
                        <th>Viagens</th>
                    </tr>
                </thead>
                <tbody>';
            
            while ($row = $result->fetch_assoc()) {
                $html .= '
                    <tr>
                        <td><span class="placa">' . $row['placa'] . '</span></td>
                        <td>' . htmlspecialchars($row['modelo']) . ' - ' . htmlspecialchars($row['marca']) . '</td>
                        <td>' . number_format($row['km_inicial'], 0, ',', '.') . '</td>
                        <td>' . number_format($row['km_final'], 0, ',', '.') . '</td>
                        <td><strong>' . number_format($row['km_percorrido'], 0, ',', '.') . ' km</strong></td>
                        <td>' . $row['total_viagens'] . '</td>
                    </tr>';
            }
            $html .= '</tbody></table>';
            break;
            
        case 'estatisticas':
            // Top veículos
            $sql_v = "SELECT v.placa, v.modelo, COALESCE(SUM(fu.km_percorrido), 0) as km_total, COUNT(*) as viagens
                      FROM frota_utilizacoes fu
                      JOIN frota_veiculos v ON fu.id_veiculo = v.id
                      $where
                      GROUP BY v.id ORDER BY km_total DESC LIMIT 10";
            $stmt_v = $conn->prepare($sql_v);
            $stmt_v->bind_param($types, ...$params);
            $stmt_v->execute();
            $result_v = $stmt_v->get_result();
            
            $html .= '
            <div class="section-title">🏆 Top 10 Veículos por Quilometragem</div>
            <table class="data">
                <thead><tr><th>#</th><th>Veículo</th><th>Modelo</th><th>KM Percorrido</th><th>Viagens</th></tr></thead>
                <tbody>';
            $pos = 1;
            while ($row = $result_v->fetch_assoc()) {
                $html .= '<tr>
                    <td>' . $pos++ . 'º</td>
                    <td><span class="placa">' . $row['placa'] . '</span></td>
                    <td>' . htmlspecialchars($row['modelo']) . '</td>
                    <td><strong>' . number_format($row['km_total'], 0, ',', '.') . ' km</strong></td>
                    <td>' . $row['viagens'] . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';
            
            // Top usuários
            $sql_u = "SELECT u.nome, COUNT(*) as viagens, COALESCE(SUM(fu.km_percorrido), 0) as km_total
                      FROM frota_utilizacoes fu
                      JOIN usuarios u ON fu.id_usuario = u.id
                      $where
                      GROUP BY u.id ORDER BY viagens DESC LIMIT 10";
            $stmt_u = $conn->prepare($sql_u);
            $stmt_u->bind_param($types, ...$params);
            $stmt_u->execute();
            $result_u = $stmt_u->get_result();
            
            $html .= '
            <div class="section-title">👤 Top 10 Usuários por Viagens</div>
            <table class="data">
                <thead><tr><th>#</th><th>Usuário</th><th>Viagens</th><th>KM Total</th></tr></thead>
                <tbody>';
            $pos = 1;
            while ($row = $result_u->fetch_assoc()) {
                $html .= '<tr>
                    <td>' . $pos++ . 'º</td>
                    <td>' . htmlspecialchars($row['nome']) . '</td>
                    <td><strong>' . $row['viagens'] . '</strong></td>
                    <td>' . number_format($row['km_total'], 0, ',', '.') . ' km</td>
                </tr>';
            }
            $html .= '</tbody></table>';
            
            // Top destinos
            $sql_d = "SELECT COALESCE(destino, 'Não informado') as destino, COUNT(*) as total
                      FROM frota_utilizacoes fu $where
                      GROUP BY destino ORDER BY total DESC LIMIT 10";
            $stmt_d = $conn->prepare($sql_d);
            $stmt_d->bind_param($types, ...$params);
            $stmt_d->execute();
            $result_d = $stmt_d->get_result();
            
            $html .= '
            <div class="section-title">📍 Top 10 Destinos</div>
            <table class="data">
                <thead><tr><th>#</th><th>Destino</th><th>Viagens</th></tr></thead>
                <tbody>';
            $pos = 1;
            while ($row = $result_d->fetch_assoc()) {
                $html .= '<tr>
                    <td>' . $pos++ . 'º</td>
                    <td>' . htmlspecialchars($row['destino']) . '</td>
                    <td><strong>' . $row['total'] . '</strong></td>
                </tr>';
            }
            $html .= '</tbody></table>';
            break;
            
        default: // geral, veiculo, usuario
            $sql = "SELECT 
                        fu.*, v.placa, v.modelo, v.marca,
                        u.nome as usuario_nome,
                        fd.nome as departamento_nome,
                        DATE_FORMAT(fu.data_saida, '%d/%m/%Y %H:%i') as data_saida_fmt,
                        DATE_FORMAT(fu.data_entrada, '%d/%m/%Y %H:%i') as data_entrada_fmt
                    FROM frota_utilizacoes fu
                    JOIN frota_veiculos v ON fu.id_veiculo = v.id
                    JOIN usuarios u ON fu.id_usuario = u.id
                    LEFT JOIN frota_departamentos fd ON fu.id_departamento = fd.id
                    $where
                    ORDER BY fu.data_saida DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $html .= '
            <div class="section-title">📋 Detalhamento das Utilizações</div>
            <table class="data">
                <thead>
                    <tr>
                        <th>Data Saída</th>
                        <th>Veículo</th>
                        <th>Usuário</th>
                        <th>Departamento</th>
                        <th>Destino</th>
                        <th>KM Saída</th>
                        <th>KM Entrada</th>
                        <th>KM Percorrido</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>';
            
            while ($row = $result->fetch_assoc()) {
                $statusBadge = $row['status'] === 'finalizado' 
                    ? '<span class="badge-success">Finalizado</span>'
                    : '<span class="badge-warning">Em Andamento</span>';
                
                $html .= '
                    <tr>
                        <td>' . $row['data_saida_fmt'] . '</td>
                        <td><span class="placa">' . $row['placa'] . '</span></td>
                        <td>' . htmlspecialchars($row['usuario_nome']) . '</td>
                        <td>' . htmlspecialchars($row['departamento_nome'] ?? '-') . '</td>
                        <td>' . htmlspecialchars($row['destino'] ?? '-') . '</td>
                        <td>' . number_format($row['km_saida'], 0, ',', '.') . '</td>
                        <td>' . ($row['km_entrada'] ? number_format($row['km_entrada'], 0, ',', '.') : '-') . '</td>
                        <td><strong>' . ($row['km_percorrido'] ? number_format($row['km_percorrido'], 0, ',', '.') . ' km' : '-') . '</strong></td>
                        <td>' . $statusBadge . '</td>
                    </tr>';
            }
            $html .= '</tbody></table>';
            break;
    }
    
    // Footer
    $html .= '
    <div class="footer">
        <p>Relatório gerado automaticamente pelo Sistema de Frota</p>
        <p>Gerado em: ' . date('d/m/Y H:i:s') . '</p>
    </div>';
    
    $mpdf->SetTitle($titulo);
    $mpdf->SetAuthor('Sistema de Presença - Módulo Frota');
    $mpdf->WriteHTML($html);
    
    $filename = 'relatorio_frota_' . $tipo . '_' . date('Y-m-d') . '.pdf';
    $mpdf->Output($filename, 'I');
    
} catch (Exception $e) {
    die('Erro ao gerar PDF: ' . $e->getMessage());
}
?>



