<?php
// api/facial/sincronizar.php
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Função de log para registrar operações
function registrarLog($mensagem) {
    $logs_dir = __DIR__ . '/../../logs';
    
    // Verificar se o diretório de logs existe e tentar criá-lo se não existir
    if (!file_exists($logs_dir)) {
        if (!@mkdir($logs_dir, 0777, true)) {
            // Se não conseguir criar o diretório, usar diretório temporário do sistema
            $logs_dir = sys_get_temp_dir();
        }
    }
    
    // Verificar permissões de escrita no diretório
    if (!is_writable($logs_dir)) {
        // Tentar corrigir permissões
        @chmod($logs_dir, 0777);
        
        // Se ainda não for gravável, usar diretório temporário do sistema
        if (!is_writable($logs_dir)) {
            $logs_dir = sys_get_temp_dir();
        }
    }
    
    $log_file = $logs_dir . '/sincronizacao_' . date('Y-m-d') . '.log';
    $time = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$time] $mensagem" . PHP_EOL, FILE_APPEND);
}

// Iniciar o registro de log
registrarLog("======== INÍCIO DO PROCESSO DE SINCRONIZAÇÃO FACIAL ========");

try {
    // Incluir arquivos necessários
    require_once __DIR__ . '/../../api/conexao.php';

    // Iniciar a sessão manualmente (se necessário)
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    // Configurações
    $limite = isset($_GET['limite']) ? intval($_GET['limite']) : 10; // Limite padrão de registros por execução
    
    registrarLog("Iniciando sincronização com limite de $limite registros");
    
    // Verificar se a tabela existe
    $result = $conn->query("SHOW TABLES LIKE 'facial_sync'");
    if ($result->num_rows == 0) {
        throw new Exception("Tabela facial_sync não existe. Execute o script preparar_sincronizacao.php primeiro.");
    }
    
    // Buscar registros pendentes
    $sql = "
        SELECT fs.id, fs.id_usuario, fs.data, u.nome, u.foto_base64
        FROM facial_sync fs
        JOIN usuarios u ON fs.id_usuario = u.id
        WHERE fs.status = 'pendente'
        ORDER BY fs.data ASC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta: " . $conn->error);
    }
    
    $stmt->bind_param("i", $limite);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $total_para_sincronizar = $result->num_rows;
    registrarLog("Total de registros para sincronizar: $total_para_sincronizar");
    
    // Se não houver registros para sincronizar, terminar o processo
    if ($total_para_sincronizar == 0) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Nenhum registro pendente para sincronização.',
            'processados' => 0
        ]);
        registrarLog("Nenhum registro pendente. Processo concluído.");
        exit;
    }
    
    // Preparar a atualização de registros
    $sql_atualizar = "UPDATE facial_sync SET status = ?, horario_sync = NOW(), detalhes = ? WHERE id = ?";
    $stmt_atualizar = $conn->prepare($sql_atualizar);
    
    if (!$stmt_atualizar) {
        throw new Exception("Erro ao preparar atualização: " . $conn->error);
    }
    
    // Processar cada registro
    $sincronizados = 0;
    $falhas = 0;
    $registros_processados = [];
    
    while ($row = $result->fetch_assoc()) {
        $id_sync = $row['id'];
        $id_usuario = $row['id_usuario'];
        $nome_usuario = $row['nome'];
        $data = $row['data'];
        $foto_base64 = $row['foto_base64'];
        
        registrarLog("Processando sincronização para usuário: $nome_usuario (ID: $id_usuario)");
        
        // Preparar dados do usuário para sincronização
        $usuario = [
            'id' => $id_usuario,
            'nome' => $nome_usuario,
            'foto_base64' => $foto_base64
        ];
        
        // Sincronizar usuário com o dispositivo
        $resultado = sincronizarUsuarioDispositivo($usuario, $conn);
        
        if ($resultado['sucesso']) {
            $status = 'sincronizado';
            $detalhes = $resultado['mensagem'];
            $sincronizados++;
            registrarLog("SUCESSO - " . $resultado['mensagem']);
        } else {
            $status = 'falha';
            $detalhes = $resultado['mensagem'];
            $falhas++;
            registrarLog("FALHA - " . $resultado['mensagem']);
        }
        
        // Atualizar o status do registro
        $stmt_atualizar->bind_param("ssi", $status, $detalhes, $id_sync);
        
        if (!$stmt_atualizar->execute()) {
            registrarLog("ERRO ao atualizar registro: " . $stmt_atualizar->error);
        }
        
        $registros_processados[] = [
            'id' => $id_usuario,
            'nome' => $nome_usuario,
            'data' => $data,
            'status' => $status,
            'detalhes' => $detalhes
        ];
    }
    
    registrarLog("Processo concluído. Sincronizados: $sincronizados, Falhas: $falhas");
    
    // Responder com o resultado da operação
    echo json_encode([
        'status' => 'ok',
        'mensagem' => "Processo concluído. Foram sincronizados $sincronizados usuários, com $falhas falhas.",
        'processados' => count($registros_processados),
        'sincronizados' => $sincronizados,
        'falhas' => $falhas,
        'registros' => $registros_processados
    ]);
    
    registrarLog("======== FIM DO PROCESSO DE SINCRONIZAÇÃO FACIAL ========");
    
} catch (Exception $e) {
    registrarLog("ERRO: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Ocorreu um erro: ' . $e->getMessage()
    ]);
    
    registrarLog("======== FIM DO PROCESSO COM ERRO ========");
}

