<?php
include_once(__DIR__ . '/../conexao.php');
header('Content-Type: application/json');

function redimensionarImagemBase64($base64, $larguraMax = 600, $alturaMax = 800) {
    $dados = explode(',', $base64);
    $imagemBase64 = base64_decode(end($dados));
    $imagem = imagecreatefromstring($imagemBase64);

    if (!$imagem) {
        return false;
    }

    $larguraOriginal = imagesx($imagem);
    $alturaOriginal = imagesy($imagem);

    // Se a imagem já está dentro dos limites, retornar sem processar
    if ($larguraOriginal <= $larguraMax && $alturaOriginal <= $alturaMax) {
        imagedestroy($imagem);
        return base64_encode($imagemBase64);
    }

    $escala = min($larguraMax / $larguraOriginal, $alturaMax / $alturaOriginal);
    $novaLargura = (int)($larguraOriginal * $escala);
    $novaAltura = (int)($alturaOriginal * $escala);

    // Garantir dimensões mínimas
    $novaLargura = max($novaLargura, 100);
    $novaAltura = max($novaAltura, 100);

    $imagemRedimensionada = imagecreatetruecolor($novaLargura, $novaAltura);
    
    // Configurar qualidade de redimensionamento
    imagealphablending($imagemRedimensionada, false);
    imagesavealpha($imagemRedimensionada, true);
    
    imagecopyresampled($imagemRedimensionada, $imagem, 0, 0, 0, 0, $novaLargura, $novaAltura, $larguraOriginal, $alturaOriginal);

    ob_start();
    imagejpeg($imagemRedimensionada, null, 85);
    $conteudoImagem = ob_get_clean();

    imagedestroy($imagem);
    imagedestroy($imagemRedimensionada);

    return base64_encode($conteudoImagem);
}

