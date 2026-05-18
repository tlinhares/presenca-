<?php
/**
 * sincronizarUsuario.php - Sincroniza um usuário com o dispositivo facial
 * 
 * Esta versão é compatível com PHP 5.5.38 e não utiliza a extensão mysqlnd.
 * 
 * @version 1.1
 * @modified 2023-11-10
 */

if (!function_exists('registrarLogSinc')) {
    /**
     * Registra mensagens no log de sincronização
     */
    function registrarLogSinc($mensagem, $log_file = null) {
        if ($log_file === null) {
            $logs_dir = __DIR__ . '/../../logs';
            if (!file_exists($logs_dir)) {
                mkdir($logs_dir, 0777, true);
            }
            $log_file = $logs_dir . '/presenca_sincronizacao_' . date('Y-m-d') . '.log';
        }
        
        $time = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$time] $mensagem" . PHP_EOL, FILE_APPEND);
    }
}

/**
 * Converte imagem para base64
 * 
 * @param string $image_path Caminho da imagem
 * @return string|null Imagem em base64 ou null em caso de erro
 */
function imageToBase64($image_path) {
    if (!file_exists($image_path)) {
        return null;
    }
    
    return base64_encode(file_get_contents($image_path));
}

/**
 * Obtém a data e hora atual no formato correto
 * 
 * @return string Data e hora atual no formato 'Y-m-d H:i:s'
 */
function getCurrentDateTime() {
    return date('Y-m-d H:i:s');
}

/**
 * Formata data para o formato esperado pelo dispositivo
 * 
 * @param string|int $dateTime Data a formatar (string ou timestamp)
 * @return string Data formatada (AAAAMMDD HHMMSS)
 */
function formatDateForDevice($dateTime) {
    if (is_string($dateTime)) {
        $dateTime = strtotime($dateTime);
    }
    // Formato AAAAMMDD HHMMSS
    return date('Ymd', $dateTime) . ' ' . date('His', $dateTime);
}

/**
 * Função para fazer upload da foto do usuário
 * 
 * @param int $userId ID do usuário
 * @param string $photoBase64 Foto em base64
 * @param string $deviceIp IP do dispositivo
 * @param string $devicePort Porta do dispositivo
 * @param string $deviceUser Usuário do dispositivo
 * @param string $devicePass Senha do dispositivo
 * @return array Resultado do upload com status e mensagem
 */
function uploadUserFace($userId, $photoBase64, $deviceIp, $devicePort = '80', $deviceUser = 'admin', $devicePass = 'Arcs2901') {
    // Endpoint correto para atualizar a foto facial
    $url = "http://$deviceIp:$devicePort/cgi-bin/AccessFace.cgi?action=updateMulti";
    
    // Estrutura de dados correta para o endpoint
    $data = [
        "FaceList" => [
            [
                "UserID" => $userId,
                "PhotoData" => [$photoBase64]
            ]
        ]
    ];
    
    // Salvar JSON para debug
    $debug_dir = __DIR__ . '/../../logs/debug';
    if (!file_exists($debug_dir)) {
        mkdir($debug_dir, 0777, true);
    }
    $debug_file = $debug_dir . '/face_data_' . $userId . '_' . date('Ymd_His') . '.json';
    file_put_contents($debug_file, json_encode($data, JSON_PRETTY_PRINT));
    
    registrarLogSinc("Enviando foto para usuário ID: $userId - URL: $url");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "$deviceUser:$devicePass");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Tempo maior para uploads de imagens
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Salvar resposta para debug
    $debug_response = $debug_dir . '/face_response_' . $userId . '_' . date('Ymd_His') . '.txt';
    file_put_contents($debug_response, "HTTP: $httpcode\nResponse: $response\nError: $error");
    
    registrarLogSinc("Resposta do upload de foto - HTTP: $httpcode, Erro: $error");
    
    return [
        'sucesso' => ($httpcode == 200),
        'resposta' => $response,
        'erro' => $error,
        'http_code' => $httpcode,
        'mensagem' => ($httpcode == 200) ? 'Foto enviada com sucesso' : "Erro ao enviar foto: $error (HTTP $httpcode)"
    ];
}

/**
 * Sincroniza um usuário com o dispositivo de reconhecimento facial
 * 
 * @param array $usuario Dados do usuário (id, nome, foto_base64)
 * @param string $ip_dispositivo IP do dispositivo
 * @param string $porta Porta do dispositivo (opcional, padrão 80)
 * @param string $usuario_dispositivo Usuário do dispositivo (opcional, padrão 'admin')
 * @param string $senha_dispositivo Senha do dispositivo (opcional, padrão 'Arcs2901')
 * @param bool $com_foto Se deve enviar foto (opcional, padrão true)
 * @return array Resultado da sincronização com status e mensagem
 */