/**
 * Sincroniza um usuário com o dispositivo de reconhecimento facial
 *
 * @param array $usuario Array contendo id, nome, e opcionalmente foto_base64
 * @param object $conn Conexão com o banco de dados
 * @return array Resultado da sincronização com status e mensagem
 */
function sincronizarUsuarioDispositivo($usuario, $conn) {
    // Validar parâmetros
    if (empty($usuario['id']) || empty($usuario['nome'])) {
        registrarLog("ERRO: Parâmetros de usuário inválidos");
        return [
            'sucesso' => false,
            'mensagem' => 'Dados de usuário inválidos'
        ];
    }
    
    registrarLog("Iniciando sincronização para usuário #{$usuario['id']} - {$usuario['nome']}");
    
    try {
        // Buscar configurações do dispositivo
        $ip_dispositivo = obterConfiguracao('ip_dispositivo_facial', '10.144.129.69', $conn);
        $porta_dispositivo = obterConfiguracao('porta_dispositivo_facial', '80', $conn);
        $usuario_dispositivo = obterConfiguracao('usuario_dispositivo_facial', 'admin', $conn);
        $senha_dispositivo = obterConfiguracao('senha_dispositivo_facial', 'Arcs2901', $conn);
        
        registrarLog("Configurações do dispositivo: IP=$ip_dispositivo, Porta=$porta_dispositivo");
        
        // Preparar dados para envio
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
        
        $json_dados = json_encode($dados_usuario);
        registrarLog("Dados JSON preparados para envio: " . $json_dados);
        
        // Construir URL da API
        $url = "http://{$ip_dispositivo}:{$porta_dispositivo}/cgi-bin/AccessUser.cgi?action=insertMulti";
        registrarLog("URL do endpoint: $url");
        
        // Verificar se cURL está disponível
        if (!function_exists('curl_init')) {
            registrarLog("ERRO: A extensão cURL não está disponível");
            return ['sucesso' => false, 'mensagem' => 'Extensão cURL não disponível no servidor'];
        }
        
        // Usar cURL para enviar a requisição
        $ch = curl_init();
        
        // Configurações básicas do cURL
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch, CURLOPT_USERPWD, "$usuario_dispositivo:$senha_dispositivo");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_dados);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Executar a requisição
        registrarLog("Enviando requisição...");
        $resposta = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_erro = curl_error($ch);
        
        registrarLog("Código HTTP: $http_code, Resposta: $resposta, Erro: $curl_erro");
        
        if ($http_code != 200 || $resposta === false) {
            curl_close($ch);
            registrarLog("Erro na comunicação HTTP $http_code");
            return ['sucesso' => false, 'mensagem' => "Erro na comunicação: $curl_erro (HTTP $http_code)"];
        }
        
        curl_close($ch);
        
        // Verificação de resposta OK
        if (trim($resposta) === 'OK') {
            registrarLog("Usuário sincronizado com sucesso");
            
            // Se tiver foto, enviar foto também
            if (!empty($usuario['foto_base64'])) {
                $resultado_foto = enviarFotoUsuario($usuario['id'], $usuario['foto_base64'], $ip_dispositivo, $porta_dispositivo, $usuario_dispositivo, $senha_dispositivo);
                
                if ($resultado_foto['sucesso']) {
                    registrarLog("Foto do usuário sincronizada com sucesso");
                    return ['sucesso' => true, 'mensagem' => 'Usuário e foto sincronizados com sucesso'];
                } else {
                    registrarLog("Erro ao sincronizar foto: " . $resultado_foto['mensagem']);
                    return ['sucesso' => true, 'mensagem' => 'Usuário sincronizado com sucesso, mas houve erro ao sincronizar foto'];
                }
            }
            
            return ['sucesso' => true, 'mensagem' => 'Usuário sincronizado com sucesso'];
        } else {
            registrarLog("Erro na resposta do dispositivo: [" . trim($resposta) . "]");
            return ['sucesso' => false, 'mensagem' => 'Erro na resposta do dispositivo: ' . trim($resposta)];
        }
        
    } catch (Exception $e) {
        registrarLog("ERRO DE EXCEÇÃO: " . $e->getMessage());
        return [
            'sucesso' => false,
            'mensagem' => 'Erro durante sincronização: ' . $e->getMessage()
        ];
    }
}

