<?php
/**
 * API para obter estatísticas de sincronização de culto
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/timezone.php';
include_once(__DIR__ . '/../../api/conexao.php');

$data = $_GET['data'] ?? date('Y-m-d');

try {
    // Estatísticas gerais
    $sql_geral = "SELECT 
                    COUNT(*) as total_usuarios_culto,
                    SUM(CASE WHEN u.foto_base64 IS NOT NULL AND u.foto_base64 != '' THEN 1 ELSE 0 END) as usuarios_com_foto
                  FROM usuarios u
                  WHERE u.culto = 1 AND u.ativo = 1";
    
    $result_geral = $conn->query($sql_geral);
    $stats_geral = $result_geral->fetch_assoc();
    
    // Contar dispositivos separadamente
    $sql_dispositivos = "SELECT COUNT(*) as dispositivos_ativos
                         FROM dispositivos_faciais 
                         WHERE ativo = 1 AND tipo_dispositivo = 'culto'";
    
    $result_dispositivos = $conn->query($sql_dispositivos);
    $dispositivos_count = $result_dispositivos->fetch_assoc();
    $stats_geral['dispositivos_ativos'] = $dispositivos_count['dispositivos_ativos'];
    
    // Estatísticas de sincronização por status (apenas dispositivos ativos)
    $sql_status = "SELECT 
                     fs.status,
                     COUNT(*) as quantidade
                   FROM facial_sync_culto fs
                   JOIN dispositivos_faciais d ON fs.id_dispositivo = d.id AND d.ativo = 1
                   WHERE fs.data = ?
                   GROUP BY fs.status";
    
    $stmt_status = $conn->prepare($sql_status);
    $stmt_status->bind_param("s", $data);
    $stmt_status->execute();
    $result_status = $stmt_status->get_result();
    
    $status_stats = [];
    while ($row = $result_status->fetch_assoc()) {
        $status_stats[$row['status']] = $row['quantidade'];
    }
    
    // Estatísticas por dispositivo (apenas ativos)
    $sql_dispositivo = "SELECT 
                          d.id,
                          d.nome as dispositivo_nome,
                          d.ip,
                          d.ativo,
                          COUNT(fs.id) as total_registros,
                          SUM(CASE WHEN fs.status = 'sincronizado' THEN 1 ELSE 0 END) as sincronizados,
                          SUM(CASE WHEN fs.status = 'falha' THEN 1 ELSE 0 END) as falhas,
                          SUM(CASE WHEN fs.status = 'pendente' THEN 1 ELSE 0 END) as pendentes
                        FROM dispositivos_faciais d
                        LEFT JOIN facial_sync_culto fs ON d.id = fs.id_dispositivo AND fs.data = ?
                        WHERE d.tipo_dispositivo = 'culto' AND d.ativo = 1
                        GROUP BY d.id, d.nome, d.ip, d.ativo
                        ORDER BY d.nome";
    
    $stmt_dispositivo = $conn->prepare($sql_dispositivo);
    $stmt_dispositivo->bind_param("s", $data);
    $stmt_dispositivo->execute();
    $result_dispositivo = $stmt_dispositivo->get_result();
    
    $dispositivos_stats = [];
    while ($row = $result_dispositivo->fetch_assoc()) {
        $dispositivos_stats[] = $row;
    }
    
    // Usuários com problemas de sincronização (apenas dispositivos ativos)
    $sql_problemas = "SELECT 
                        u.id,
                        u.nome,
                        u.email,
                        COUNT(fs.id) as total_tentativas,
                        MAX(fs.ultima_tentativa) as ultima_tentativa,
                        GROUP_CONCAT(DISTINCT fs.status) as status_agregado
                      FROM usuarios u
                      JOIN facial_sync_culto fs ON u.id = fs.id_usuario
                      JOIN dispositivos_faciais d ON fs.id_dispositivo = d.id AND d.ativo = 1
                      WHERE fs.data = ? 
                      AND fs.status IN ('falha', 'pendente')
                      AND u.culto = 1
                      GROUP BY u.id, u.nome, u.email
                      HAVING total_tentativas > 0
                      ORDER BY total_tentativas DESC, ultima_tentativa DESC";
    
    $stmt_problemas = $conn->prepare($sql_problemas);
    $stmt_problemas->bind_param("s", $data);
    $stmt_problemas->execute();
    $result_problemas = $stmt_problemas->get_result();
    
    $usuarios_problemas = [];
    while ($row = $result_problemas->fetch_assoc()) {
        $usuarios_problemas[] = $row;
    }
    
    // Lista detalhada de usuários do culto com status de sincronização (apenas dispositivos ativos)
    $sql_usuarios_detalhado = "SELECT 
                                u.id,
                                u.nome,
                                u.email,
                                u.foto_base64,
                                u.culto,
                                u.ativo,
                                COUNT(fs.id) as total_sincronizacoes,
                                SUM(CASE WHEN fs.status = 'sincronizado' THEN 1 ELSE 0 END) as sincronizados,
                                SUM(CASE WHEN fs.status = 'falha' THEN 1 ELSE 0 END) as falhas,
                                SUM(CASE WHEN fs.status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                                MAX(fs.ultima_tentativa) as ultima_sincronizacao,
                                GROUP_CONCAT(DISTINCT d.nome ORDER BY d.nome SEPARATOR ', ') as dispositivos
                              FROM usuarios u
                              LEFT JOIN facial_sync_culto fs ON u.id = fs.id_usuario AND fs.data = ?
                              LEFT JOIN dispositivos_faciais d ON fs.id_dispositivo = d.id AND d.ativo = 1
                              WHERE u.culto = 1 AND u.ativo = 1
                              GROUP BY u.id, u.nome, u.email, u.foto_base64, u.culto, u.ativo
                              ORDER BY u.nome";
    
    $stmt_usuarios_detalhado = $conn->prepare($sql_usuarios_detalhado);
    $stmt_usuarios_detalhado->bind_param("s", $data);
    $stmt_usuarios_detalhado->execute();
    $result_usuarios_detalhado = $stmt_usuarios_detalhado->get_result();
    
    $usuarios_detalhado = [];
    while ($row = $result_usuarios_detalhado->fetch_assoc()) {
        $usuarios_detalhado[] = $row;
    }
    
    // Logs recentes (apenas dispositivos ativos)
    $sql_logs = "SELECT 
                   fs.id,
                   u.nome as usuario_nome,
                   d.nome as dispositivo_nome,
                   fs.status,
                   fs.tentativas,
                   fs.ultima_tentativa,
                   fs.detalhes
                 FROM facial_sync_culto fs
                 JOIN usuarios u ON fs.id_usuario = u.id
                 JOIN dispositivos_faciais d ON fs.id_dispositivo = d.id AND d.ativo = 1
                 WHERE fs.data = ?
                 ORDER BY fs.ultima_tentativa DESC
                 LIMIT 50";
    
    $stmt_logs = $conn->prepare($sql_logs);
    $stmt_logs->bind_param("s", $data);
    $stmt_logs->execute();
    $result_logs = $stmt_logs->get_result();
    
    $logs_recentes = [];
    while ($row = $result_logs->fetch_assoc()) {
        $logs_recentes[] = $row;
    }
    
    echo json_encode([
        'status' => 'sucesso',
        'data' => $data,
        'estatisticas_gerais' => $stats_geral,
        'status_sincronizacao' => $status_stats,
        'dispositivos' => $dispositivos_stats,
        'usuarios_com_problemas' => $usuarios_problemas,
        'usuarios_detalhado' => $usuarios_detalhado,
        'logs_recentes' => $logs_recentes
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao obter estatísticas: ' . $e->getMessage()
    ]);
}

$conn->close();
?>