function sincronizarUsuario($usuario, $ip_dispositivo, $porta = '80', $usuario_dispositivo = 'admin', $senha_dispositivo = 'Arcs2901', $com_foto = true) {
    if (empty($usuario) || empty($usuario['id']) || empty($usuario['nome'])) {
        return [
            'sucesso' => false,
            'mensagem' => 'Dados do usuário incompletos'
        ];
    }
    
    // Validar IP
    if (empty($ip_dispositivo) || !filter_var($ip_dispositivo, FILTER_VALIDATE_IP)) {
        return [
            'sucesso' => false,
            'mensagem' => 'IP do dispositivo inválido'
        ];
    }
    
    try {
        registrarLogSinc("Iniciando sincronização do usuário ID {$usuario['id']}, Nome: {$usuario['nome']}");
        
        // Preparar dados do usuário
        $userId = (string)$usuario['id'];
        $hoje = getCurrentDateTime();
        $valido_ate = date('Y-m-d H:i:s', strtotime('+5 years'));
        
        // Formatar dados para o dispositivo
        $valid_from = formatDateForDevice($hoje);
        $valid_to = formatDateForDevice($valido_ate);
        
        // Criar estrutura de dados para o usuário
        $userData = [
            "UserID" => $userId,
            "UserName" => $usuario['nome'],
            "UserType" => 0, // General user
            "Authority" => 2, // Normal user (não administrador)
            "Password" => "123456", // Senha padrão
            "Doors" => [0], // Todas as portas
            "TimeSections" => [255], // Sempre permitido
            "ValidFrom" => $valid_from,
            "ValidTo" => $valid_to
        ];
        
        // Salvar dados para debug
        $debug_dir = __DIR__ . '/../../logs/debug';
        if (!file_exists($debug_dir)) {
            mkdir($debug_dir, 0777, true);
        }
        $debug_file = $debug_dir . '/user_data_' . $userId . '_' . date('Ymd_His') . '.json';
        file_put_contents($debug_file, json_encode(["UserList" => [$userData]], JSON_PRETTY_PRINT));
        
        // Cadastrar o usuário
        $resultado = insertUser($userData, $ip_dispositivo, $porta, $usuario_dispositivo, $senha_dispositivo);
        
        // Verificar resultado
        if (!$resultado['sucesso']) {
            registrarLogSinc("Falha ao registrar usuário: " . $resultado['mensagem']);
            return [
                'sucesso' => false,
                'mensagem' => 'Falha ao registrar usuário: ' . $resultado['mensagem']
            ];
        }
        
        registrarLogSinc("Usuário registrado com sucesso");
        
        // Se não for para enviar foto ou não tiver foto, retorna aqui
        if (!$com_foto || empty($usuario['foto_base64'])) {
            return [
                'sucesso' => true,
                'mensagem' => 'Usuário registrado com sucesso (sem foto)'
            ];
        }
        
        // Se tem foto, enviar
        if (!empty($usuario['foto_base64'])) {
            registrarLogSinc("Enviando foto do usuário ID: {$usuario['id']}");
            
            // Verificar se a foto tem o prefixo correto
            $foto_base64 = $usuario['foto_base64'];
            if (strpos($foto_base64, 'data:image') === 0 && strpos($foto_base64, 'base64,') !== false) {
                $foto_base64 = explode('base64,', $foto_base64)[1];
            }
            
            // Enviar foto
            $resultado_foto = uploadUserFace(
                $userId, 
                $foto_base64, 
                $ip_dispositivo, 
                $porta, 
                $usuario_dispositivo, 
                $senha_dispositivo
            );
            
            // Verificar resultado da foto
            if ($resultado_foto['sucesso']) {
                registrarLogSinc("Foto enviada com sucesso");
                return [
                    'sucesso' => true,
                    'mensagem' => 'Usuário registrado e foto enviada com sucesso'
                ];
            } else {
                registrarLogSinc("Falha ao enviar foto: " . $resultado_foto['mensagem']);
                return [
                    'sucesso' => true,
                    'mensagem' => 'Usuário registrado com sucesso, mas falha ao enviar foto: ' . $resultado_foto['mensagem']
                ];
            }
        }
        
        return [
            'sucesso' => true,
            'mensagem' => 'Usuário registrado com sucesso'
        ];
        
    } catch (Exception $e) {
        registrarLogSinc("Exceção ao sincronizar usuário: " . $e->getMessage());
        return [
            'sucesso' => false,
            'mensagem' => "Erro ao sincronizar usuário: " . $e->getMessage()
        ];
    }
}

/**
 * Cadastra um usuário no dispositivo usando a API do fabricante
 * 
 * @param array $userData Dados do usuário formatados
 * @param string $deviceIp IP do dispositivo
 * @param string $devicePort Porta do dispositivo
 * @param string $deviceUser Usuário do dispositivo
 * @param string $devicePass Senha do dispositivo
 * @return array Resultado do cadastro
 */
function insertUser($userData, $deviceIp, $devicePort = '80', $deviceUser = 'admin', $devicePass = 'Arcs2901') {
    // Endpoint correto para inserção de usuários
    $url = "http://$deviceIp:$devicePort/cgi-bin/AccessUser.cgi?action=insertMulti";
    
    // Preparar dados no formato correto
    $data = [
        "UserList" => [$userData]
    ];
    
    registrarLogSinc("Enviando requisição para cadastro de usuário - URL: $url");
    
    // Inicializar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "$deviceUser:$devicePass");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Executar a requisição
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Salvar resposta para debug
    $debug_dir = __DIR__ . '/../../logs/debug';
    if (!file_exists($debug_dir)) {
        mkdir($debug_dir, 0777, true);
    }
    $debug_response = $debug_dir . '/user_response_' . date('Ymd_His') . '.txt';
    file_put_contents($debug_response, "HTTP: $httpcode\nResponse: $response\nError: $error");
    
    registrarLogSinc("Resposta do cadastro de usuário - HTTP: $httpcode, Erro: $error");
    
    $sucesso = ($httpcode == 200 && (trim($response) == "OK" || empty($error)));
    
    return [
        'sucesso' => $sucesso,
        'resposta' => $response,
        'erro' => $error,
        'http_code' => $httpcode,
        'mensagem' => $sucesso ? 'Usuário cadastrado com sucesso' : "Erro ao cadastrar usuário: $error (HTTP $httpcode)"
    ];
} 