/**
 * Envia a foto do usuário para o dispositivo
 *
 * @param int $userId ID do usuário
 * @param string $photoBase64 Foto em formato base64
 * @param string $deviceIp IP do dispositivo
 * @param string $devicePort Porta do dispositivo
 * @param string $deviceUser Usuário do dispositivo
 * @param string $devicePass Senha do dispositivo
 * @return array Resultado do envio com status e mensagem
 */
function enviarFotoUsuario($userId, $photoBase64, $deviceIp, $devicePort, $deviceUser, $devicePass) {
    registrarLog("Iniciando envio de foto para usuário #$userId");
    
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
    
    $json_dados = json_encode($data);
    
    // Inicializar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_dados);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "$deviceUser:$devicePass");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Tempo maior para uploads de imagens
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    // Executar a requisição
    $resposta = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_erro = curl_error($ch);
    
    registrarLog("Resposta envio de foto - Código HTTP: $http_code, Resposta: $resposta, Erro: $curl_erro");
    
    curl_close($ch);
    
    if ($http_code != 200 || $resposta === false) {
        return [
            'sucesso' => false,
            'mensagem' => "Erro ao enviar foto: $curl_erro (HTTP $http_code)"
        ];
    }
    
    // Verificar resposta
    if (trim($resposta) === 'OK') {
        return [
            'sucesso' => true,
            'mensagem' => 'Foto sincronizada com sucesso'
        ];
    } else {
        return [
            'sucesso' => false,
            'mensagem' => 'Erro na resposta ao enviar foto: ' . trim($resposta)
        ];
    }
}

/**
 * Obtém uma configuração do banco de dados
 *
 * @param string $chave Nome da configuração
 * @param string $padrao Valor padrão caso não encontre
 * @param object $conn Conexão com o banco de dados
 * @return string Valor da configuração
 */
function obterConfiguracao($chave, $padrao = '', $conn = null) {
    if ($conn === null) {
        return $padrao;
    }
    
    $stmt = $conn->prepare("SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1");
    if (!$stmt) {
        return $padrao;
    }
    
    $stmt->bind_param("s", $chave);
    $stmt->execute();
    $stmt->bind_result($valor);
    $encontrado = $stmt->fetch();
    $stmt->close();
    
    if ($encontrado && !empty($valor)) {
        return $valor;
    }
    
    return $padrao;
}
?> 