<?php
/**
 * API para mostrar detalhes das falhas de sincronização
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/timezone.php';
include_once(__DIR__ . '/../../api/conexao.php');

$data = $_GET['data'] ?? date('Y-m-d');

try {
    // Buscar falhas de sincronização
    $sql_falhas = "SELECT 
                    fs.id,
                    fs.id_usuario,
                    u.nome,
                    u.email,
                    fs.id_dispositivo,
                    d.nome as dispositivo_nome,
                    d.ip as dispositivo_ip,
                    fs.status,
                    fs.tentativas,
                    fs.ultima_tentativa,
                    fs.detalhes,
                    fs.data
                   FROM facial_sync_culto fs
                   JOIN usuarios u ON fs.id_usuario = u.id
                   JOIN dispositivos_faciais d ON fs.id_dispositivo = d.id AND d.ativo = 1
                   WHERE fs.data = ? AND fs.status = 'falha'
                   ORDER BY fs.ultima_tentativa DESC";
    
    $stmt_falhas = $conn->prepare($sql_falhas);
    $stmt_falhas->bind_param("s", $data);
    $stmt_falhas->execute();
    $result_falhas = $stmt_falhas->get_result();
    
    $falhas = [];
    while ($row = $result_falhas->fetch_assoc()) {
        $falhas[] = $row;
    }
    
    // Buscar estatísticas gerais (apenas dispositivos ativos)
    $sql_stats = "SELECT 
                    COUNT(*) as total_falhas,
                    COUNT(DISTINCT fs.id_usuario) as usuarios_com_falha,
                    COUNT(DISTINCT fs.id_dispositivo) as dispositivos_com_falha
                   FROM facial_sync_culto fs
                   JOIN dispositivos_faciais d ON fs.id_dispositivo = d.id AND d.ativo = 1
                   WHERE fs.data = ? AND fs.status = 'falha'";
    
    $stmt_stats = $conn->prepare($sql_stats);
    $stmt_stats->bind_param("s", $data);
    $stmt_stats->execute();
    $result_stats = $stmt_stats->get_result();
    $stats = $result_stats->fetch_assoc();
    
    echo json_encode([
        'status' => 'sucesso',
        'data' => $data,
        'estatisticas' => $stats,
        'falhas' => $falhas
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar falhas: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
