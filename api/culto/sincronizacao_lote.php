<?php
/**
 * API para sincronização em lote de todos os usuários do culto
 */
header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/timezone.php';
// Tornar execução robusta para grandes volumes
@set_time_limit(0);
@ini_set('memory_limit', '512M');

// Função para comprimir foto base64
function comprimirFoto($foto_base64, $tamanho_maximo) {
    // Decodificar base64 para imagem
    $imagem_binaria = base64_decode($foto_base64);
    $imagem = imagecreatefromstring($imagem_binaria);
    
    if (!$imagem) {
        return $foto_base64; // Retorna original se não conseguir processar
    }
    
    // Obter dimensões originais
    $largura_original = imagesx($imagem);
    $altura_original = imagesy($imagem);
    
    // Calcular nova dimensão (reduzir proporcionalmente)
    $fator_reducao = sqrt($tamanho_maximo / strlen($foto_base64));
    $nova_largura = intval($largura_original * $fator_reducao);
    $nova_altura = intval($altura_original * $fator_reducao);
    
    // Garantir dimensões mínimas
    $nova_largura = max($nova_largura, 100);
    $nova_altura = max($nova_altura, 100);
    
    // Criar nova imagem redimensionada
    $nova_imagem = imagecreatetruecolor($nova_largura, $nova_altura);
    imagecopyresampled($nova_imagem, $imagem, 0, 0, 0, 0, $nova_largura, $nova_altura, $largura_original, $altura_original);
    
    // Converter para base64 com compressão JPEG
    ob_start();
    imagejpeg($nova_imagem, null, 80); // Qualidade 80%
    $imagem_comprimida = ob_get_contents();
    ob_end_clean();
    
    // Limpar memória
    imagedestroy($imagem);
    imagedestroy($nova_imagem);
    
    return base64_encode($imagem_comprimida);
}

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

try {
    include_once(__DIR__ . '/../../api/conexao.php');
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro de conexão: ' . $e->getMessage()]);
    exit;
}

