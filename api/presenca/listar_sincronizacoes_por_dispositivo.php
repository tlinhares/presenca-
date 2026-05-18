<?php
header('Content-Type: application/json');
include_once(__DIR__ . '/../../api/conexao.php');

$data = $_GET['data'] ?? date('Y-m-d');

try {
    // Buscar dispositivos ativos do tipo restaurante
    $sql_dispositivos = "SELECT id, nome, ip, porta, status_conexao FROM dispositivos_faciais WHERE ativo = 1 AND tipo_dispositivo = 'restaurante' ORDER BY nome";
    $result_dispositivos = $conn->query($sql_dispositivos);
    
    $dispositivos = [];
    
    if ($result_dispositivos && $result_dispositivos->num_rows > 0) {
        while ($row = $result_dispositivos->fetch_assoc()) {
            $dispositivo_id = $row['id'];
            
            // Buscar sincronizações para este dispositivo na data especificada
            $sql_sync = "SELECT fs.id, fs.id_usuario, fs.status, fs.horario_sync, fs.detalhes, fs.origem,
                                COALESCE(u.nome, d.nome) as nome_usuario
                         FROM facial_sync fs
                         LEFT JOIN usuarios u ON fs.id_usuario = u.id AND fs.origem = 'usuario'
                         LEFT JOIN dependentes d ON fs.id_usuario = d.id AND fs.origem = 'dependente'
                         WHERE fs.data = ? AND fs.id_dispositivo = ?
                         ORDER BY fs.horario_sync DESC";
            
            $stmt_sync = $conn->prepare($sql_sync);
            $stmt_sync->bind_param("si", $data, $dispositivo_id);
            $stmt_sync->execute();
            $result_sync = $stmt_sync->get_result();
            
            $sincronizacoes = [];
            $total_sincronizacoes = 0;
            $sincronizados = 0;
            $falhas = 0;
            
            while ($sync_row = $result_sync->fetch_assoc()) {
                $sincronizacoes[] = $sync_row;
                $total_sincronizacoes++;
                
                if ($sync_row['status'] === 'sincronizado') {
                    $sincronizados++;
                } elseif ($sync_row['status'] === 'falha') {
                    $falhas++;
                }
            }
            $stmt_sync->close();
            
            $dispositivos[] = [
                'id' => $row['id'],
                'nome' => $row['nome'],
                'ip' => $row['ip'],
                'porta' => $row['porta'],
                'status_conexao' => $row['status_conexao'],
                'total_sincronizacoes' => $total_sincronizacoes,
                'sincronizados' => $sincronizados,
                'falhas' => $falhas,
                'sincronizacoes' => $sincronizacoes
            ];
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'dispositivos' => $dispositivos
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao carregar sincronizações: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
