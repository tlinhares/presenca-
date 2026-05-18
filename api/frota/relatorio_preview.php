<?php
/**
 * API para Preview de Relatórios da Frota
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../conexao.php';

// Verificar permissão
if (!MenuPermissaoService::podeAcessar('frota_relatorios')) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado']);
    exit;
}

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'geral';
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$veiculo_id = isset($_GET['veiculo_id']) && !empty($_GET['veiculo_id']) ? intval($_GET['veiculo_id']) : null;
$usuario_id = isset($_GET['usuario_id']) && !empty($_GET['usuario_id']) ? intval($_GET['usuario_id']) : null;
$status = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : null;

try {
    $dados = [];
    $estatisticas = [];
    $top_veiculos = [];
    $top_usuarios = [];
    $top_destinos = [];
    
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
    
    // Buscar dados baseado no tipo
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
                    ORDER BY total_viagens DESC
                    LIMIT 50";
            break;
            
        case 'km':
            $sql = "SELECT 
                        v.id, v.placa, v.modelo, v.marca,
                        MIN(fu.km_saida) as km_inicial,
                        MAX(COALESCE(fu.km_entrada, fu.km_saida)) as km_final,
                        COALESCE(SUM(fu.km_percorrido), 0) as km_percorrido,
                        COUNT(*) as total_viagens
                    FROM frota_utilizacoes fu
                    JOIN frota_veiculos v ON fu.id_veiculo = v.id
                    $where
                    GROUP BY v.id
                    ORDER BY km_percorrido DESC";
            break;
            
        case 'usuario':
            $sql = "SELECT 
                        fu.*, v.placa, v.modelo, v.marca,
                        u.nome as usuario_nome,
                        DATE_FORMAT(fu.data_saida, '%d/%m/%Y %H:%i') as data_saida_formatada
                    FROM frota_utilizacoes fu
                    JOIN frota_veiculos v ON fu.id_veiculo = v.id
                    JOIN usuarios u ON fu.id_usuario = u.id
                    $where
                    ORDER BY fu.data_saida DESC
                    LIMIT 100";
            break;
            
        case 'veiculo':
            $sql = "SELECT 
                        fu.*, v.placa, v.modelo, v.marca,
                        u.nome as usuario_nome,
                        DATE_FORMAT(fu.data_saida, '%d/%m/%Y %H:%i') as data_saida_formatada
                    FROM frota_utilizacoes fu
                    JOIN frota_veiculos v ON fu.id_veiculo = v.id
                    JOIN usuarios u ON fu.id_usuario = u.id
                    $where
                    ORDER BY fu.data_saida DESC
                    LIMIT 100";
            break;
            
        case 'estatisticas':
            // Será tratado separadamente
            break;
            
        default: // geral
            $sql = "SELECT 
                        fu.*, v.placa, v.modelo, v.marca,
                        u.nome as usuario_nome,
                        DATE_FORMAT(fu.data_saida, '%d/%m/%Y %H:%i') as data_saida_formatada
                    FROM frota_utilizacoes fu
                    JOIN frota_veiculos v ON fu.id_veiculo = v.id
                    JOIN usuarios u ON fu.id_usuario = u.id
                    $where
                    ORDER BY fu.data_saida DESC
                    LIMIT 100";
            break;
    }
    
    // Executar consulta principal
    if ($tipo !== 'estatisticas') {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Formatar tempo se necessário
            if (isset($row['tempo_total'])) {
                $minutos = intval($row['tempo_total']);
                $horas = floor($minutos / 60);
                $mins = $minutos % 60;
                $row['tempo_formatado'] = $horas > 0 ? "{$horas}h {$mins}min" : "{$mins}min";
            } elseif (isset($row['tempo_utilizacao'])) {
                $minutos = intval($row['tempo_utilizacao']);
                $horas = floor($minutos / 60);
                $mins = $minutos % 60;
                $row['tempo_formatado'] = $horas > 0 ? "{$horas}h {$mins}min" : "{$mins}min";
            }
            $dados[] = $row;
        }
    }
    
    // Buscar estatísticas gerais
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
    $stats['tempo_total_formatado'] = $horas > 0 ? "{$horas}h {$mins}min" : "{$mins}min";
    $stats['km_total'] = intval($stats['km_total']);
    $stats['media_km'] = round($stats['media_km'], 1);
    
    $estatisticas = $stats;
    
    // Para estatísticas, buscar tops
    if ($tipo === 'estatisticas') {
        // Top veículos por KM
        $sql_top_v = "SELECT v.placa, v.modelo, COALESCE(SUM(fu.km_percorrido), 0) as km_total
                      FROM frota_utilizacoes fu
                      JOIN frota_veiculos v ON fu.id_veiculo = v.id
                      $where
                      GROUP BY v.id
                      ORDER BY km_total DESC
                      LIMIT 5";
        $stmt_top_v = $conn->prepare($sql_top_v);
        $stmt_top_v->bind_param($types, ...$params);
        $stmt_top_v->execute();
        $result_top_v = $stmt_top_v->get_result();
        while ($row = $result_top_v->fetch_assoc()) {
            $top_veiculos[] = $row;
        }
        
        // Top usuários por viagens
        $sql_top_u = "SELECT u.nome, COUNT(*) as total_viagens
                      FROM frota_utilizacoes fu
                      JOIN usuarios u ON fu.id_usuario = u.id
                      $where
                      GROUP BY u.id
                      ORDER BY total_viagens DESC
                      LIMIT 5";
        $stmt_top_u = $conn->prepare($sql_top_u);
        $stmt_top_u->bind_param($types, ...$params);
        $stmt_top_u->execute();
        $result_top_u = $stmt_top_u->get_result();
        while ($row = $result_top_u->fetch_assoc()) {
            $top_usuarios[] = $row;
        }
        
        // Top destinos
        $sql_top_d = "SELECT COALESCE(destino, 'Não informado') as destino, COUNT(*) as total
                      FROM frota_utilizacoes fu
                      $where
                      GROUP BY destino
                      ORDER BY total DESC
                      LIMIT 5";
        $stmt_top_d = $conn->prepare($sql_top_d);
        $stmt_top_d->bind_param($types, ...$params);
        $stmt_top_d->execute();
        $result_top_d = $stmt_top_d->get_result();
        while ($row = $result_top_d->fetch_assoc()) {
            $top_destinos[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'tipo' => $tipo,
        'dados' => $dados,
        'estatisticas' => $estatisticas,
        'top_veiculos' => $top_veiculos,
        'top_usuarios' => $top_usuarios,
        'top_destinos' => $top_destinos
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar dados: ' . $e->getMessage()
    ]);
}
?>



