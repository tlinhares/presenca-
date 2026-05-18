<?php
/**
 * API para sincronização inteligente - verifica antes de sincronizar
 */
header('Content-Type: application/json; charset=UTF-8');
include_once(__DIR__ . '/../../api/conexao.php');

$usuario_id = $_POST['usuario_id'] ?? null;

if (!$usuario_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID do usuário é obrigatório']);
    exit;
}

try {
    // Buscar dados do usuário
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
    
    $resultados = [];
    $total_verificados = 0;
    $total_sincronizados = 0;
    $total_ja_sincronizados = 0;
    $total_falhas = 0;
    
    // Para cada dispositivo, verificar e sincronizar se necessário
    foreach ($dispositivos as $dispositivo) {
        $total_verificados++;
        
        // Verificar se usuário existe no dispositivo
        $url_usuario = "http://{$dispositivo['ip']}:{$dispositivo['porta']}/cgi-bin/AccessUser.cgi?action=list&UserIDList[0]={$usuario_id}";
        
        $ch_usuario = curl_init();
        curl_setopt($ch_usuario, CURLOPT_URL, $url_usuario);
        curl_setopt($ch_usuario, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_usuario, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch_usuario, CURLOPT_USERPWD, "{$dispositivo['usuario']}:{$dispositivo['senha']}");
        curl_setopt($ch_usuario, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch_usuario, CURLOPT_CONNECTTIMEOUT, 5);
        
        $resposta_usuario = curl_exec($ch_usuario);
        $erro_usuario = curl_error($ch_usuario);
        $codigo_usuario = curl_getinfo($ch_usuario, CURLINFO_HTTP_CODE);
        curl_close($ch_usuario);
        
        $usuario_existe = false;
        if ($codigo_usuario == 200 && !$erro_usuario) {
            $usuario_existe = (strpos($resposta_usuario, '<UserID>' . $usuario_id . '</UserID>') !== false);
        }
        
        // Se usuário existe, verificar se tem foto
        $foto_existe = false;
        if ($usuario_existe) {
            $url_foto = "http://{$dispositivo['ip']}:{$dispositivo['porta']}/cgi-bin/AccessFace.cgi?action=list&UserIDList[0]={$usuario_id}";
            
            $ch_foto = curl_init();
            curl_setopt($ch_foto, CURLOPT_URL, $url_foto);
            curl_setopt($ch_foto, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_foto, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($ch_foto, CURLOPT_USERPWD, "{$dispositivo['usuario']}:{$dispositivo['senha']}");
            curl_setopt($ch_foto, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch_foto, CURLOPT_CONNECTTIMEOUT, 5);
            
            $resposta_foto = curl_exec($ch_foto);
            $erro_foto = curl_error($ch_foto);
            $codigo_foto = curl_getinfo($ch_foto, CURLINFO_HTTP_CODE);
            curl_close($ch_foto);
            
            if ($codigo_foto == 200 && !$erro_foto) {
                $foto_existe = (strpos($resposta_foto, '<UserID>' . $usuario_id . '</UserID>') !== false && 
                               strpos($resposta_foto, '<PhotoData>') !== false);
            }
        }
        
        // Se usuário já existe e tem foto, não precisa sincronizar
        if ($usuario_existe && $foto_existe) {
            $resultados[] = [
                'dispositivo' => $dispositivo['nome'],
                'ip' => $dispositivo['ip'],
                'status' => 'ja_sincronizado',
                'mensagem' => 'Usuário já está sincronizado com foto'
            ];
            $total_ja_sincronizados++;
            continue;
        }
        
        // Sincronizar usuário
        $dados = [
            'UserID' => (string)$usuario_id,
            'Name' => $usuario['nome'],
            'PhotoData' => [$usuario['foto_base64']],
            'Type' => 'culto',
            'Status' => 'active'
        ];
        
        $url_sync = "http://{$dispositivo['ip']}:{$dispositivo['porta']}/cgi-bin/AccessFace.cgi?action=updateMulti";
        
        $ch_sync = curl_init();
        curl_setopt($ch_sync, CURLOPT_URL, $url_sync);
        curl_setopt($ch_sync, CURLOPT_POST, true);
        curl_setopt($ch_sync, CURLOPT_POSTFIELDS, json_encode($dados));
        curl_setopt($ch_sync, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($dados))
        ]);
        curl_setopt($ch_sync, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_sync, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch_sync, CURLOPT_USERPWD, "{$dispositivo['usuario']}:{$dispositivo['senha']}");
        curl_setopt($ch_sync, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch_sync, CURLOPT_CONNECTTIMEOUT, 5);
        
        $resposta_sync = curl_exec($ch_sync);
        $erro_sync = curl_error($ch_sync);
        $codigo_sync = curl_getinfo($ch_sync, CURLINFO_HTTP_CODE);
        curl_close($ch_sync);
        
        if ($codigo_sync == 200 && !$erro_sync) {
            $resultados[] = [
                'dispositivo' => $dispositivo['nome'],
                'ip' => $dispositivo['ip'],
                'status' => 'sincronizado',
                'mensagem' => 'Usuário sincronizado com sucesso'
            ];
            $total_sincronizados++;
            
            // Registrar sucesso na tabela facial_sync_culto
            $sql_insert = "INSERT INTO facial_sync_culto 
                          (id_usuario, id_dispositivo, data, status, origem, tentativas, detalhes) 
                          VALUES (?, ?, CURDATE(), 'sincronizado', 'culto', 1, ?)
                          ON DUPLICATE KEY UPDATE 
                          status = 'sincronizado', 
                          tentativas = tentativas + 1,
                          ultima_tentativa = NOW(),
                          detalhes = ?";
            
            $stmt_insert = $conn->prepare($sql_insert);
            $detalhe_sucesso = "Sincronização inteligente realizada com sucesso. Código: $codigo_sync";
            $stmt_insert->bind_param("iisss", $usuario_id, $dispositivo['id'], $detalhe_sucesso, $detalhe_sucesso);
            $stmt_insert->execute();
            $stmt_insert->close();
            
        } else {
            $resultados[] = [
                'dispositivo' => $dispositivo['nome'],
                'ip' => $dispositivo['ip'],
                'status' => 'falha',
                'mensagem' => "Falha na sincronização. Código: $codigo_sync, Erro: $erro_sync"
            ];
            $total_falhas++;
            
            // Registrar falha na tabela facial_sync_culto
            $sql_insert = "INSERT INTO facial_sync_culto 
                          (id_usuario, id_dispositivo, data, status, origem, tentativas, detalhes) 
                          VALUES (?, ?, CURDATE(), 'falha', 'culto', 1, ?)
                          ON DUPLICATE KEY UPDATE 
                          status = 'falha', 
                          tentativas = tentativas + 1,
                          ultima_tentativa = NOW(),
                          detalhes = ?";
            
            $stmt_insert = $conn->prepare($sql_insert);
            $detalhe_falha = "Falha na sincronização inteligente. Código: $codigo_sync, Erro: $erro_sync";
            $stmt_insert->bind_param("iisss", $usuario_id, $dispositivo['id'], $detalhe_falha, $detalhe_falha);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
    }
    
    echo json_encode([
        'status' => 'sucesso',
        'usuario' => $usuario['nome'],
        'total_verificados' => $total_verificados,
        'total_sincronizados' => $total_sincronizados,
        'total_ja_sincronizados' => $total_ja_sincronizados,
        'total_falhas' => $total_falhas,
        'resultados' => $resultados
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro na sincronização inteligente: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
