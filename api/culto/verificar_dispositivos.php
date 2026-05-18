<?php
/**
 * API para verificar dispositivos faciais do tipo culto
 */
header('Content-Type: application/json; charset=UTF-8');
include_once(__DIR__ . '/../../api/conexao.php');

try {
    // Verificar dispositivos do tipo culto
    $sql_dispositivos = "SELECT id, nome, ip, porta, usuario, senha, ativo, tipo_dispositivo 
                         FROM dispositivos_faciais 
                         WHERE tipo_dispositivo = 'culto'";
    
    $result_dispositivos = $conn->query($sql_dispositivos);
    $dispositivos = [];
    
    while ($row = $result_dispositivos->fetch_assoc()) {
        $dispositivos[] = $row;
    }
    
    // Verificar usuários do culto
    $sql_usuarios = "SELECT COUNT(*) as total_usuarios_culto,
                            SUM(CASE WHEN foto_base64 IS NOT NULL AND foto_base64 != '' THEN 1 ELSE 0 END) as usuarios_com_foto
                     FROM usuarios 
                     WHERE culto = 1 AND ativo = 1";
    
    $result_usuarios = $conn->query($sql_usuarios);
    $usuarios = $result_usuarios->fetch_assoc();
    
    // Verificar registros de sincronização
    $sql_sync = "SELECT COUNT(*) as total_registros,
                        SUM(CASE WHEN status = 'sincronizado' THEN 1 ELSE 0 END) as sincronizados,
                        SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as falhas,
                        SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes
                 FROM facial_sync_culto 
                 WHERE data = CURDATE()";
    
    $result_sync = $conn->query($sql_sync);
    $sync_stats = $result_sync->fetch_assoc();
    
    echo json_encode([
        'status' => 'sucesso',
        'dispositivos' => $dispositivos,
        'usuarios' => $usuarios,
        'sincronizacao' => $sync_stats,
        'resumo' => [
            'total_dispositivos_culto' => count($dispositivos),
            'dispositivos_ativos' => count(array_filter($dispositivos, function($d) { return $d['ativo'] == 1; })),
            'total_usuarios_culto' => $usuarios['total_usuarios_culto'],
            'usuarios_com_foto' => $usuarios['usuarios_com_foto'],
            'registros_sync_hoje' => $sync_stats['total_registros']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao verificar dispositivos: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
