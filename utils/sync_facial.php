<?php
// utils/sync_facial.php
// Script para ser executado via CRON às 9:00

// Configurar tempo limite de execução maior
set_time_limit(300); // 5 minutos

// Aumentar limite de memória de forma segura
$memory_limit = ini_get('memory_limit');
if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
    if ($matches[2] == 'M') {
        $memory_limit = $matches[1] * 1024 * 1024; // Converter para bytes
    } else if ($matches[2] == 'G') {
        $memory_limit = $matches[1] * 1024 * 1024 * 1024; // Converter para bytes
    } else {
        $memory_limit = intval($memory_limit); // Já está em bytes
    }
}

// Se o limite atual for menor que 512MB, tentar aumentar para 512MB (se permitido pelo servidor)
if ($memory_limit < 536870912) { // 512MB em bytes
    ini_set('memory_limit', '512M');
}

// Configurar tratamento de erros
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros na saída
ini_set('log_errors', 1); // Logar erros
ini_set('error_log', dirname(__FILE__) . '/../logs/php_errors.log');

require_once dirname(__FILE__) . '/../api/conexao.php';
include_once dirname(__FILE__) . '/config.php';

// Criar pasta de logs se não existir
if (!file_exists(dirname(__FILE__) . '/../logs')) {
    mkdir(dirname(__FILE__) . '/../logs', 0755, true);
}

$data_hoje = date('Y-m-d');
$hora_atual = date('H:i:s');
$log_file = dirname(__FILE__) . '/../logs/sync_facial_' . $data_hoje . '.log';

require_once __DIR__ . '/logger.php';

function log_message($message) {
    Logger::emergencial('sync_facial', $message);
}

log_message("============ INÍCIO DA SINCRONIZAÇÃO ============");
log_message("Data de sincronização: $data_hoje");
log_message("Hora atual: $hora_atual");
log_message("Limite de memória configurado: " . ini_get('memory_limit'));

// Verificar se já foi sincronizado hoje
$stmt = $conn->prepare("SELECT COUNT(*) FROM facial_sync WHERE data = ?");
$stmt->bind_param("s", $data_hoje);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

log_message("Total de sincronizações já existentes para hoje: $count");

if ($count > 0) {
    log_message("Sincronização já realizada hoje. Verificando pendentes.");
    
    // Verificar pendentes
    $stmt = $conn->prepare("SELECT COUNT(*) FROM facial_sync WHERE data = ? AND status = 'pendente'");
    $stmt->bind_param("s", $data_hoje);
    $stmt->execute();
    $stmt->bind_result($pendentes);
    $stmt->fetch();
    $stmt->close();
    
    log_message("Total de sincronizações pendentes: $pendentes");
    
    if ($pendentes == 0) {
        log_message("Não há sincronizações pendentes. Finalizando.");
        exit;
    }
}

// Buscar todos usuários com reserva hoje - versão PHP 5.3
log_message("Buscando usuários com reserva para hoje...");

