<?php
/**
 * API para re-sincronizar um usuário específico (remove e adiciona novamente)
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/timezone.php';
include_once(__DIR__ . '/../../api/conexao.php');

// Função auxiliar para construir URL do dispositivo (HTTP ou HTTPS)
function construirUrlDispositivo($ip, $porta, $endpoint) {
    $protocolo = ($porta == 443) ? 'https' : 'http';
    $url = "{$protocolo}://{$ip}";
    if ($porta != 80 && $porta != 443) {
        $url .= ":{$porta}";
    }
    return $url . $endpoint;
}

// Cache global para protocolo por dispositivo (evita tentativas duplas)
$GLOBALS['protocolo_cache_culto'] = $GLOBALS['protocolo_cache_culto'] ?? [];

// Função auxiliar para fazer requisição cURL com suporte a HTTPS e redirecionamentos
function fazerRequisicaoDispositivo($url, $usuario, $senha, $opcoes = []) {
    // Extrair IP da URL para cache
    preg_match('/(?:https?:\/\/)?([^:\/]+)/', $url, $matches);
    $ip = $matches[1] ?? '';
    
    // Se já sabemos que este dispositivo usa HTTPS, usar direto
    if (isset($GLOBALS['protocolo_cache_culto'][$ip]) && $GLOBALS['protocolo_cache_culto'][$ip] === 'https' && strpos($url, 'https://') === false) {
        $url = str_replace('http://', 'https://', $url);
    }
    
    $ch = curl_init();
    
    // Configurações padrão otimizadas (timeouts menores)
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "{$usuario}:{$senha}");
    curl_setopt($ch, CURLOPT_TIMEOUT, $opcoes[CURLOPT_TIMEOUT] ?? 5); // Reduzido de 20 para 5
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // Reduzido de 10 para 3
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 2); // Reduzido de 5 para 2
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    // Aplicar opções adicionais
    if (isset($opcoes[CURLOPT_POST])) {
        curl_setopt($ch, CURLOPT_POST, $opcoes[CURLOPT_POST]);
    }
    if (isset($opcoes[CURLOPT_POSTFIELDS])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opcoes[CURLOPT_POSTFIELDS]);
    }
    if (isset($opcoes[CURLOPT_HTTPHEADER]) && is_array($opcoes[CURLOPT_HTTPHEADER])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $opcoes[CURLOPT_HTTPHEADER]);
    }
    
    $resposta = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);
    
    // Se recebeu 302 ou 401 e não está usando HTTPS, tentar HTTPS uma vez e cachear
    if (($codigo == 302 || $codigo == 401) && strpos($url, 'https://') === false && !isset($GLOBALS['protocolo_cache_culto'][$ip])) {
        $url_https = str_replace('http://', 'https://', $url);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_https);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch, CURLOPT_USERPWD, "{$usuario}:{$senha}");
        curl_setopt($ch, CURLOPT_TIMEOUT, $opcoes[CURLOPT_TIMEOUT] ?? 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        if (isset($opcoes[CURLOPT_POST])) {
            curl_setopt($ch, CURLOPT_POST, $opcoes[CURLOPT_POST]);
        }
        if (isset($opcoes[CURLOPT_POSTFIELDS])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $opcoes[CURLOPT_POSTFIELDS]);
        }
        if (isset($opcoes[CURLOPT_HTTPHEADER]) && is_array($opcoes[CURLOPT_HTTPHEADER])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $opcoes[CURLOPT_HTTPHEADER]);
        }
        
        $resposta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch);
        curl_close($ch);
        
        // Cachear o protocolo que funcionou
        if ($codigo >= 200 && $codigo < 300) {
            $GLOBALS['protocolo_cache_culto'][$ip] = 'https';
        }
    }
    
    return ['resposta' => $resposta, 'codigo' => $codigo, 'erro' => $erro];
}

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
    if (!$result_dispositivos) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro na consulta de dispositivos: ' . $conn->error]);
        exit;
    }
    
    $dispositivos = [];
    while ($row = $result_dispositivos->fetch_assoc()) {
        $dispositivos[] = $row;
    }
    
    if (empty($dispositivos)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Nenhum dispositivo facial do tipo culto ativo encontrado']);
        exit;
    }
    
    $resultados = [];
    $total_processados = 0;
    $total_sucessos = 0;
    $total_falhas = 0;
    
    // Para cada dispositivo, remover e re-sincronizar
    foreach ($dispositivos as $dispositivo) {
        $total_processados++;
        
        // 1. Remover foto do usuário
        $url_remover_foto = construirUrlDispositivo($dispositivo['ip'], $dispositivo['porta'], "/cgi-bin/AccessFace.cgi?action=removeMulti&UserIDList[0]={$usuario_id}");
        $resultado_remover_foto = fazerRequisicaoDispositivo($url_remover_foto, $dispositivo['usuario'], $dispositivo['senha']);
        $resposta_remover_foto = $resultado_remover_foto['resposta'];
        $codigo_remover_foto = $resultado_remover_foto['codigo'];
        $erro_remover_foto = $resultado_remover_foto['erro'];
        
        // 2. Remover usuário
        $url_remover_usuario = construirUrlDispositivo($dispositivo['ip'], $dispositivo['porta'], "/cgi-bin/AccessUser.cgi?action=removeMulti&UserIDList[0]={$usuario_id}");
        $resultado_remover_usuario = fazerRequisicaoDispositivo($url_remover_usuario, $dispositivo['usuario'], $dispositivo['senha']);
        $resposta_remover_usuario = $resultado_remover_usuario['resposta'];
        $codigo_remover_usuario = $resultado_remover_usuario['codigo'];
        $erro_remover_usuario = $resultado_remover_usuario['erro'];
        
        // 3. Adicionar usuário novamente
        $hoje = date('Y-m-d H:i:s');
        $valido_ate = date('Y-m-d H:i:s', strtotime('+1 year'));
        
        $dados_usuario = [
            "UserList" => [
                [
                    "UserID" => (string)$usuario_id,
                    "UserName" => $usuario['nome'],
                    "UserType" => 0,
                    "Authority" => 2,
                    "Password" => "123456",
                    "Doors" => [0],
                    "TimeSections" => [255],
                    "ValidFrom" => $hoje,
                    "ValidTo" => $valido_ate
                ]
            ]
        ];
        
        $url_adicionar_usuario = construirUrlDispositivo($dispositivo['ip'], $dispositivo['porta'], "/cgi-bin/AccessUser.cgi?action=insertMulti");
        $resultado_adicionar_usuario = fazerRequisicaoDispositivo($url_adicionar_usuario, $dispositivo['usuario'], $dispositivo['senha'], [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($dados_usuario),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($dados_usuario))
            ]
        ]);
        $resposta_adicionar_usuario = $resultado_adicionar_usuario['resposta'];
        $codigo_adicionar_usuario = $resultado_adicionar_usuario['codigo'];
        $erro_adicionar_usuario = $resultado_adicionar_usuario['erro'];
        
        // 4. Adicionar foto
        $dados_foto = [
            "FaceList" => [
                [
                    "UserID" => (string)$usuario_id,
                    "PhotoData" => [$usuario['foto_base64']]
                ]
            ]
        ];
        
        $url_adicionar_foto = construirUrlDispositivo($dispositivo['ip'], $dispositivo['porta'], "/cgi-bin/AccessFace.cgi?action=insertMulti");
        $resultado_adicionar_foto = fazerRequisicaoDispositivo($url_adicionar_foto, $dispositivo['usuario'], $dispositivo['senha'], [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($dados_foto),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($dados_foto))
            ]
        ]);
        $resposta_adicionar_foto = $resultado_adicionar_foto['resposta'];
        $codigo_adicionar_foto = $resultado_adicionar_foto['codigo'];
        $erro_adicionar_foto = $resultado_adicionar_foto['erro'];
        
        // Verificar se todas as operações foram bem-sucedidas
        $sucesso = ($codigo_remover_foto == 200 || $codigo_remover_foto == 404) && // 404 = usuário não existe
                   ($codigo_remover_usuario == 200 || $codigo_remover_usuario == 404) &&
                   $codigo_adicionar_usuario == 200 &&
                   $codigo_adicionar_foto == 200;
        
        if ($sucesso) {
            $total_sucessos++;
            $resultados[] = [
                'dispositivo' => $dispositivo['nome'],
                'ip' => $dispositivo['ip'],
                'status' => 'sucesso',
                'mensagem' => 'Usuário re-sincronizado com sucesso'
            ];
            
            // Registrar sucesso na tabela facial_sync_culto
            $sql_insert = "INSERT INTO facial_sync_culto 
                          (id_usuario, id_dispositivo, data, status, origem, tentativas, detalhes) 
                          VALUES (?, ?, CURDATE(), 'sincronizado', 'manual', 1, ?)
                          ON DUPLICATE KEY UPDATE 
                          status = 'sincronizado', 
                          tentativas = tentativas + 1,
                          ultima_tentativa = NOW(),
                          detalhes = ?";
            
            $stmt_insert = $conn->prepare($sql_insert);
            $detalhe_sucesso = "Re-sincronização manual realizada com sucesso";
            $stmt_insert->bind_param("iiss", $usuario_id, $dispositivo['id'], $detalhe_sucesso, $detalhe_sucesso);
            $stmt_insert->execute();
            $stmt_insert->close();
            
        } else {
            $total_falhas++;
            $resultados[] = [
                'dispositivo' => $dispositivo['nome'],
                'ip' => $dispositivo['ip'],
                'status' => 'falha',
                'mensagem' => "Falha na re-sincronização. Códigos: remover_foto=$codigo_remover_foto, remover_usuario=$codigo_remover_usuario, adicionar_usuario=$codigo_adicionar_usuario, adicionar_foto=$codigo_adicionar_foto"
            ];
            
            // Registrar falha na tabela facial_sync_culto
            $sql_insert = "INSERT INTO facial_sync_culto 
                          (id_usuario, id_dispositivo, data, status, origem, tentativas, detalhes) 
                          VALUES (?, ?, CURDATE(), 'falha', 'manual', 1, ?)
                          ON DUPLICATE KEY UPDATE 
                          status = 'falha', 
                          tentativas = tentativas + 1,
                          ultima_tentativa = NOW(),
                          detalhes = ?";
            
            $stmt_insert = $conn->prepare($sql_insert);
            $detalhe_falha = "Falha na re-sincronização manual. Códigos: remover_foto=$codigo_remover_foto, remover_usuario=$codigo_remover_usuario, adicionar_usuario=$codigo_adicionar_usuario, adicionar_foto=$codigo_adicionar_foto";
            $stmt_insert->bind_param("iiss", $usuario_id, $dispositivo['id'], $detalhe_falha, $detalhe_falha);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
    }
    
    echo json_encode([
        'status' => 'sucesso',
        'usuario' => $usuario['nome'],
        'total_processados' => $total_processados,
        'total_sucessos' => $total_sucessos,
        'total_falhas' => $total_falhas,
        'resultados' => $resultados
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro na re-sincronização: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