// Função para sincronizar usuário com dispositivos faciais do tipo culto
function sincronizarUsuarioCulto($usuario_id, $conn) {
    try {
        // Buscar dados do usuário
        $stmt_usuario = $conn->prepare("SELECT id, nome, foto_base64, culto, ativo FROM usuarios WHERE id = ?");
        $stmt_usuario->bind_param("i", $usuario_id);
        $stmt_usuario->execute();
        $result_usuario = $stmt_usuario->get_result();
        
        if ($result_usuario->num_rows == 0) {
            return ['status' => 'erro', 'mensagem' => 'Usuário não encontrado'];
        }
        
        $usuario = $result_usuario->fetch_assoc();
        
        // Verificar se o usuário tem culto = 1 e está ativo
        if ($usuario['culto'] != 1 || $usuario['ativo'] != 1) {
            return ['status' => 'info', 'mensagem' => 'Usuário não é do culto ou está inativo'];
        }
        
        // Buscar dispositivos faciais do tipo 'culto' ativos
        $sql_dispositivos = "SELECT id, nome, ip, porta, usuario, senha FROM dispositivos_faciais WHERE ativo = 1 AND tipo_dispositivo = 'culto'";
        $result_dispositivos = $conn->query($sql_dispositivos);
        
        if (!$result_dispositivos || $result_dispositivos->num_rows == 0) {
            return ['status' => 'erro', 'mensagem' => 'Nenhum dispositivo facial do tipo culto ativo encontrado'];
        }
        
        $dispositivos = [];
        while ($row = $result_dispositivos->fetch_assoc()) {
            $dispositivos[] = $row;
        }
        
        $resultados = [];
        $total_sucessos = 0;
        $total_falhas = 0;
        
        // Para cada dispositivo, sincronizar o usuário
        foreach ($dispositivos as $dispositivo) {
            // 1. Remover usuário se existir
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
            
            // 2. Inserir usuário
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
            
            $url_inserir = "http://{$dispositivo['ip']}:{$dispositivo['porta']}/cgi-bin/AccessUser.cgi?action=insertMulti";
            
            $ch_inserir = curl_init();
            curl_setopt($ch_inserir, CURLOPT_URL, $url_inserir);
            curl_setopt($ch_inserir, CURLOPT_POST, true);
            curl_setopt($ch_inserir, CURLOPT_POSTFIELDS, json_encode($dados_usuario));
            curl_setopt($ch_inserir, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($dados_usuario))
            ]);
            curl_setopt($ch_inserir, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_inserir, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($ch_inserir, CURLOPT_USERPWD, "{$dispositivo['usuario']}:{$dispositivo['senha']}");
            curl_setopt($ch_inserir, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch_inserir, CURLOPT_CONNECTTIMEOUT, 5);
            
            $resposta_inserir = curl_exec($ch_inserir);
            $codigo_inserir = curl_getinfo($ch_inserir, CURLINFO_HTTP_CODE);
            curl_close($ch_inserir);
            
            // 3. Inserir foto se existir
            $foto_enviada = false;
            if (!empty($usuario['foto_base64'])) {
                $dados_foto = [
                    "FaceList" => [
                        [
                            "UserID" => (string)$usuario_id,
                            "PhotoData" => [$usuario['foto_base64']]
                        ]
                    ]
                ];
                
                $url_foto = "http://{$dispositivo['ip']}:{$dispositivo['porta']}/cgi-bin/AccessFace.cgi?action=insertMulti";
                
                $ch_foto = curl_init();
                curl_setopt($ch_foto, CURLOPT_URL, $url_foto);
                curl_setopt($ch_foto, CURLOPT_POST, true);
                curl_setopt($ch_foto, CURLOPT_POSTFIELDS, json_encode($dados_foto));
                curl_setopt($ch_foto, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen(json_encode($dados_foto))
                ]);
                curl_setopt($ch_foto, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_foto, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                curl_setopt($ch_foto, CURLOPT_USERPWD, "{$dispositivo['usuario']}:{$dispositivo['senha']}");
                curl_setopt($ch_foto, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch_foto, CURLOPT_CONNECTTIMEOUT, 5);
                
                $resposta_foto = curl_exec($ch_foto);
                $codigo_foto = curl_getinfo($ch_foto, CURLINFO_HTTP_CODE);
                curl_close($ch_foto);
                
                $foto_enviada = (strpos($resposta_foto, 'OK') !== false);
            }
            
            // Verificar se a sincronização foi bem-sucedida
            $sucesso = ($codigo_remover == 200 || $codigo_remover == 404) && // 404 = usuário não existe
                       $codigo_inserir == 200;
            
            if ($sucesso) {
                $total_sucessos++;
                $resultados[] = [
                    'dispositivo' => $dispositivo['nome'],
                    'status' => 'sucesso',
                    'mensagem' => 'Usuário sincronizado com sucesso' . ($foto_enviada ? ' (com foto)' : ' (sem foto)')
                ];
                
                // Registrar na tabela facial_sync_culto
                $sql_sync = "INSERT INTO facial_sync_culto 
                            (id_usuario, id_dispositivo, data, status, origem, tentativas, detalhes) 
                            VALUES (?, ?, CURDATE(), 'sincronizado', 'automatico', 1, ?)
                            ON DUPLICATE KEY UPDATE 
                            status = 'sincronizado', 
                            tentativas = tentativas + 1,
                            ultima_tentativa = NOW(),
                            detalhes = ?";
                
                $stmt_sync = $conn->prepare($sql_sync);
                $detalhe = "Sincronização automática realizada com sucesso" . ($foto_enviada ? ' (com foto)' : ' (sem foto)');
                $stmt_sync->bind_param("iiss", $usuario_id, $dispositivo['id'], $detalhe, $detalhe);
                $stmt_sync->execute();
                $stmt_sync->close();
                
            } else {
                $total_falhas++;
                $resultados[] = [
                    'dispositivo' => $dispositivo['nome'],
                    'status' => 'falha',
                    'mensagem' => "Falha na sincronização. Códigos: remover=$codigo_remover, inserir=$codigo_inserir"
                ];
                
                // Registrar falha na tabela facial_sync_culto
                $sql_sync = "INSERT INTO facial_sync_culto 
                            (id_usuario, id_dispositivo, data, status, origem, tentativas, detalhes) 
                            VALUES (?, ?, CURDATE(), 'falha', 'automatico', 1, ?)
                            ON DUPLICATE KEY UPDATE 
                            status = 'falha', 
                            tentativas = tentativas + 1,
                            ultima_tentativa = NOW(),
                            detalhes = ?";
                
                $stmt_sync = $conn->prepare($sql_sync);
                $detalhe_falha = "Falha na sincronização automática. Códigos: remover=$codigo_remover, inserir=$codigo_inserir";
                $stmt_sync->bind_param("iiss", $usuario_id, $dispositivo['id'], $detalhe_falha, $detalhe_falha);
                $stmt_sync->execute();
                $stmt_sync->close();
            }
        }
        
        return [
            'status' => 'sucesso',
            'mensagem' => "Sincronização automática concluída. Sucessos: $total_sucessos, Falhas: $total_falhas",
            'resultados' => $resultados
        ];
        
    } catch (Exception $e) {
        return ['status' => 'erro', 'mensagem' => 'Erro na sincronização: ' . $e->getMessage()];
    }
}

// Dados recebidos
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$senha = isset($_POST['senha']) ? trim($_POST['senha']) : '';
$categoria = isset($_POST['categoria']) ? trim($_POST['categoria']) : '';
$culto = isset($_POST['culto']) ? intval($_POST['culto']) : 0;
$gerar_qrcode = isset($_POST['gerar_qrcode']) ? 1 : 0;
$id_valor = isset($_POST['id_valor']) ? intval($_POST['id_valor']) : null;
$entidade_id = isset($_POST['entidade_id']) ? intval($_POST['entidade_id']) : null;
$telefone = isset($_POST['telefone']) ? trim($_POST['telefone']) : '';
$cpf = isset($_POST['cpf']) ? trim($_POST['cpf']) : '';

// Validação
if (empty($nome) || empty($categoria)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Preencha todos os campos obrigatórios.']);
    exit;
}

// Geração de QR Code (se solicitado)
$qrcode = null;
if ($gerar_qrcode) {
    $qrcode = uniqid('qr_');
}