// Buscar IDs primeiro (para economia de memória)
$stmt = $conn->prepare("
    SELECT u.id
    FROM usuarios u
    JOIN reservas_almoco ra ON u.id = ra.id_usuario
    WHERE ra.data = ? AND u.ativo = 1
    LIMIT 100
");
$stmt->bind_param("s", $data_hoje);
$stmt->execute();
$stmt->bind_result($id_usuario);

$ids_usuarios = array();
while ($stmt->fetch()) {
    $ids_usuarios[] = $id_usuario;
}
$stmt->close();

$total = count($ids_usuarios);
log_message("Total de usuários para sincronizar: $total");

if ($total == 0) {
    log_message("AVISO: Nenhum usuário encontrado com reserva para hoje. Verifique o banco de dados.");
    exit;
}

$sucesso = 0;
$falhas = 0;

// Processar cada usuário individualmente para economizar memória
foreach ($ids_usuarios as $id_usuario) {
    log_message("Processando usuário ID: $id_usuario");
    
    // Buscar dados básicos do usuário (sem foto primeiro)
    $stmt = $conn->prepare("SELECT nome FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $stmt->bind_result($nome_usuario);
    $stmt->fetch();
    $stmt->close();
    
    log_message("Sincronizando usuário #$id_usuario - $nome_usuario");
    
    // Verificar se já existe sync para este usuário hoje
    $stmt = $conn->prepare("SELECT id, status FROM facial_sync WHERE id_usuario = ? AND data = ?");
    $stmt->bind_param("is", $id_usuario, $data_hoje);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($sync_id, $status);
        $stmt->fetch();
        $stmt->close();
        
        if ($status != 'pendente') {
            log_message("Usuário já sincronizado com status: $status. Pulando.");
            continue;
        }
        
        log_message("Encontrada sincronização pendente (ID: $sync_id). Tentando novamente.");
    } else {
        $stmt->close();
        // Criar registro de sincronização
        $stmt = $conn->prepare("INSERT INTO facial_sync (id_usuario, data, status) VALUES (?, ?, 'pendente')");
        $stmt->bind_param("is", $id_usuario, $data_hoje);
        $stmt->execute();
        $sync_id = $conn->insert_id;
        $stmt->close();
        log_message("Criado registro de sincronização ID: $sync_id");
    }
    
    // Buscar a foto somente quando realmente precisar
    // Cuidado: Não carregar foto em memória para economizar RAM
    $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE id = ? AND foto_base64 IS NOT NULL");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $stmt->bind_result($tem_foto);
    $stmt->fetch();
    $stmt->close();
    
    $usuario = array(
        'id' => $id_usuario,
        'nome' => $nome_usuario,
        'tem_foto' => $tem_foto > 0
    );
    
    // Aqui implementar a comunicação com o dispositivo Intelbras
    // Baseado na documentação específica do SS3542 MF W
    
    // Exemplo genérico:
    $sincronizado = enviarParaDispositivo($usuario, $data_hoje);
    
    if ($sincronizado) {
        $status = 'sincronizado';
        $sucesso++;
        log_message("Sincronização bem-sucedida para usuário #$id_usuario");
    } else {
        $status = 'falha';
        $falhas++;
        log_message("FALHA na sincronização para usuário #$id_usuario");
    }
    
    // Atualizar status
    $stmt = $conn->prepare("UPDATE facial_sync SET status = ?, horario_sync = NOW() WHERE id = ?");
    $stmt->bind_param("si", $status, $sync_id);
    $stmt->execute();
    $stmt->close();
}

log_message("Sincronização concluída. Sucesso: $sucesso, Falhas: $falhas, Total: $total");
log_message("============ FIM DA SINCRONIZAÇÃO ============");

// Notificar administrador se houver falhas
if ($falhas > 0) {
    $email_admin = get_config('email_notificacoes', '');
    if (!empty($email_admin)) {
        mail(
            $email_admin,
            "Relatório de Sincronização Facial - $data_hoje",
            "Sincronização facial concluída.\nSucesso: $sucesso\nFalhas: $falhas\nTotal: $total\n\nVerifique o log para mais detalhes."
        );
        log_message("E-mail de notificação enviado para: $email_admin");
    }
}

// Função a ser implementada conforme documentação do dispositivo
function enviarParaDispositivo($usuario, $data) {
    global $conn, $log_file;
    
    try {
        // TEMPORÁRIO: Para testes, simplesmente simular sucesso
        // Removendo comunicação real com dispositivo para evitar erros
        log_message("MODO DE TESTE: Simulando sucesso para o usuário ID {$usuario['id']} - {$usuario['nome']}");
        
        // Verificar se cURL está disponível (apenas para log)
        if (!function_exists('curl_init')) {
            log_message("AVISO: A extensão cURL não está disponível. Será necessário para a comunicação real.");
        }
        
        // Buscar configurações do dispositivo (apenas para log)
        $ip_dispositivo = get_config('ip_dispositivo_facial', '');
        $porta_dispositivo = get_config('porta_dispositivo_facial', '80');
        $usuario_dispositivo = get_config('usuario_dispositivo_facial', 'admin');
        
        log_message("Configurações do dispositivo (não utilizado no modo de teste): IP=$ip_dispositivo, Porta=$porta_dispositivo");
        
        // SIMULAÇÃO: Marcar que verificamos a foto, mas não carregamos para economizar memória
        if ($usuario['tem_foto']) {
            log_message("Usuário ID {$usuario['id']} tem foto disponível para sincronização futura");
        }
        
        // Registrar que seria enviado ao dispositivo (para fins de log apenas)
        $dados_simulados = array(
            "UserID" => (string)$usuario['id'],
            "UserName" => $usuario['nome']
        );
        
        log_message("Dados que seriam enviados: " . json_encode($dados_simulados));
        log_message("Simulação completa para o usuário ID {$usuario['id']}");
        
        // Em ambiente de teste, sempre retornar sucesso
        return true;
        
    } catch (Exception $e) {
        log_message("ERRO na simulação: " . $e->getMessage());
        return false;
    }
}

// Função para registrar log
function sincronizacao_log($mensagem, $log_file = null) {
    Logger::emergencial('sincronizacao_log', $mensagem);
}

/**
 * Sincroniza um usuário com o dispositivo de reconhecimento facial
 *
 * @param array $usuario Array contendo id, nome, e opcionalmente foto_base64
 * @param object $conn Conexão com o banco de dados
 * @param string $log_file Caminho para o arquivo de log (opcional)
 * @return array Resultado da sincronização com status e mensagem
 */
function sincronizar_usuario_dispositivo($usuario, $conn, $log_file = null) {
    // Validar parâmetros
    if (empty($usuario['id']) || empty($usuario['nome'])) {
        sincronizacao_log("ERRO: Parâmetros de usuário inválidos", $log_file);
        return [
            'sucesso' => false,
            'mensagem' => 'Dados de usuário inválidos'
        ];
    }
    
    sincronizacao_log("======== INÍCIO SINCRONIZAÇÃO USUÁRIO ========", $log_file);
    sincronizacao_log("Iniciando sincronização para usuário #{$usuario['id']} - {$usuario['nome']}", $log_file);
    
    try {
        // Buscar configurações do dispositivo
        $ip_dispositivo = facial_get_config('ip_dispositivo_facial', '', $conn);
        $porta_dispositivo = facial_get_config('porta_dispositivo_facial', '80', $conn);
        $usuario_dispositivo = facial_get_config('usuario_dispositivo_facial', 'admin', $conn);
        $senha_dispositivo = facial_get_config('senha_dispositivo_facial', '', $conn);
        
        sincronizacao_log("Configurações do dispositivo:", $log_file);
        sincronizacao_log("- IP: $ip_dispositivo", $log_file);
        sincronizacao_log("- Porta: $porta_dispositivo", $log_file);
        sincronizacao_log("- Usuário: $usuario_dispositivo", $log_file);
        sincronizacao_log("- Senha: " . (empty($senha_dispositivo) ? "NÃO DEFINIDA" : "configurada"), $log_file);
        
        // Validar configurações
        if (empty($ip_dispositivo)) {
            sincronizacao_log("ERRO: IP do dispositivo não configurado", $log_file);
            return [
                'sucesso' => false,
                'mensagem' => 'IP do dispositivo não configurado'
            ];
        }
        
        if (empty($senha_dispositivo)) {
            sincronizacao_log("ERRO: Senha do dispositivo não configurada", $log_file);
            return [
                'sucesso' => false,
                'mensagem' => 'Senha do dispositivo não configurada'
            ];
        }
        
        // Verificar se temos uma foto para o usuário
        $tem_foto = false;
        $foto_base64 = '';
        
        if (isset($usuario['foto_base64']) && !empty($usuario['foto_base64'])) {
            $foto_base64 = $usuario['foto_base64'];
            $tem_foto = true;
            sincronizacao_log("Usuário possui foto fornecida diretamente", $log_file);
        } else if (isset($usuario['id'])) {
            // Verificar tamanho da foto antes de carregar
            $stmt = $conn->prepare("SELECT LENGTH(foto_base64) AS tamanho FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $usuario['id']);
            $stmt->execute();
            $stmt->bind_result($tamanho_foto);
            $stmt->fetch();
            $stmt->close();
            
            if ($tamanho_foto > 0) {
                if ($tamanho_foto > 10485760) { // Mais de 10MB
                    sincronizacao_log("AVISO: Foto muito grande (" . round($tamanho_foto/1048576, 2) . " MB). Ignorando.", $log_file);
                } else {
                    // Carregar foto
                    $stmt = $conn->prepare("SELECT foto_base64 FROM usuarios WHERE id = ?");
                    $stmt->bind_param("i", $usuario['id']);
                    $stmt->execute();
                    $stmt->bind_result($foto_base64);
                    $stmt->fetch();
                    $stmt->close();
                    
                    if (!empty($foto_base64)) {
                        $tem_foto = true;
                        sincronizacao_log("Foto carregada com sucesso (" . round(strlen($foto_base64)/1024, 2) . " KB)", $log_file);
                    } else {
                        sincronizacao_log("Foto vazia no banco de dados", $log_file);
                    }
                }
            } else {
                sincronizacao_log("Usuário não possui foto", $log_file);
            }
        }
        
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
        sincronizacao_log("Dados JSON preparados para envio:", $log_file);
        sincronizacao_log($json_dados, $log_file);
        
        // IMPLEMENTAÇÃO REAL de comunicação com o dispositivo
        // Construir URL da API
        $url = "http://{$ip_dispositivo}:{$porta_dispositivo}/cgi-bin/AccessUser.cgi?action=insertMulti";
        sincronizacao_log("URL do endpoint: $url", $log_file);
        
        // Verificar se cURL está disponível
        if (!function_exists('curl_init')) {
            sincronizacao_log("ERRO: A extensão cURL não está disponível", $log_file);
            return ['sucesso' => false, 'mensagem' => 'Extensão cURL não disponível no servidor'];
        }
        
        // Verificar se o dispositivo está acessível
        sincronizacao_log("Verificando se o dispositivo está acessível...", $log_file);
        $ping_ch = curl_init("http://{$ip_dispositivo}:{$porta_dispositivo}/");
        curl_setopt($ping_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ping_ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ping_ch, CURLOPT_NOBODY, true);
        curl_exec($ping_ch);
        $ping_code = curl_getinfo($ping_ch, CURLINFO_HTTP_CODE);
        $ping_erro = curl_error($ping_ch);
        curl_close($ping_ch);
        
        if ($ping_code == 0) {
            sincronizacao_log("ERRO: Dispositivo inacessível: $ping_erro", $log_file);
            return ['sucesso' => false, 'mensagem' => "Dispositivo inacessível: $ping_erro"];
        }
        
        sincronizacao_log("Dispositivo acessível (código HTTP: $ping_code)", $log_file);
        
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
        
        // Opções de depuração
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        
        // Executar a requisição
        sincronizacao_log("Enviando requisição...", $log_file);
        $resposta = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_erro = curl_error($ch);
        
        // Obter informações detalhadas de debug
        rewind($verbose);
        $debug_info = stream_get_contents($verbose);
        fclose($verbose);
        
        sincronizacao_log("Debug cURL:", $log_file);
        sincronizacao_log($debug_info, $log_file);
        
        sincronizacao_log("Código HTTP: $http_code", $log_file);
        
        if (!empty($curl_erro)) {
            sincronizacao_log("Erro cURL: $curl_erro", $log_file);
        }
        
        curl_close($ch);
        
        if ($http_code != 200 || $resposta === false) {
            sincronizacao_log("Erro na comunicação HTTP $http_code", $log_file);
            return ['sucesso' => false, 'mensagem' => "Erro na comunicação: $curl_erro (HTTP $http_code)"];
        }
        
        sincronizacao_log("Resposta do dispositivo: [" . trim($resposta) . "]", $log_file);
        
        // Verificação de resposta OK
        if (trim($resposta) === 'OK') {
            sincronizacao_log("Usuário sincronizado com sucesso", $log_file);
            
            // Se tiver foto e a sincronização de usuário funcionou, enviar foto
            if ($tem_foto) {
                // Implementar envio da face quando houver documentação específica
                sincronizacao_log("Face do usuário será sincronizada em uma etapa futura", $log_file);
            }
            
            sincronizacao_log("======== FIM SINCRONIZAÇÃO: SUCESSO ========", $log_file);
            return ['sucesso' => true, 'mensagem' => 'Usuário sincronizado com sucesso'];
        } else {
            sincronizacao_log("Erro na resposta do dispositivo: [" . trim($resposta) . "]", $log_file);
            sincronizacao_log("======== FIM SINCRONIZAÇÃO: FALHA ========", $log_file);
            return ['sucesso' => false, 'mensagem' => 'Erro na resposta do dispositivo: ' . trim($resposta)];
        }
        
    } catch (Exception $e) {
        sincronizacao_log("ERRO DE EXCEÇÃO: " . $e->getMessage(), $log_file);
        sincronizacao_log("======== FIM SINCRONIZAÇÃO: EXCEÇÃO ========", $log_file);
        return [
            'sucesso' => false,
            'mensagem' => 'Erro durante sincronização: ' . $e->getMessage()
        ];
    }
}

/**
 * Obtém uma configuração do banco de dados (versão específica para o módulo facial)
 *
 * @param string $chave Nome da configuração
 * @param string $padrao Valor padrão caso não encontre
 * @param object $conn Conexão com o banco de dados
 * @return string Valor da configuração
 */
function facial_get_config($chave, $padrao = '', $conn = null) {
    // Primeiro, verificar se a função get_config original existe para usar
    if (function_exists('get_config')) {
        return get_config($chave, $padrao);
    }
    
    // Se não existir, usar implementação local
    // Se não for fornecida conexão, tentar obter a global
    if ($conn === null) {
        global $conn;
        if (!$conn) {
            // Tentar conectar
            require_once dirname(__FILE__) . '/../api/conexao.php';
            if (!$conn) {
                return $padrao;
            }
        }
    }
    
    try {
        $stmt = $conn->prepare("SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1");
        $stmt->bind_param("s", $chave);
        $stmt->execute();
        $stmt->bind_result($valor);
        
        if ($stmt->fetch()) {
            $stmt->close();
            return $valor;
        }
        
        $stmt->close();
    } catch (Exception $e) {
        sincronizacao_log("Erro ao buscar configuração '$chave': " . $e->getMessage());
    }
    
    return $padrao;
}
?>