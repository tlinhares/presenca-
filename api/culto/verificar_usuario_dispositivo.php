<?php
/**
 * API para verificar se um usuário está sincronizado no dispositivo
 */
header('Content-Type: application/json; charset=UTF-8');
include_once(__DIR__ . '/../../api/conexao.php');

$usuario_id = $_POST['usuario_id'] ?? null;
$dispositivo_id = $_POST['dispositivo_id'] ?? null;

if (!$usuario_id || !$dispositivo_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID do usuário e dispositivo são obrigatórios']);
    exit;
}

try {
    // Buscar dados do dispositivo
    $sql_dispositivo = "SELECT id, nome, ip, porta, usuario, senha 
                        FROM dispositivos_faciais 
                        WHERE id = ? AND ativo = 1 AND tipo_dispositivo = 'culto'";
    
    $stmt_dispositivo = $conn->prepare($sql_dispositivo);
    $stmt_dispositivo->bind_param("i", $dispositivo_id);
    $stmt_dispositivo->execute();
    $result_dispositivo = $stmt_dispositivo->get_result();
    
    if ($result_dispositivo->num_rows == 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dispositivo não encontrado ou inativo']);
        exit;
    }
    
    $dispositivo = $result_dispositivo->fetch_assoc();
    
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
        // Verificar se a resposta contém dados do usuário
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
            // Verificar se a resposta contém dados de foto
            $foto_existe = (strpos($resposta_foto, '<UserID>' . $usuario_id . '</UserID>') !== false && 
                           strpos($resposta_foto, '<PhotoData>') !== false);
        }
    }
    
    echo json_encode([
        'status' => 'sucesso',
        'usuario_id' => $usuario_id,
        'dispositivo_id' => $dispositivo_id,
        'dispositivo_nome' => $dispositivo['nome'],
        'dispositivo_ip' => $dispositivo['ip'],
        'usuario_existe' => $usuario_existe,
        'foto_existe' => $foto_existe,
        'precisa_sincronizar' => !$usuario_existe || !$foto_existe,
        'detalhes' => [
            'codigo_usuario' => $codigo_usuario,
            'erro_usuario' => $erro_usuario,
            'codigo_foto' => $codigo_foto,
            'erro_foto' => $erro_foto
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao verificar usuário no dispositivo: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
