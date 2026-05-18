<?php
include_once(__DIR__ . '/../conexao.php');
header('Content-Type: application/json');

// Função para remover usuário dos dispositivos faciais do tipo culto
function removerUsuarioDispositivosCulto($usuario_id, $conn) {
    try {
        // Buscar dispositivos faciais do tipo 'culto' ativos
        $sql_dispositivos = "SELECT id, nome, ip, porta, usuario, senha FROM dispositivos_faciais WHERE ativo = 1 AND tipo_dispositivo = 'culto'";
        $result_dispositivos = $conn->query($sql_dispositivos);
        
        if (!$result_dispositivos || $result_dispositivos->num_rows == 0) {
            return ['status' => 'info', 'mensagem' => 'Nenhum dispositivo facial do tipo culto ativo encontrado'];
        }
        
        $dispositivos = [];
        while ($row = $result_dispositivos->fetch_assoc()) {
            $dispositivos[] = $row;
        }
        
        $resultados = [];
        $total_sucessos = 0;
        $total_falhas = 0;
        
        // Para cada dispositivo, remover o usuário
        foreach ($dispositivos as $dispositivo) {
            $url_remover = "http://{$dispositivo['ip']}:{$dispositivo['porta']}/cgi-bin/AccessUser.cgi?action=removeMulti&UserIDList[0]={$usuario_id}";
            
            $ch_remover = curl_init();
            curl_setopt($ch_remover, CURLOPT_URL, $url_remover);
            curl_setopt($ch_remover, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_remover, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($ch_remover, CURLOPT_USERPWD, "{$dispositivo['usuario']}:{$dispositivo['senha']}");
            curl_setopt($ch_remover, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch_remover, CURLOPT_CONNECTTIMEOUT, 5);
            
            $resposta_remover = curl_exec($ch_remover);
            $codigo_remover = curl_getinfo($ch_remover, CURLINFO_HTTP_CODE);
            curl_close($ch_remover);
            
            // Considerar sucesso se o código for 200 (removido) ou 404 (já não existe)
            $sucesso = ($codigo_remover == 200 || $codigo_remover == 404);
            
            if ($sucesso) {
                $total_sucessos++;
                $resultados[] = [
                    'dispositivo' => $dispositivo['nome'],
                    'status' => 'sucesso',
                    'mensagem' => 'Usuário removido com sucesso'
                ];
                
                // Registrar na tabela facial_sync_culto
                $sql_sync = "INSERT INTO facial_sync_culto 
                            (id_usuario, id_dispositivo, data, status, origem, tentativas, detalhes) 
                            VALUES (?, ?, CURDATE(), 'removido', 'exclusao', 1, ?)
                            ON DUPLICATE KEY UPDATE 
                            status = 'removido', 
                            tentativas = tentativas + 1,
                            ultima_tentativa = NOW(),
                            detalhes = ?";
                
                $stmt_sync = $conn->prepare($sql_sync);
                $detalhe = "Usuário removido automaticamente durante exclusão/inativação";
                $stmt_sync->bind_param("iiss", $usuario_id, $dispositivo['id'], $detalhe, $detalhe);
                $stmt_sync->execute();
                $stmt_sync->close();
                
            } else {
                $total_falhas++;
                $resultados[] = [
                    'dispositivo' => $dispositivo['nome'],
                    'status' => 'falha',
                    'mensagem' => "Falha na remoção. Código: $codigo_remover"
                ];
                
                // Registrar falha na tabela facial_sync_culto
                $sql_sync = "INSERT INTO facial_sync_culto 
                            (id_usuario, id_dispositivo, data, status, origem, tentativas, detalhes) 
                            VALUES (?, ?, CURDATE(), 'falha', 'exclusao', 1, ?)
                            ON DUPLICATE KEY UPDATE 
                            status = 'falha', 
                            tentativas = tentativas + 1,
                            ultima_tentativa = NOW(),
                            detalhes = ?";
                
                $stmt_sync = $conn->prepare($sql_sync);
                $detalhe_falha = "Falha na remoção automática durante exclusão/inativação. Código: $codigo_remover";
                $stmt_sync->bind_param("iiss", $usuario_id, $dispositivo['id'], $detalhe_falha, $detalhe_falha);
                $stmt_sync->execute();
                $stmt_sync->close();
            }
        }
        
        return [
            'status' => 'sucesso',
            'mensagem' => "Remoção automática concluída. Sucessos: $total_sucessos, Falhas: $total_falhas",
            'resultados' => $resultados
        ];
        
    } catch (Exception $e) {
        return ['status' => 'erro', 'mensagem' => 'Erro na remoção: ' . $e->getMessage()];
    }
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido']);
    exit;
}

// Verificar se o usuário tem culto = 1 antes de remover
$stmt_check = $conn->prepare("SELECT culto FROM usuarios WHERE id = ?");
$stmt_check->bind_param("i", $id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    $usuario = $result_check->fetch_assoc();
    
    // Se o usuário tem culto = 1, remover dos dispositivos faciais antes de inativar
    if ($usuario['culto'] == 1) {
        error_log("Iniciando remoção automática do usuário $id dos dispositivos faciais do tipo culto");
        $resultado_remocao = removerUsuarioDispositivosCulto($id, $conn);
        error_log("Remoção automática do usuário $id: " . json_encode($resultado_remocao));
    }
}

$stmt_check->close();

// Inativar o usuário
$stmt = $conn->prepare("UPDATE usuarios SET ativo = '0' WHERE id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'erro', 'mensagem' => $conn->error]);
    exit;
}

$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'ok', 'mensagem' => 'Usuário inativado com sucesso']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao inativar usuário']);
}

$stmt->close();
$conn->close();
?>
