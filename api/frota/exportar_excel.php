<?php
/**
 * API para Exportar Relatórios da Frota em Excel (CSV)
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

require_once __DIR__ . '/../conexao.php';

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'geral';
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$veiculo_id = isset($_GET['veiculo_id']) && !empty($_GET['veiculo_id']) ? intval($_GET['veiculo_id']) : null;
$usuario_id = isset($_GET['usuario_id']) && !empty($_GET['usuario_id']) ? intval($_GET['usuario_id']) : null;
$status = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : null;

try {
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
    
    // Nome do arquivo
    $filename = 'relatorio_frota_' . $tipo . '_' . date('Y-m-d') . '.csv';
    
    // Headers para download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Abrir output
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8 no Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Gerar conteúdo baseado no tipo
    switch ($tipo) {
        case 'destino':
            $sql = "SELECT 
                        COALESCE(fu.destino, 'Não informado') as destino,
                        COUNT(*) as total_viagens,
                        COALESCE(SUM(fu.km_percorrido), 0) as km_total,
                        COALESCE(SUM(fu.tempo_utilizacao), 0) as tempo_total_min
                    FROM frota_utilizacoes fu
                    $where
                    GROUP BY fu.destino
                    ORDER BY total_viagens DESC";
            
            // Cabeçalho
            fputcsv($output, ['Destino', 'Total Viagens', 'KM Total', 'Tempo Total (min)'], ';');
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['destino'],
                    $row['total_viagens'],
                    $row['km_total'],
                    $row['tempo_total_min']
                ], ';');
            }
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
            
            // Cabeçalho
            fputcsv($output, ['Placa', 'Modelo', 'Marca', 'KM Inicial', 'KM Final', 'KM Percorrido', 'Total Viagens'], ';');
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['placa'],
                    $row['modelo'],
                    $row['marca'],
                    $row['km_inicial'],
                    $row['km_final'],
                    $row['km_percorrido'],
                    $row['total_viagens']
                ], ';');
            }
            break;
            
        case 'estatisticas':
            // Estatísticas gerais
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
            
            fputcsv($output, ['ESTATÍSTICAS GERAIS'], ';');
            fputcsv($output, ['Métrica', 'Valor'], ';');
            fputcsv($output, ['Total de Viagens', $stats['total_viagens']], ';');
            fputcsv($output, ['KM Total', $stats['km_total']], ';');
            fputcsv($output, ['Média KM/Viagem', round($stats['media_km'], 1)], ';');
            fputcsv($output, ['Tempo Total (min)', $stats['tempo_total']], ';');
            fputcsv($output, ['Veículos Utilizados', $stats['veiculos_unicos']], ';');
            fputcsv($output, ['Usuários Ativos', $stats['usuarios_unicos']], ';');
            fputcsv($output, [], ';');
            
            // Top veículos
            fputcsv($output, ['TOP VEÍCULOS POR KM'], ';');
            fputcsv($output, ['Posição', 'Placa', 'Modelo', 'KM Total', 'Viagens'], ';');
            
            $sql_v = "SELECT v.placa, v.modelo, COALESCE(SUM(fu.km_percorrido), 0) as km_total, COUNT(*) as viagens
                      FROM frota_utilizacoes fu
                      JOIN frota_veiculos v ON fu.id_veiculo = v.id
                      $where
                      GROUP BY v.id ORDER BY km_total DESC LIMIT 10";
            $stmt_v = $conn->prepare($sql_v);
            $stmt_v->bind_param($types, ...$params);
            $stmt_v->execute();
            $result_v = $stmt_v->get_result();
            $pos = 1;
            while ($row = $result_v->fetch_assoc()) {
                fputcsv($output, [$pos++, $row['placa'], $row['modelo'], $row['km_total'], $row['viagens']], ';');
            }
            fputcsv($output, [], ';');
            
            // Top usuários
            fputcsv($output, ['TOP USUÁRIOS POR VIAGENS'], ';');
            fputcsv($output, ['Posição', 'Nome', 'Viagens', 'KM Total'], ';');
            
            $sql_u = "SELECT u.nome, COUNT(*) as viagens, COALESCE(SUM(fu.km_percorrido), 0) as km_total
                      FROM frota_utilizacoes fu
                      JOIN usuarios u ON fu.id_usuario = u.id
                      $where
                      GROUP BY u.id ORDER BY viagens DESC LIMIT 10";
            $stmt_u = $conn->prepare($sql_u);
            $stmt_u->bind_param($types, ...$params);
            $stmt_u->execute();
            $result_u = $stmt_u->get_result();
            $pos = 1;
            while ($row = $result_u->fetch_assoc()) {
                fputcsv($output, [$pos++, $row['nome'], $row['viagens'], $row['km_total']], ';');
            }
            break;
            
        default: // geral, veiculo, usuario
            $sql = "SELECT 
                        fu.id,
                        v.placa, v.modelo, v.marca,
                        u.nome as usuario_nome,
                        DATE_FORMAT(fu.data_saida, '%d/%m/%Y %H:%i') as data_saida,
                        DATE_FORMAT(fu.data_entrada, '%d/%m/%Y %H:%i') as data_entrada,
                        fu.km_saida,
                        fu.km_entrada,
                        fu.km_percorrido,
                        fu.tempo_utilizacao,
                        fu.destino,
                        fu.motivo,
                        fu.status,
                        fu.observacoes_saida,
                        fu.observacoes_entrada,
                        fd.nome as departamento_nome
                    FROM frota_utilizacoes fu
                    JOIN frota_veiculos v ON fu.id_veiculo = v.id
                    JOIN usuarios u ON fu.id_usuario = u.id
                    LEFT JOIN frota_departamentos fd ON fu.id_departamento = fd.id
                    $where
                    ORDER BY fu.data_saida DESC";
            
            // Cabeçalho
            fputcsv($output, [
                'ID', 'Placa', 'Modelo', 'Marca', 'Usuário', 'Departamento',
                'Data Saída', 'Data Entrada', 'KM Saída', 'KM Entrada', 
                'KM Percorrido', 'Tempo (min)', 'Destino', 'Motivo', 
                'Status', 'Obs. Saída', 'Obs. Entrada'
            ], ';');
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['id'],
                    $row['placa'],
                    $row['modelo'],
                    $row['marca'],
                    $row['usuario_nome'],
                    $row['departamento_nome'] ?? '',
                    $row['data_saida'],
                    $row['data_entrada'] ?? '',
                    $row['km_saida'],
                    $row['km_entrada'] ?? '',
                    $row['km_percorrido'] ?? '',
                    $row['tempo_utilizacao'] ?? '',
                    $row['destino'] ?? '',
                    $row['motivo'] ?? '',
                    $row['status'],
                    $row['observacoes_saida'] ?? '',
                    $row['observacoes_entrada'] ?? ''
                ], ';');
            }
            break;
    }
    
    fclose($output);
    
} catch (Exception $e) {
    die('Erro ao gerar Excel: ' . $e->getMessage());
}
?>