// Foto (opcional)
$foto_base64 = isset($_POST['foto_base64']) ? $_POST['foto_base64'] : null;
$nova_foto = false;
if ($foto_base64) {
    $foto_base64 = preg_replace('/^data:image\/[^;]+;base64,/', '', $foto_base64);
    
    // Verificar se a foto é válida antes de processar
    $imagem_binaria = base64_decode($foto_base64);
    $imagem_teste = imagecreatefromstring($imagem_binaria);
    
    if ($imagem_teste) {
        imagedestroy($imagem_teste);
        $foto_base64 = redimensionarImagemBase64($foto_base64);
        $nova_foto = true;
    } else {
        // Se a foto não é válida, não processar
        $foto_base64 = null;
        $nova_foto = false;
    }
}

// EDIÇÃO
if ($id > 0) {
    // Se não foi enviada nova foto, busca a atual
    if (!$nova_foto) {
        $stmtFoto = $conn->prepare("SELECT foto_base64 FROM usuarios WHERE id = ?");
        $stmtFoto->bind_param("i", $id);
        $stmtFoto->execute();
        $stmtFoto->bind_result($fotoAtual);
        if ($stmtFoto->fetch()) {
            $foto_base64 = $fotoAtual;
        }
        $stmtFoto->close();
    }

    $sql = "UPDATE usuarios SET nome = ?, email = ?, categoria = ?, culto = ?, foto_base64 = ?, id_valor = ?, entidade_id = ?, telefone = ?, cpf = ?";
    $params = [$nome, $email, $categoria, $culto, $foto_base64, $id_valor, $entidade_id, $telefone, $cpf];
    // Tipos: nome(s), email(s), categoria(s), culto(i), foto_base64(s), id_valor(i), entidade_id(i), telefone(s), cpf(s)
    $types = "sssisiiss";

    if (!empty($senha)) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $sql .= ", senha = ?";
        $params[] = $senha_hash;
        $types .= "s";
    }

    if ($gerar_qrcode) {
        $sql .= ", qrcode = ?";
        $params[] = $qrcode;
        $types .= "s";
    }

    $sql .= " WHERE id = ?";
    $params[] = $id;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    $bind_names[] = $types;
    foreach ($params as $key => $value) {
        $bind_names[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);

    $ok = $stmt->execute();

    if ($ok) {
    // Se o usuário tem culto = 1, sincronizar automaticamente
    if ($culto == 1) {
        error_log("Iniciando sincronização automática para usuário $id (culto=$culto)");
        $resultado_sync = sincronizarUsuarioCulto($id, $conn);
        // Log do resultado da sincronização
        error_log("Sincronização automática para usuário $id: " . json_encode($resultado_sync));
    } else {
        error_log("Usuário $id não será sincronizado (culto=$culto)");
    }
        
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar usuário.']);
    }
    exit;
}


// CADASTRO
if (empty($senha)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'A senha é obrigatória no cadastro.']);
    exit;
}

$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

// Se id_valor ou entidade_id forem null, usar valores padrão
if ($id_valor === null) $id_valor = 1;
if ($entidade_id === null) $entidade_id = 1;

$stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, categoria, culto, qrcode, ativo, foto_base64, id_valor, entidade_id, telefone, cpf) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssssiiss", $nome, $email, $senha_hash, $categoria, $culto, $qrcode, $foto_base64, $id_valor, $entidade_id, $telefone, $cpf);

$ok = $stmt->execute();

if ($ok) {
    // Obter o ID do usuário recém-criado
    $novo_usuario_id = $conn->insert_id;
    error_log("Novo usuário criado com ID: $novo_usuario_id");
    
    // Se insert_id não funcionar, buscar pelo email
    if ($novo_usuario_id == 0) {
        $stmt_id = $conn->prepare("SELECT id FROM usuarios WHERE email = ? ORDER BY id DESC LIMIT 1");
        $stmt_id->bind_param("s", $email);
        $stmt_id->execute();
        $result_id = $stmt_id->get_result();
        if ($row_id = $result_id->fetch_assoc()) {
            $novo_usuario_id = $row_id['id'];
            error_log("ID obtido via busca por email: $novo_usuario_id");
        }
        $stmt_id->close();
    }
    
    // Se o usuário tem culto = 1, sincronizar automaticamente
    if ($culto == 1 && $novo_usuario_id > 0) {
        error_log("Iniciando sincronização automática para novo usuário $novo_usuario_id (culto=$culto)");
        $resultado_sync = sincronizarUsuarioCulto($novo_usuario_id, $conn);
        // Log do resultado da sincronização
        error_log("Sincronização automática para novo usuário $novo_usuario_id: " . json_encode($resultado_sync));
    } else {
        error_log("Novo usuário $novo_usuario_id não será sincronizado (culto=$culto, id_valido=" . ($novo_usuario_id > 0 ? 'sim' : 'não') . ")");
    }
    
    echo json_encode(['status' => 'ok']);
} else {
    error_log("Erro ao cadastrar usuário: " . $conn->error);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao cadastrar usuário.']);
}
?>