try {
    // Verificar se a tabela facial_sync_culto existe
    $result = $conn->query("SHOW TABLES LIKE 'facial_sync_culto'");
    if ($result->num_rows == 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Tabela facial_sync_culto não existe']);
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
    
    // Configurar paginação para processar em lotes e reduzir memória
    $batchSize = 200;
    $offset = 0;
    
    // Inicializar estrutura de resultados sem carregar tudo em memória
    
    // Como usamos MYSQLI_USE_RESULT, não temos count direto confiável; computaremos on-the-fly
    $resultados_gerais = [
        'total_usuarios' => 0,
        'total_dispositivos' => count($dispositivos),
        'usuarios_processados' => 0,
        'usuarios_sincronizados' => 0,
        'usuarios_ja_sincronizados' => 0,
        'usuarios_falhas' => 0,
        'usuarios_removidos' => 0,
        'usuarios_remocao_falhas' => 0,
        'detalhes_por_usuario' => [],
        'detalhes_remocoes' => []
    ];
    
    // Processar TODOS os usuários (independente da flag culto)
    $detalhesMax = 500; // limitar detalhes para evitar resposta gigante
    $encontrouAlgum = false;
    while (true) {
        $sql_usuarios_paginado = "SELECT u.id, u.nome, u.foto_base64, u.culto, u.ativo
                                   FROM usuarios u
                                   ORDER BY u.nome
                                   LIMIT $batchSize OFFSET $offset";
        $res = $conn->query($sql_usuarios_paginado);
        if (!$res) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro na consulta de usuários: ' . $conn->error]);
            exit;
        }
        if ($res->num_rows === 0) {
            break;
        }
        $encontrouAlgum = true;
        while ($usuario = $res->fetch_assoc()) {
            $resultados_gerais['total_usuarios']++;
            $resultados_gerais['usuarios_processados']++;
        
        $resultado_usuario = [
            'usuario_id' => $usuario['id'],
            'usuario_nome' => $usuario['nome'],
            'culto' => (int)$usuario['culto'],
            'ativo' => (int)$usuario['ativo'],
            'dispositivos_verificados' => 0,
            'dispositivos_sincronizados' => 0,
            'dispositivos_ja_sincronizados' => 0,
            'dispositivos_removidos' => 0,
            'dispositivos_falhas' => 0,
            'detalhes_dispositivos' => []
        ];
        
        // Para cada dispositivo, processar usuário
        foreach ($dispositivos as $dispositivo) {
            $resultado_usuario['dispositivos_verificados']++;
            
            // LÓGICA SIMPLIFICADA:
            // Se usuário tem culto = 1 e está ativo: REMOVER + INSERIR (atualizar)
            // Se usuário tem culto = 0 ou inativo: APENAS REMOVER (sem verificar se existe)
            
            if ($usuario['culto'] == 1 && $usuario['ativo'] == 1) {
                // USUÁRIO DEVE ESTAR NO FACIAL - REMOVER + INSERIR
                
                // 1. Remover (sempre, para garantir atualização)
                $url_remove = construirUrlDispositivo($dispositivo['ip'], $dispositivo['porta'], "/cgi-bin/AccessUser.cgi?action=removeMulti&UserIDList[0]={$usuario['id']}");
                $resultado_remove = fazerRequisicaoDispositivo($url_remove, $dispositivo['usuario'], $dispositivo['senha']);
                $resposta_remove = $resultado_remove['resposta'];
                $codigo_remove = $resultado_remove['codigo'];
                $erro_remove = $resultado_remove['erro'];
                
                // 2. Inserir usuário (dados)
                $hoje = date('Y-m-d H:i:s');
                $valido_ate = date('Y-m-d H:i:s', strtotime('+1 year'));
                
                $dados_usuario = [
                    "UserList" => [
                        [
                            "UserID" => (string)$usuario['id'],
                            "UserName" => $usuario['nome'],
                            "UserType" => 0, // General user
                            "Authority" => 2, // Normal user (não administrador)
                            "Password" => "123456", // Senha padrão
                            "Doors" => [0], // Todas as portas
                            "TimeSections" => [255], // Sempre permitido
                            "ValidFrom" => $hoje,
                            "ValidTo" => $valido_ate
                        ]
                    ]
                ];
                
                $url_sync = construirUrlDispositivo($dispositivo['ip'], $dispositivo['porta'], "/cgi-bin/AccessUser.cgi?action=insertMulti");
                $resultado_sync = fazerRequisicaoDispositivo($url_sync, $dispositivo['usuario'], $dispositivo['senha'], [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($dados_usuario),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen(json_encode($dados_usuario))
                    ]
                ]);
                $resposta_sync = $resultado_sync['resposta'];
                $codigo_sync = $resultado_sync['codigo'];
                $erro_sync = $resultado_sync['erro'];
                
                if ($codigo_sync == 200 && !$erro_sync) {
                    // 3. Enviar foto (se existir) - usando a mesma estrutura do sistema de almoço
                    $foto_enviada = false;
                    $foto_base64 = trim($usuario['foto_base64']);
                    
                    if (!empty($foto_base64)) {
                        // Verificar tamanho da foto e comprimir se necessário
                        $foto_size = strlen($foto_base64);
                        $foto_para_envio = $foto_base64;
                        
                        if ($foto_size > 200000) {
                            // Tentar comprimir a foto apenas se for muito grande
                            $foto_para_envio = comprimirFoto($foto_base64, 200000);
                        }
                        
                        if (strlen($foto_para_envio) <= 250000) {
                            $url_face = construirUrlDispositivo($dispositivo['ip'], $dispositivo['porta'], "/cgi-bin/AccessFace.cgi?action=insertMulti");
                            $payload_face = json_encode([
                                "FaceList" => [
                                    [
                                        "UserID" => (string)$usuario['id'],
                                        "PhotoData" => [$foto_para_envio]
                                    ]
                                ]
                            ]);

                            $resultado_foto = fazerRequisicaoDispositivo($url_face, $dispositivo['usuario'], $dispositivo['senha'], [
                                CURLOPT_POST => true,
                                CURLOPT_POSTFIELDS => $payload_face,
                                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                                CURLOPT_TIMEOUT => 10 // Reduzido de 30 para 10 (fotos são grandes mas não precisam de 30s)
                            ]);
                            $resposta_foto = $resultado_foto['resposta'];
                            $http_code_foto = $resultado_foto['codigo'];
                            $erro_foto = $resultado_foto['erro'];
                            
                            $foto_enviada = (strpos($resposta_foto, 'OK') !== false);
                            if (!$foto_enviada) {
                                error_log("Falha ao enviar foto - {$usuario['nome']} no dispositivo {$dispositivo['nome']}: HTTP $http_code_foto, Erro: $erro_foto");
                            }
                        }
                    }
                    
                    $resultado_usuario['dispositivos_sincronizados']++;
                    $mensagem_sucesso = 'Usuário atualizado com sucesso (removido + inserido)';
                    if ($foto_enviada) {
                        $mensagem_sucesso .= ' + foto enviada';
                    } elseif (!empty($usuario['foto_base64'])) {
                        $mensagem_sucesso .= ' (foto não enviada)';
                    }
                    
                    $resultado_usuario['detalhes_dispositivos'][] = [
                        'dispositivo' => $dispositivo['nome'],
                        'status' => 'sincronizado',
                        'mensagem' => $mensagem_sucesso
                    ];
                    
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
                    $detalhe_sucesso = "Sincronização em lote realizada com sucesso. Código: $codigo_sync" . 
                                      ($foto_enviada ? " + foto enviada" : (!empty($usuario['foto_base64']) ? " (foto falhou)" : " (sem foto)"));
                    $stmt_insert->bind_param("iiss", $usuario['id'], $dispositivo['id'], $detalhe_sucesso, $detalhe_sucesso);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                    
                } else {
                    $resultado_usuario['dispositivos_falhas']++;
                    $resultado_usuario['detalhes_dispositivos'][] = [
                        'dispositivo' => $dispositivo['nome'],
                        'status' => 'falha',
                        'mensagem' => "Falha na sincronização. Código: $codigo_sync, Erro: $erro_sync"
                    ];
                    
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
                    $detalhe_falha = "Falha na sincronização em lote. Código: $codigo_sync, Erro: $erro_sync";
                    $stmt_insert->bind_param("iiss", $usuario['id'], $dispositivo['id'], $detalhe_falha, $detalhe_falha);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
                
            } else {
                // USUÁRIO NÃO DEVE ESTAR NO FACIAL - APENAS REMOVER (sem verificar se existe)
                $url_remove = construirUrlDispositivo($dispositivo['ip'], $dispositivo['porta'], "/cgi-bin/AccessUser.cgi?action=removeMulti&UserIDList[0]={$usuario['id']}");
                $resultado_remove = fazerRequisicaoDispositivo($url_remove, $dispositivo['usuario'], $dispositivo['senha']);
                $resposta_remove = $resultado_remove['resposta'];
                $codigo_remove = $resultado_remove['codigo'];
                $erro_remove = $resultado_remove['erro'];
                
                if ($codigo_remove == 200 && !$erro_remove && trim($resposta_remove) === 'OK') {
                    $resultado_usuario['dispositivos_removidos']++;
                    $resultado_usuario['detalhes_dispositivos'][] = [
                        'dispositivo' => $dispositivo['nome'],
                        'status' => 'removido',
                        'mensagem' => 'Usuário removido com sucesso (culto=0 ou inativo)'
                    ];
                    
                    // Registrar remoção na tabela facial_sync_culto
                    $sql_insert = "INSERT INTO facial_sync_culto 
                                  (id_usuario, id_dispositivo, data, status, origem, tentativas, detalhes) 
                                  VALUES (?, ?, CURDATE(), 'removido', 'culto', 1, ?)
                                  ON DUPLICATE KEY UPDATE 
                                  status = 'removido', 
                                  tentativas = tentativas + 1,
                                  ultima_tentativa = NOW(),
                                  detalhes = ?";
                    
                    $stmt_insert = $conn->prepare($sql_insert);
                    $detalhe_remocao = "Usuário removido com sucesso. Código: $codigo_remove, Resposta: " . trim($resposta_remove);
                    $stmt_insert->bind_param("iiss", $usuario['id'], $dispositivo['id'], $detalhe_remocao, $detalhe_remocao);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                    
                } else {
                    $resultado_usuario['dispositivos_falhas']++;
                    $resultado_usuario['detalhes_dispositivos'][] = [
                        'dispositivo' => $dispositivo['nome'],
                        'status' => 'falha',
                        'mensagem' => "Falha na remoção. Código: $codigo_remove, Erro: $erro_remove, Resposta: " . trim($resposta_remove)
                    ];
                }
            }
        }
        
        // Atualizar contadores gerais
        if ($resultado_usuario['dispositivos_sincronizados'] > 0) {
            $resultados_gerais['usuarios_sincronizados']++;
        }
        if ($resultado_usuario['dispositivos_removidos'] > 0) {
            $resultados_gerais['usuarios_removidos']++;
        }
        if ($resultado_usuario['dispositivos_falhas'] > 0) {
            $resultados_gerais['usuarios_falhas']++;
        }
        
            if (count($resultados_gerais['detalhes_por_usuario']) < $detalhesMax) {
                $resultados_gerais['detalhes_por_usuario'][] = $resultado_usuario;
            }
        }
        $res->free();
        $offset += $batchSize;
    }
    if (!$encontrouAlgum) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Nenhum usuário encontrado']);
        exit;
    }

    
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Sincronização em lote concluída',
        'resultados' => $resultados_gerais
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro na sincronização em lote: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
