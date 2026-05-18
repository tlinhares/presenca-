<?php
/**
 * API para sincronizar usuário do culto permanentemente
 * Este script sincroniza um usuário específico com todos os dispositivos de culto
 */
header('Content-Type: application/json; charset=UTF-8');
include_once(__DIR__ . '/../../api/conexao.php');

$usuario_id = $_POST['usuario_id'] ?? null;

if (!$usuario_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID do usuário é obrigatório']);
    exit;
}

try {
    // Verificar se o usuário tem culto=1 e foto
    $sql_usuario = "SELECT id, nome, foto_base64, culto, ativo 
                    FROM usuarios 
                    WHERE id = ? AND culto = 1 AND ativo = 1";
    
    $stmt_usuario = $conn->prepare($sql_usuario);
    $stmt_usuario->bind_param("i", $usuario_id);
    $stmt_usuario->execute();
    $result_usuario = $stmt_usuario->get_result();
    
    if ($result_usuario->num_rows == 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não encontrado ou não é do culto']);
        exit;
    }
    
    $usuario = $result_usuario->fetch_assoc();
    
    if (empty($usuario['foto_base64'])) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não possui foto cadastrada']);
        exit;
    }
    
    // Buscar dispositivos faciais do tipo 'culto' ativos
    $sql_dispositivos = "SELECT id, nome, ip, porta, usuario, senha 
                         FROM dispositivos_faciais 
                         WHERE ativo = 1 AND tipo_dispositivo = 'culto'";
    
    $result_dispositivos = $conn->query($sql_dispositivos);
    $dispositivos = [];
    
    while ($row = $result_dispositivos->fetch_assoc()) {
        $dispositivos[] = $row;
    }
    
    if (empty($dispositivos)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Nenhum dispositivo facial do tipo culto ativo encontrado']);
        exit;
    }
    
    $data_atual = date('Y-m-d');
    $sincronizados = 0;
    $falhas = 0;
    $detalhes = [];
    
    // Sincronizar com cada dispositivo
    foreach ($dispositivos as $dispositivo) {
        // Preparar dados para envio
        $dados = [
            'UserID' => (string)$usuario['id'],
            'Name' => $usuario['nome'],
            'PhotoData' => [$usuario['foto_base64']],
            'Type' => 'culto',
            'Status' => 'active'
        ];
        
        // URL do dispositivo (ajustar conforme API do dispositivo)
        $url = "http://{$dispositivo['ip']}:{$dispositivo['porta']}/cgi-bin/AccessFace.cgi?action=updateMulti";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($dados))
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch, CURLOPT_USERPWD, "{$dispositivo['usuario']}:{$dispositivo['senha']}");
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $resposta = curl_exec($ch);
        $erro = curl_error($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($codigo == 200 && !$erro) {
            $sincronizados++;
            $detalhes[] = "✓ Sincronizado com sucesso no dispositivo {$dispositivo['nome']} ({$dispositivo['ip']})";
            
            // Registrar sucesso na tabela facial_sync_culto
            $sql_insert = "INSERT INTO facial_sync_culto 
                          (id_usuario, id_dispositivo, data, status, origem, tentativas, detalhes) 
                          VALUES (?, ?, ?, 'sincronizado', 'culto', 1, ?)
                          ON DUPLICATE KEY UPDATE 
                          status = 'sincronizado', 
                          tentativas = tentativas + 1,
                          ultima_tentativa = NOW(),
                          detalhes = ?";
            
            $stmt_insert = $conn->prepare($sql_insert);
            $detalhe_sucesso = "Sincronização permanente realizada com sucesso. Código: $codigo";
            $stmt_insert->bind_param("iisss", $usuario_id, $dispositivo['id'], $data_atual, $detalhe_sucesso, $detalhe_sucesso);
            $stmt_insert->execute();
            $stmt_insert->close();
            
        } else {
            $falhas++;
            $detalhes[] = "✗ Falha ao sincronizar no dispositivo {$dispositivo['nome']} ({$dispositivo['ip']}). Código: $codigo, Erro: $erro";
            
            // Registrar falha na tabela facial_sync_culto
            $sql_insert = "INSERT INTO facial_sync_culto 
                          (id_usuario, id_dispositivo, data, status, origem, tentativas, detalhes) 
                          VALUES (?, ?, ?, 'falha', 'culto', 1, ?)
                          ON DUPLICATE KEY UPDATE 
                          status = 'falha', 
                          tentativas = tentativas + 1,
                          ultima_tentativa = NOW(),
                          detalhes = ?";
            
            $stmt_insert = $conn->prepare($sql_insert);
            $detalhe_falha = "Falha na sincronização permanente. Código: $codigo, Erro: $erro";
            $stmt_insert->bind_param("iisss", $usuario_id, $dispositivo['id'], $data_atual, $detalhe_falha, $detalhe_falha);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
    }
    
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Sincronização permanente concluída',
        'usuario' => $usuario['nome'],
        'dispositivos_total' => count($dispositivos),
        'sincronizados' => $sincronizados,
        'falhas' => $falhas,
        'detalhes' => $detalhes,
        'data' => $data_atual
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro na sincronização: ' . $e->getMessage()
    ]);
}

$conn->close();
?>