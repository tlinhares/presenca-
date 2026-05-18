<?php
/**
 * WhatsAppService - Serviço centralizado para envio de mensagens e arquivos via WhatsApp
 * 
 * Este serviço centraliza toda a lógica de envio de WhatsApp, garantindo
 * consistência e facilitando manutenção.
 * 
 * @version 1.0
 * @author Sistema de Presença
 */

class WhatsAppService {
    // Configurações da API (fallback se não houver APIs cadastradas — lê do .env)
    private static function apiUrlMessage(): string {
        require_once __DIR__ . '/../../utils/env.php';
        return env('WHATSAPP_API_URL_MESSAGE', 'http://10.144.128.34:21465/api/servidor/send-message');
    }
    private static function apiUrlFile(): string {
        require_once __DIR__ . '/../../utils/env.php';
        return env('WHATSAPP_API_URL_FILE', 'http://10.144.128.34:21465/api/servidor/send-file');
    }
    private static function apiToken(): string {
        require_once __DIR__ . '/../../utils/env.php';
        return env('WHATSAPP_API_TOKEN', '');
    }

    // Timeouts
    private const TIMEOUT_MESSAGE = 30;
    private const TIMEOUT_FILE = 60;
    private const CONNECT_TIMEOUT = 10;
    
    // Cache de conexão e configurações
    private static $conn = null;
    private static $cache_configs = [];
    
    /**
     * Limpa o cache de configurações
     */
    public static function limparCache() {
        self::$cache_configs = [];
    }
    
    /**
     * Obtém conexão com banco de dados
     * 
     * @return mysqli|null
     */
    private static function getConexao() {
        // Sempre tentar obter conexão fresca para evitar problemas de cache
        // Mas manter cache se já existe e está válida
        if (self::$conn !== null && self::$conn instanceof mysqli) {
            try {
                if (self::$conn->ping()) {
                    return self::$conn;
                }
            } catch (Exception $e) {
                // Conexão inválida, continuar para criar nova
            }
        }
        
        // Limpar cache se conexão inválida
        self::$conn = null;
        
        try {
            // Tentar usar conexão global se já existe
            global $conn, $db_conn;
            
            // Verificar $db_conn primeiro (pode ter sido passada explicitamente)
            if (isset($db_conn) && $db_conn instanceof mysqli && !$db_conn->connect_error) {
                try {
                    if ($db_conn->ping()) {
                        self::$conn = $db_conn;
                        error_log("WhatsAppService: Usando conexão db_conn global");
                        return $db_conn;
                    }
                } catch (Exception $e) {
                    // Continuar para tentar criar nova
                }
            }
            
            // Verificar $conn global
            if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
                try {
                    if ($conn->ping()) {
                        self::$conn = $conn;
                        error_log("WhatsAppService: Usando conexão global existente");
                        return $conn;
                    }
                } catch (Exception $e) {
                    // Continuar para tentar criar nova
                }
            }
            
            // Tentar incluir arquivo de conexão
            $paths = [
                __DIR__ . '/../../api/conexao.php',
                __DIR__ . '/../../config/config.php'
            ];
            
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    // Usar include em vez de require_once para garantir que execute
                    $conn_temp = null;
                    ob_start();
                    include $path;
                    ob_end_clean();
                    
                    // Verificar se $conn foi definida após o include
                    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
                        try {
                            if ($conn->ping()) {
                                self::$conn = $conn;
                                error_log("WhatsAppService: Conexão obtida com sucesso de: " . $path);
                                return $conn;
                            }
                        } catch (Exception $e) {
                            error_log("WhatsAppService: Erro ao fazer ping na conexão: " . $e->getMessage());
                        }
                    }
                }
            }
            
            // Se não conseguiu usar conexão existente, criar nova diretamente
            require_once __DIR__ . '/../../utils/env.php';
            $host    = env('DB_HOST', 'localhost');
            $usuario = env('DB_USER', 'root');
            $senha   = env('DB_PASS', '');
            $banco   = env('DB_NAME', 'presenca_aom');

            $conn_nova = new mysqli($host, $usuario, $senha, $banco);
            if (!$conn_nova->connect_error) {
                $conn_nova->set_charset("utf8");
                self::$conn = $conn_nova;
                error_log("WhatsAppService: Nova conexão criada diretamente");
                return $conn_nova;
            } else {
                error_log("WhatsAppService: Erro ao criar nova conexão: " . $conn_nova->connect_error);
            }
            
        } catch (Exception $e) {
            error_log("WhatsAppService: Erro ao obter conexão: " . $e->getMessage());
            error_log("WhatsAppService: Stack trace: " . $e->getTraceAsString());
        }
        
        return null;
    }
    
    /**
     * Busca configuração para um tipo de notificação
     * 
     * @param string $tipo_notificacao Tipo de notificação
     * @return array|null Configuração ou null se não encontrada
     */
    private static function buscarConfiguracao($tipo_notificacao, $forcar_atualizacao = false) {
        // Verificar cache (a menos que seja forçada atualização)
        if (!$forcar_atualizacao && isset(self::$cache_configs[$tipo_notificacao])) {
            error_log("WhatsAppService: Retornando configuração do cache para '$tipo_notificacao'");
            return self::$cache_configs[$tipo_notificacao];
        }
        
        $conn = self::getConexao();
        if (!$conn) {
            error_log("WhatsAppService: Erro - não foi possível obter conexão com banco");
            return null;
        }
        
        try {
            // Verificar se tabela existe
            $result = $conn->query("SHOW TABLES LIKE 'whatsapp_config_notificacoes'");
            if (!$result || $result->num_rows === 0) {
                error_log("WhatsAppService: Tabela whatsapp_config_notificacoes não existe");
                return null; // Tabela não existe, usar comportamento padrão
            }
            
            error_log("WhatsAppService: Buscando configuração para '$tipo_notificacao'");
            $stmt = $conn->prepare("SELECT * FROM whatsapp_config_notificacoes WHERE tipo_notificacao = ? LIMIT 1");
            if (!$stmt) {
                error_log("WhatsAppService: Erro ao preparar query de configuração: " . $conn->error);
                return null;
            }
            
            $stmt->bind_param("s", $tipo_notificacao);
            $stmt->execute();
            $result = $stmt->get_result();
            $config = $result->fetch_assoc();
            $stmt->close();
            
            if ($config) {
                error_log("WhatsAppService: Configuração encontrada para '$tipo_notificacao' - modo: {$config['modo_selecao']}, ids_apis_sorteio (raw): " . ($config['ids_apis_sorteio'] ?? 'null'));
                
                // Decodificar JSON se existir
                if (!empty($config['ids_apis_sorteio'])) {
                    $decoded = json_decode($config['ids_apis_sorteio'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log("WhatsAppService: Erro ao decodificar JSON de ids_apis_sorteio: " . json_last_error_msg());
                        $config['ids_apis_sorteio'] = [];
                    } else {
                        $config['ids_apis_sorteio'] = is_array($decoded) ? $decoded : [];
                        error_log("WhatsAppService: ids_apis_sorteio decodificado: " . json_encode($config['ids_apis_sorteio']));
                    }
                } else {
                    $config['ids_apis_sorteio'] = [];
                }
                
                // Garantir tipos corretos
                $config['desabilitar_whatsapp'] = isset($config['desabilitar_whatsapp']) ? intval($config['desabilitar_whatsapp']) : 0;
                $config['id_api_especifica'] = isset($config['id_api_especifica']) && $config['id_api_especifica'] > 0 ? intval($config['id_api_especifica']) : null;
                
                self::$cache_configs[$tipo_notificacao] = $config;
            } else {
                error_log("WhatsAppService: Nenhuma configuração encontrada no banco para '$tipo_notificacao'");
            }
            
            return $config;
        } catch (Exception $e) {
            error_log("WhatsAppService: Erro ao buscar configuração: " . $e->getMessage());
            error_log("WhatsAppService: Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }
    
    /**
     * Obtém lista de APIs para tentar envio
     * 
     * @param string|null $tipo_notificacao Tipo de notificação
     * @param int $tentativas_maximas Número máximo de tentativas
     * @return array Array de arrays com dados das APIs
     */
    private static function obterApisParaTentativas($tipo_notificacao = null, $tentativas_maximas = 3) {
        $conn = self::getConexao();
        if (!$conn) {
            return []; // Sem conexão, usar API padrão
        }
        
        try {
            // Verificar se tabela existe
            $result = $conn->query("SHOW TABLES LIKE 'whatsapp_apis'");
            if (!$result || $result->num_rows === 0) {
                return []; // Tabela não existe, usar API padrão
            }
            
            // Se não há tipo de notificação, buscar todas APIs ativas
            if (!$tipo_notificacao) {
                $sql = "SELECT * FROM whatsapp_apis WHERE ativo = 1 ORDER BY prioridade ASC, nome ASC LIMIT ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $tentativas_maximas);
                $stmt->execute();
                $result = $stmt->get_result();
                $apis = [];
                while ($row = $result->fetch_assoc()) {
                    $apis[] = $row;
                }
                $stmt->close();
                return $apis;
            }
            
            // Buscar configuração (forçar atualização para evitar cache desatualizado)
            $config = self::buscarConfiguracao($tipo_notificacao, true);
            
            // Se não há configuração, usar comportamento padrão (todas APIs ativas)
            if (!$config) {
                error_log("WhatsAppService: Nenhuma configuração encontrada para '$tipo_notificacao', usando comportamento padrão");
                $sql = "SELECT * FROM whatsapp_apis WHERE ativo = 1 ORDER BY prioridade ASC, nome ASC LIMIT ?";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    error_log("WhatsAppService: Erro ao preparar query padrão: " . $conn->error);
                    return [];
                }
                $stmt->bind_param("i", $tentativas_maximas);
                $stmt->execute();
                $result = $stmt->get_result();
                $apis = [];
                while ($row = $result->fetch_assoc()) {
                    $apis[] = $row;
                }
                $stmt->close();
                return $apis;
            }
            
            error_log("WhatsAppService: Config encontrada para '$tipo_notificacao' - modo: {$config['modo_selecao']}, ids_apis_sorteio: " . json_encode($config['ids_apis_sorteio'] ?? 'não definido'));
            
            // Se desabilitado, retornar vazio
            if ($config['desabilitar_whatsapp'] || $config['modo_selecao'] === 'desabilitado') {
                return [];
            }
            
            $apis = [];
            
            // Modo específica
            if ($config['modo_selecao'] === 'especifica' && $config['id_api_especifica']) {
                $sql = "SELECT * FROM whatsapp_apis WHERE id = ? AND ativo = 1 LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $config['id_api_especifica']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $apis[] = $row;
                }
                $stmt->close();
            }
            // Modo sorteio
            elseif ($config['modo_selecao'] === 'sorteio') {
                // Verificar se ids_apis_sorteio existe e é um array válido
                if (isset($config['ids_apis_sorteio']) && is_array($config['ids_apis_sorteio']) && !empty($config['ids_apis_sorteio'])) {
                    $ids = array_map('intval', $config['ids_apis_sorteio']);
                    $ids = array_filter($ids); // Remove zeros e valores inválidos
                    
                    if (!empty($ids)) {
                        $ids_str = implode(',', $ids);
                        $sql = "SELECT * FROM whatsapp_apis WHERE id IN ($ids_str) AND ativo = 1 ORDER BY prioridade ASC, nome ASC";
                        $result = $conn->query($sql);
                        
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                $apis[] = $row;
                            }
                            
                            // Embaralhar para sorteio
                            if (!empty($apis)) {
                                shuffle($apis);
                                
                                // Limitar ao número de tentativas
                                $apis = array_slice($apis, 0, $tentativas_maximas);
                            } else {
                                error_log("WhatsAppService: Nenhuma API encontrada para IDs: " . $ids_str . " (modo sorteio)");
                            }
                        } else {
                            error_log("WhatsAppService: Erro ao buscar APIs no modo sorteio. SQL: " . $sql . " | Erro: " . $conn->error);
                        }
                    } else {
                        error_log("WhatsAppService: IDs de APIs inválidos após filtro. IDs originais: " . json_encode($config['ids_apis_sorteio']));
                    }
                } else {
                    error_log("WhatsAppService: Modo sorteio mas ids_apis_sorteio inválido ou vazio. Valor: " . json_encode($config['ids_apis_sorteio'] ?? 'não definido'));
                }
            }
            
            return $apis;
        } catch (Exception $e) {
            error_log("WhatsAppService: Erro ao obter APIs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Atualiza contadores de uma API após envio
     * 
     * @param int $api_id ID da API
     * @param bool $sucesso Se o envio foi bem-sucedido
     */
    private static function atualizarContadoresAPI($api_id, $sucesso) {
        $conn = self::getConexao();
        if (!$conn) {
            return;
        }
        
        try {
            if ($sucesso) {
                $sql = "UPDATE whatsapp_apis SET 
                        total_envios = total_envios + 1,
                        ultima_utilizacao = NOW()
                        WHERE id = ?";
            } else {
                $sql = "UPDATE whatsapp_apis SET 
                        total_falhas = total_falhas + 1,
                        ultima_utilizacao = NOW()
                        WHERE id = ?";
            }
            
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $api_id);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("WhatsAppService: Erro ao atualizar contadores: " . $e->getMessage());
        }
    }
    
    /**
     * Envia mensagem usando uma API específica
     * 
     * @param string $telefone_com_codigo Telefone formatado
     * @param string $mensagem Mensagem
     * @param array $api Dados da API
     * @param callable|null $log_callback Callback de log
     * @return array Resultado do envio
     */
    private static function enviarMensagemComAPI($telefone_com_codigo, $mensagem, $api, $log_callback = null) {
        $dados = [
            'phone' => $telefone_com_codigo,
            'isGroup' => false,
            'isNewsletter' => false,
            'isLid' => false,
            'message' => $mensagem
        ];
        
        $url = $api['url_mensagem'];
        $token = trim($api['token'] ?? '');
        
        // Garantir que o token tenha o prefixo "Bearer " se não tiver
        if (!empty($token) && stripos($token, 'Bearer ') !== 0) {
            $token = 'Bearer ' . $token;
        }
        
        if ($log_callback) {
            $log_callback("Tentando enviar via API: {$api['nome']} ($url)");
            $log_callback("Token (primeiros 30 chars): " . substr($token, 0, 30) . "...");
        }
        
        if (empty($token) || $token === 'Bearer ') {
            $erro = 'Token não configurado para esta API';
            if ($log_callback) {
                $log_callback("ERRO: $erro");
            }
            return ['sucesso' => false, 'mensagem' => $erro];
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: ' . $token
            ],
            CURLOPT_TIMEOUT => self::TIMEOUT_MESSAGE,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $resposta = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        $resultado = self::processarResposta($resposta, $http_code, $curl_error, 'mensagem', $log_callback);
        $resultado['api_id'] = $api['id'];
        $resultado['api_nome'] = $api['nome'];
        
        return $resultado;
    }
    
    /**
     * Envia arquivo usando uma API específica
     * 
     * @param string $telefone_com_codigo Telefone formatado
     * @param string $caminho_arquivo Caminho do arquivo
     * @param string|null $caption Legenda
     * @param array $api Dados da API
     * @param callable|null $log_callback Callback de log
     * @return array Resultado do envio
     */
    private static function enviarArquivoComAPI($telefone_com_codigo, $caminho_arquivo, $caption, $api, $log_callback = null) {
        $conteudo_arquivo = file_get_contents($caminho_arquivo);
        if ($conteudo_arquivo === false) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao ler arquivo'];
        }
        
        $base64_arquivo = base64_encode($conteudo_arquivo);
        $nome_arquivo = basename($caminho_arquivo);
        $extensao = pathinfo($caminho_arquivo, PATHINFO_EXTENSION);
        
        $mime_type = 'application/octet-stream';
        if ($extensao === 'pdf') {
            $mime_type = 'application/pdf';
        } elseif ($extensao === 'csv') {
            $mime_type = 'text/csv';
        } elseif ($extensao === 'jpg' || $extensao === 'jpeg') {
            $mime_type = 'image/jpeg';
        } elseif ($extensao === 'png') {
            $mime_type = 'image/png';
        }
        
        $data_url = "data:{$mime_type};base64,{$base64_arquivo}";
        
        if ($caption === null) {
            if ($extensao === 'pdf') {
                $caption = '📊 Relatório PDF - ' . date('d/m/Y');
            } elseif ($extensao === 'csv') {
                $caption = '📈 Relatório CSV - ' . date('d/m/Y');
            } else {
                $caption = '📄 Arquivo em anexo';
            }
        }
        
        $dados_arquivo = [
            'phone' => $telefone_com_codigo,
            'isGroup' => false,
            'isNewsletter' => false,
            'isLid' => false,
            'filename' => $nome_arquivo,
            'caption' => $caption,
            'base64' => $data_url
        ];
        
        $url = $api['url_arquivo'];
        $token = trim($api['token'] ?? '');
        
        // Garantir que o token tenha o prefixo "Bearer " se não tiver
        if (!empty($token) && stripos($token, 'Bearer ') !== 0) {
            $token = 'Bearer ' . $token;
        }
        
        if ($log_callback) {
            $log_callback("Tentando enviar arquivo via API: {$api['nome']} ($url)");
            $log_callback("Token (primeiros 30 chars): " . substr($token, 0, 30) . "...");
        }
        
        if (empty($token) || $token === 'Bearer ') {
            $erro = 'Token não configurado para esta API';
            if ($log_callback) {
                $log_callback("ERRO: $erro");
            }
            return ['sucesso' => false, 'mensagem' => $erro];
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($dados_arquivo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
                'Authorization: ' . $token
            ],
            CURLOPT_TIMEOUT => self::TIMEOUT_FILE,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $resposta = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        $resultado = self::processarResposta($resposta, $http_code, $curl_error, 'arquivo', $log_callback);
        $resultado['api_id'] = $api['id'];
        $resultado['api_nome'] = $api['nome'];
        
        return $resultado;
    }
    
    /**
     * Normaliza número de telefone brasileiro
     * - Remove caracteres não numéricos
     * - Adiciona código do país (55) se necessário
     * - Remove nono dígito se presente (WhatsApp não aceita)
     * - Retorna número no formato: 55XXXXXXXXXXX (12 dígitos)
     * 
     * @param string|int $telefone Número de telefone
     * @return string Número normalizado (sem o +)
     */
    public static function normalizarTelefone($telefone) {
        if (empty($telefone) || $telefone === null) {
            return '';
        }
        
        $telefone = (string)$telefone;
        $telefone = trim($telefone);
        
        if ($telefone === '' || $telefone === '0' || $telefone === 'null' || strtolower($telefone) === 'null') {
            return '';
        }
        
        // Remover todos os caracteres não numéricos
        $telefone_normalizado = preg_replace('/[^0-9]/', '', $telefone);
        
        if (strlen($telefone_normalizado) < 10) {
            return '';
        }
        
        // Se o número não começa com código do país do Brasil (55), adicionar
        if (!str_starts_with($telefone_normalizado, '55')) {
            $telefone_normalizado = '55' . $telefone_normalizado;
        }
        
        // Validar tamanho final (código do país + DDD + número)
        // Formato esperado: 55 + DDD (2 dígitos) + número (8 ou 9 dígitos) = 12 ou 13 dígitos
        if (strlen($telefone_normalizado) < 12 || strlen($telefone_normalizado) > 13) {
            return '';
        }
        
        // WhatsApp não aceita o nono dígito - se tiver 13 dígitos, remover o nono dígito
        // Formato: 55 (país) + DDD (2 dígitos) + número (8 ou 9 dígitos)
        // Se tiver 13 dígitos (55 + DDD + 9 dígitos), remover o primeiro dígito dos 9
        if (strlen($telefone_normalizado) === 13) {
            // Remover o nono dígito (posição 4, após 55 + DDD)
            $telefone_normalizado = substr($telefone_normalizado, 0, 4) . substr($telefone_normalizado, 5);
        }
        
        return $telefone_normalizado;
    }
    
    /**
     * Formata número para envio (adiciona + no início)
     * 
     * @param string $telefone_normalizado Número já normalizado
     * @return string Número formatado com +
     */
    private static function formatarNumeroParaEnvio($telefone_normalizado) {
        // Garantir que sempre tenha o + no início
        $telefone_com_codigo = '+' . ltrim($telefone_normalizado, '+');
        
        // Validação final
        if ($telefone_com_codigo[0] !== '+') {
            $telefone_com_codigo = '+' . $telefone_com_codigo;
        }
        
        return $telefone_com_codigo;
    }
    
    /**
     * Envia mensagem de texto via WhatsApp
     * 
     * @param string|int $telefone Número de telefone (será normalizado)
     * @param string $mensagem Mensagem a ser enviada
     * @param array $opcoes Opções adicionais:
     *   - 'log_callback': função para logging customizado
     *   - 'retornar_detalhes': se true, retorna array com detalhes da resposta
     *   - 'tipo_notificacao': tipo de notificação para seleção de API
     * @return array|bool 
     *   - Se retornar_detalhes=false: ['sucesso' => bool, 'mensagem' => string]
     *   - Se retornar_detalhes=true: array completo com detalhes
     */
    public static function enviarMensagem($telefone, $mensagem, $opcoes = []) {
        $log_callback = $opcoes['log_callback'] ?? null;
        $retornar_detalhes = $opcoes['retornar_detalhes'] ?? false;
        $tipo_notificacao = $opcoes['tipo_notificacao'] ?? null;
        
        // Normalizar telefone
        $telefone_normalizado = self::normalizarTelefone($telefone);
        
        if (empty($telefone_normalizado)) {
            $erro = 'Número de telefone inválido';
            if ($log_callback) $log_callback("ERRO: $erro");
            return ['sucesso' => false, 'mensagem' => $erro];
        }
        
        // Formatar para envio
        $telefone_com_codigo = self::formatarNumeroParaEnvio($telefone_normalizado);
        
        if ($log_callback) {
            $log_callback("Enviando WhatsApp para: $telefone_com_codigo");
        }
        
        // Buscar APIs para tentar (forçar atualização para evitar cache desatualizado)
        $config = $tipo_notificacao ? self::buscarConfiguracao($tipo_notificacao, true) : null;
        $tentativas_maximas = $config ? intval($config['tentativas_maximas']) : 3;
        
        if ($log_callback && $tipo_notificacao) {
            if ($config) {
                $log_callback("Config encontrada - modo: {$config['modo_selecao']}, tentativas_maximas: $tentativas_maximas");
            } else {
                $log_callback("Config NÃO encontrada para '$tipo_notificacao'");
            }
        }
        
        // IMPORTANTE: Só verificar desabilitar_whatsapp se HOUVER configuração específica
        // Se não há configuração, permitir WhatsApp por padrão (comportamento padrão)
        if ($config) {
            // Verificar se está desabilitado explicitamente
            $desabilitar = isset($config['desabilitar_whatsapp']) ? intval($config['desabilitar_whatsapp']) : 0;
            $modo_desabilitado = isset($config['modo_selecao']) && $config['modo_selecao'] === 'desabilitado';
            
            if ($desabilitar || $modo_desabilitado) {
                $erro = 'WhatsApp desabilitado para este tipo de notificação. Use email.';
                if ($log_callback) $log_callback("AVISO: $erro");
                return ['sucesso' => false, 'mensagem' => $erro, 'fallback_email' => true];
            }
        }
        
        $apis = self::obterApisParaTentativas($tipo_notificacao, $tentativas_maximas);
        
        // Debug: log das APIs encontradas
        if ($log_callback && $tipo_notificacao) {
            $log_callback("APIs encontradas para '$tipo_notificacao': " . count($apis));
            if (empty($apis)) {
                // Buscar configuração novamente para debug (forçar atualização)
                $config = self::buscarConfiguracao($tipo_notificacao, true);
                if ($config) {
                    $log_callback("Config encontrada - modo: {$config['modo_selecao']}, ids_apis_sorteio: " . json_encode($config['ids_apis_sorteio'] ?? 'não definido'));
                    $log_callback("desabilitar_whatsapp: " . ($config['desabilitar_whatsapp'] ?? 'não definido'));
                    
                    // Verificar se há APIs ativas no banco
                    $conn = self::getConexao();
                    if ($conn) {
                        $result_check = $conn->query("SELECT COUNT(*) as total FROM whatsapp_apis WHERE ativo = 1");
                        if ($result_check) {
                            $row_check = $result_check->fetch_assoc();
                            $log_callback("Total de APIs ativas no banco: " . $row_check['total']);
                            
                            // Verificar se as APIs específicas existem
                            if (isset($config['ids_apis_sorteio']) && is_array($config['ids_apis_sorteio']) && !empty($config['ids_apis_sorteio'])) {
                                $ids_str = implode(',', array_map('intval', $config['ids_apis_sorteio']));
                                $result_apis = $conn->query("SELECT id, nome, ativo FROM whatsapp_apis WHERE id IN ($ids_str)");
                                if ($result_apis) {
                                    $apis_encontradas = [];
                                    while ($row = $result_apis->fetch_assoc()) {
                                        $apis_encontradas[] = "ID {$row['id']} ({$row['nome']}) - " . ($row['ativo'] ? 'Ativa' : 'Inativa');
                                    }
                                    $log_callback("APIs específicas encontradas: " . (empty($apis_encontradas) ? 'Nenhuma' : implode(', ', $apis_encontradas)));
                                }
                            }
                        }
                    }
                } else {
                    $log_callback("Nenhuma configuração encontrada para '$tipo_notificacao'");
                }
            } else {
                $log_callback("APIs encontradas: " . json_encode(array_map(function($api) {
                    return ['id' => $api['id'], 'nome' => $api['nome']];
                }, $apis)));
            }
        }
        
        // Se tipo_notificacao foi fornecido mas não há APIs cadastradas, retornar erro
        if (empty($apis) && $tipo_notificacao) {
            $erro = 'Nenhuma API de WhatsApp cadastrada. Cadastre pelo menos uma API em: Gerenciamento → APIs WhatsApp';
            if ($log_callback) {
                $log_callback("ERRO: $erro");
            }
            return ['sucesso' => false, 'mensagem' => $erro, 'fallback_email' => true];
        }
        
        // Se não há APIs cadastradas e não há tipo_notificacao, usar API padrão (compatibilidade com código antigo)
        if (empty($apis)) {
            if ($log_callback) {
                $log_callback("Nenhuma API cadastrada, usando API padrão (compatibilidade)");
            }
            
            $dados = [
                'phone' => $telefone_com_codigo,
                'isGroup' => false,
                'isNewsletter' => false,
                'isLid' => false,
                'message' => $mensagem
            ];
            
            $ch = curl_init(self::apiUrlMessage());
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: ' . self::apiToken()
                ],
                CURLOPT_TIMEOUT => self::TIMEOUT_MESSAGE,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
            
            $resposta = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            $resultado = self::processarResposta($resposta, $http_code, $curl_error, 'mensagem', $log_callback);
        } else {
            // Tentar cada API até sucesso ou esgotar tentativas
            $resultado = null;
            $tentativas = 0;
            $erros = [];
            
            foreach ($apis as $api) {
                $tentativas++;
                if ($log_callback) {
                    $log_callback("Tentativa $tentativas/$tentativas_maximas com API: {$api['nome']}");
                }
                
                $resultado = self::enviarMensagemComAPI($telefone_com_codigo, $mensagem, $api, $log_callback);
                
                // Atualizar contadores
                self::atualizarContadoresAPI($api['id'], $resultado['sucesso']);
                
                if ($resultado['sucesso']) {
                    if ($log_callback) {
                        $log_callback("✅ Mensagem enviada com sucesso via API: {$api['nome']}");
                    }
                    break; // Sucesso, parar tentativas
                } else {
                    $erros[] = "{$api['nome']}: {$resultado['mensagem']}";
                    if ($log_callback) {
                        $log_callback("✗ Falha com API {$api['nome']}: {$resultado['mensagem']}");
                    }
                }
                
                // Se ainda há tentativas restantes, aguardar um pouco antes da próxima
                if ($tentativas < count($apis) && $tentativas < $tentativas_maximas) {
                    sleep(1); // Pequeno delay entre tentativas
                }
            }
            
            // Se todas falharam, preparar mensagem de erro
            if (!$resultado || !$resultado['sucesso']) {
                $mensagem_erro = 'Falha ao enviar após ' . $tentativas . ' tentativa(s). ';
                if (!empty($erros)) {
                    $mensagem_erro .= 'Erros: ' . implode('; ', $erros);
                }
                $resultado = [
                    'sucesso' => false,
                    'mensagem' => $mensagem_erro,
                    'fallback_email' => true,
                    'tentativas' => $tentativas
                ];
            }
        }
        
        if ($retornar_detalhes && isset($http_code)) {
            $resultado['detalhes'] = [
                'http_code' => $http_code,
                'curl_error' => $curl_error ?? null,
                'resposta_bruta' => $resposta ?? null
            ];
        }
        
        // Gravar notificação no histórico
        try {
            require_once __DIR__ . '/NotificacaoService.php';
            $usuario_id = $opcoes['usuario_id'] ?? null;
            $nome_destinatario = $opcoes['nome_destinatario'] ?? null;
            $tipo_mensagem = $opcoes['tipo_mensagem'] ?? null;
            
            // Passar conexão para NotificacaoService se disponível
            global $conn, $db_conn;
            if (isset($conn) && $conn instanceof mysqli) {
                $GLOBALS['db_conn'] = $conn;
            } elseif (isset($db_conn) && $db_conn instanceof mysqli) {
                $GLOBALS['db_conn'] = $db_conn;
            }
            
            $gravado = NotificacaoService::gravarWhatsApp($telefone_com_codigo, $mensagem, $resultado, $usuario_id, $nome_destinatario, $tipo_mensagem);
            if ($gravado && $log_callback) {
                $log_callback("Histórico gravado com sucesso (ID: $gravado)");
            } elseif (!$gravado && $log_callback) {
                $log_callback("Aviso: Não foi possível gravar no histórico");
            }
        } catch (Exception $e) {
            // Não quebra o sistema se falhar ao gravar histórico
            error_log("WhatsAppService: Erro ao gravar notificação: " . $e->getMessage());
            if ($log_callback) {
                $log_callback("Erro ao gravar histórico: " . $e->getMessage());
            }
        }
        
        return $resultado;
    }
    
    /**
     * Envia arquivo via WhatsApp (base64)
     * 
     * @param string|int $telefone Número de telefone (será normalizado)
     * @param string $caminho_arquivo Caminho completo do arquivo
     * @param string|null $caption Legenda do arquivo (opcional)
     * @param array $opcoes Opções adicionais:
     *   - 'log_callback': função para logging customizado
     *   - 'tipo_notificacao': tipo de notificação para seleção de API
     * @return array ['sucesso' => bool, 'mensagem' => string]
     */
    public static function enviarArquivo($telefone, $caminho_arquivo, $caption = null, $opcoes = []) {
        $log_callback = $opcoes['log_callback'] ?? null;
        $tipo_notificacao = $opcoes['tipo_notificacao'] ?? null;
        
        // Verificar se arquivo existe
        if (!file_exists($caminho_arquivo)) {
            $erro = 'Arquivo não encontrado: ' . $caminho_arquivo;
            if ($log_callback) $log_callback("ERRO: $erro");
            return ['sucesso' => false, 'mensagem' => $erro];
        }
        
        // Normalizar telefone
        $telefone_normalizado = self::normalizarTelefone($telefone);
        
        if (empty($telefone_normalizado)) {
            $erro = 'Número de telefone inválido';
            if ($log_callback) $log_callback("ERRO: $erro");
            return ['sucesso' => false, 'mensagem' => $erro];
        }
        
        // Formatar para envio
        $telefone_com_codigo = self::formatarNumeroParaEnvio($telefone_normalizado);
        
        if ($log_callback) {
            $log_callback("Enviando arquivo para: $telefone_com_codigo");
            $log_callback("Arquivo: $caminho_arquivo (" . filesize($caminho_arquivo) . " bytes)");
        }
        
        // Buscar APIs para tentar
        $config = $tipo_notificacao ? self::buscarConfiguracao($tipo_notificacao) : null;
        $tentativas_maximas = $config ? intval($config['tentativas_maximas']) : 3;
        
        // Se desabilitado, retornar erro indicando fallback para email
        if ($config && ($config['desabilitar_whatsapp'] || $config['modo_selecao'] === 'desabilitado')) {
            $erro = 'WhatsApp desabilitado para este tipo de notificação. Use email.';
            if ($log_callback) $log_callback("AVISO: $erro");
            return ['sucesso' => false, 'mensagem' => $erro, 'fallback_email' => true];
        }
        
        $apis = self::obterApisParaTentativas($tipo_notificacao, $tentativas_maximas);
        
        // Preparar dados do arquivo (comum para todas APIs)
        $conteudo_arquivo = file_get_contents($caminho_arquivo);
        if ($conteudo_arquivo === false) {
            $erro = 'Erro ao ler arquivo';
            if ($log_callback) $log_callback("ERRO: $erro");
            return ['sucesso' => false, 'mensagem' => $erro];
        }
        
        $nome_arquivo = basename($caminho_arquivo);
        $extensao = pathinfo($caminho_arquivo, PATHINFO_EXTENSION);
        
        // Determinar MIME type
        $mime_type = 'application/octet-stream';
        if ($extensao === 'pdf') {
            $mime_type = 'application/pdf';
        } elseif ($extensao === 'csv') {
            $mime_type = 'text/csv';
        } elseif ($extensao === 'jpg' || $extensao === 'jpeg') {
            $mime_type = 'image/jpeg';
        } elseif ($extensao === 'png') {
            $mime_type = 'image/png';
        }
        
        // Caption padrão se não fornecido
        if ($caption === null) {
            if ($extensao === 'pdf') {
                $caption = '📊 Relatório PDF - ' . date('d/m/Y');
            } elseif ($extensao === 'csv') {
                $caption = '📈 Relatório CSV - ' . date('d/m/Y');
            } else {
                $caption = '📄 Arquivo em anexo';
            }
        }
        
        // Se tipo_notificacao foi fornecido mas não há APIs cadastradas, retornar erro
        if (empty($apis) && $tipo_notificacao) {
            $erro = 'Nenhuma API de WhatsApp cadastrada. Cadastre pelo menos uma API em: Gerenciamento → APIs WhatsApp';
            if ($log_callback) {
                $log_callback("ERRO: $erro");
            }
            return ['sucesso' => false, 'mensagem' => $erro, 'fallback_email' => true];
        }
        
        // Se não há APIs cadastradas e não há tipo_notificacao, usar API padrão (compatibilidade com código antigo)
        if (empty($apis)) {
            if ($log_callback) {
                $log_callback("Nenhuma API cadastrada, usando API padrão (compatibilidade)");
            }
            
            $base64_arquivo = base64_encode($conteudo_arquivo);
            $data_url = "data:{$mime_type};base64,{$base64_arquivo}";
            
            $dados_arquivo = [
                'phone' => $telefone_com_codigo,
                'isGroup' => false,
                'isNewsletter' => false,
                'isLid' => false,
                'filename' => $nome_arquivo,
                'caption' => $caption,
                'base64' => $data_url
            ];
            
            $ch = curl_init(self::apiUrlFile());
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($dados_arquivo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json; charset=utf-8',
                    'Accept: application/json',
                    'Authorization: ' . self::apiToken()
                ],
                CURLOPT_TIMEOUT => self::TIMEOUT_FILE,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
            
            $resposta = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            $resultado = self::processarResposta($resposta, $http_code, $curl_error, 'arquivo', $log_callback);
        } else {
            // Tentar cada API até sucesso ou esgotar tentativas
            $resultado = null;
            $tentativas = 0;
            $erros = [];
            
            foreach ($apis as $api) {
                $tentativas++;
                if ($log_callback) {
                    $log_callback("Tentativa $tentativas/$tentativas_maximas com API: {$api['nome']}");
                }
                
                $resultado = self::enviarArquivoComAPI($telefone_com_codigo, $caminho_arquivo, $caption, $api, $log_callback);
                
                // Atualizar contadores
                self::atualizarContadoresAPI($api['id'], $resultado['sucesso']);
                
                if ($resultado['sucesso']) {
                    if ($log_callback) {
                        $log_callback("✅ Arquivo enviado com sucesso via API: {$api['nome']}");
                    }
                    break; // Sucesso, parar tentativas
                } else {
                    $erros[] = "{$api['nome']}: {$resultado['mensagem']}";
                    if ($log_callback) {
                        $log_callback("✗ Falha com API {$api['nome']}: {$resultado['mensagem']}");
                    }
                }
                
                // Se ainda há tentativas restantes, aguardar um pouco antes da próxima
                if ($tentativas < count($apis) && $tentativas < $tentativas_maximas) {
                    sleep(1); // Pequeno delay entre tentativas
                }
            }
            
            // Se todas falharam, preparar mensagem de erro
            if (!$resultado || !$resultado['sucesso']) {
                $mensagem_erro = 'Falha ao enviar arquivo após ' . $tentativas . ' tentativa(s). ';
                if (!empty($erros)) {
                    $mensagem_erro .= 'Erros: ' . implode('; ', $erros);
                }
                $resultado = [
                    'sucesso' => false,
                    'mensagem' => $mensagem_erro,
                    'fallback_email' => true,
                    'tentativas' => $tentativas
                ];
            }
        }
        
        // Gravar notificação no histórico (arquivo)
        try {
            require_once __DIR__ . '/NotificacaoService.php';
            $usuario_id = $opcoes['usuario_id'] ?? null;
            $nome_destinatario = $opcoes['nome_destinatario'] ?? null;
            $tipo_mensagem = $opcoes['tipo_mensagem'] ?? null;
            $mensagem_arquivo = $caption ?? 'Arquivo enviado';
            
            // Passar conexão para NotificacaoService se disponível
            global $conn, $db_conn;
            if (isset($conn) && $conn instanceof mysqli) {
                $GLOBALS['db_conn'] = $conn;
            } elseif (isset($db_conn) && $db_conn instanceof mysqli) {
                $GLOBALS['db_conn'] = $db_conn;
            }
            
            $gravado = NotificacaoService::gravarWhatsApp($telefone_com_codigo, $mensagem_arquivo, $resultado, $usuario_id, $nome_destinatario, $tipo_mensagem);
            if ($gravado && $log_callback) {
                $log_callback("Histórico gravado com sucesso (ID: $gravado)");
            } elseif (!$gravado && $log_callback) {
                $log_callback("Aviso: Não foi possível gravar no histórico");
            }
        } catch (Exception $e) {
            error_log("WhatsAppService: Erro ao gravar notificação de arquivo: " . $e->getMessage());
            if ($log_callback) {
                $log_callback("Erro ao gravar histórico: " . $e->getMessage());
            }
        }
        
        return $resultado;
    }
    
    /**
     * Envia mensagem e arquivo (se fornecido)
     * 
     * @param string|int $telefone Número de telefone
     * @param string $mensagem Mensagem de texto
     * @param string|null $caminho_arquivo Caminho do arquivo (opcional)
     * @param array $opcoes Opções adicionais
     * @return array ['sucesso' => bool, 'mensagem' => string]
     */
    public static function enviarMensagemEArquivo($telefone, $mensagem, $caminho_arquivo = null, $opcoes = []) {
        $log_callback = $opcoes['log_callback'] ?? null;
        
        // Enviar mensagem primeiro (já grava automaticamente no histórico)
        $resultado_mensagem = self::enviarMensagem($telefone, $mensagem, [
            'log_callback' => $log_callback,
            'usuario_id' => $opcoes['usuario_id'] ?? null,
            'nome_destinatario' => $opcoes['nome_destinatario'] ?? null,
            'tipo_mensagem' => $opcoes['tipo_mensagem'] ?? null
        ]);
        
        $mensagem_enviada = $resultado_mensagem['sucesso'];
        $erro_mensagem = $resultado_mensagem['mensagem'] ?? null;
        
        // Se houver arquivo, enviar também
        if ($caminho_arquivo && file_exists($caminho_arquivo)) {
            if ($log_callback) {
                $log_callback("Aguardando 2 segundos antes de enviar arquivo...");
            }
            sleep(2); // Aguardar um pouco antes de enviar o arquivo
            
            $resultado_arquivo = self::enviarArquivo($telefone, $caminho_arquivo, null, [
                'log_callback' => $log_callback,
                'usuario_id' => $opcoes['usuario_id'] ?? null,
                'nome_destinatario' => $opcoes['nome_destinatario'] ?? null,
                'tipo_mensagem' => $opcoes['tipo_mensagem'] ?? null,
                'tipo_notificacao' => $opcoes['tipo_notificacao'] ?? null
            ]);
            
            $arquivo_enviado = $resultado_arquivo['sucesso'];
            
            // Combinar resultados
            if ($mensagem_enviada && $arquivo_enviado) {
                return ['sucesso' => true, 'mensagem' => 'Mensagem e arquivo enviados com sucesso'];
            } elseif ($mensagem_enviada && !$arquivo_enviado) {
                return ['sucesso' => false, 'mensagem' => 'Mensagem enviada, mas erro no arquivo: ' . $resultado_arquivo['mensagem']];
            } elseif (!$mensagem_enviada && $arquivo_enviado) {
                return ['sucesso' => true, 'mensagem' => 'Arquivo enviado. Erro na mensagem: ' . $erro_mensagem];
            } else {
                return ['sucesso' => false, 'mensagem' => 'Erro na mensagem: ' . $erro_mensagem . ' | Erro no arquivo: ' . $resultado_arquivo['mensagem']];
            }
        }
        
        // Retornar resultado da mensagem
        return $resultado_mensagem;
    }
    
    /**
     * Processa resposta da API
     * 
     * @param string|false $resposta Resposta da API
     * @param int $http_code Código HTTP
     * @param string $curl_error Erro do cURL
     * @param string $tipo Tipo de envio ('mensagem' ou 'arquivo')
     * @param callable|null $log_callback Função de log
     * @return array ['sucesso' => bool, 'mensagem' => string]
     */
    private static function processarResposta($resposta, $http_code, $curl_error, $tipo, $log_callback = null) {
        // Verificar se há resposta JSON mesmo com HTTP 400
        $resposta_json = null;
        if ($resposta !== false) {
            $resposta_json = json_decode($resposta, true);
        }
        
        // Verificar se o erro é sobre "número não existe" (mas pode ter sido enviado)
        $erro_numero_nao_existe = false;
        if ($resposta_json && isset($resposta_json['message'])) {
            $msg_erro = strtolower($resposta_json['message']);
            if (strpos($msg_erro, 'não existe') !== false || 
                strpos($msg_erro, 'nao existe') !== false ||
                strpos($msg_erro, 'não encontrado') !== false ||
                strpos($msg_erro, 'nao encontrado') !== false) {
                $erro_numero_nao_existe = true;
                if ($log_callback) {
                    $log_callback("AVISO: Erro 'número não existe' detectado, mas $tipo pode ter sido enviado");
                }
            }
        }
        
        // Verificar erros de comunicação
        if ($resposta === false || !empty($curl_error)) {
            $erro = "Erro na comunicação com API do WhatsApp ($tipo)";
            if ($curl_error) {
                $erro .= ": $curl_error";
            }
            if ($log_callback) $log_callback("ERRO: $erro");
            return ['sucesso' => false, 'mensagem' => $erro];
        }
        
        // Verificar diferentes formatos de resposta
        if (json_last_error() === JSON_ERROR_NONE && is_array($resposta_json)) {
            // Verificar status: "success" ou "ok"
            if (isset($resposta_json['status']) && ($resposta_json['status'] === 'success' || $resposta_json['status'] === 'ok')) {
                if ($log_callback) $log_callback("✅ $tipo enviado com sucesso");
                return ['sucesso' => true, 'mensagem' => ucfirst($tipo) . ' enviado com sucesso'];
            } elseif (isset($resposta_json['success']) && $resposta_json['success'] === true) {
                if ($log_callback) $log_callback("✅ $tipo enviado com sucesso (success: true)");
                return ['sucesso' => true, 'mensagem' => ucfirst($tipo) . ' enviado com sucesso'];
            } else {
                // Se é erro de "número não existe", considerar como sucesso mas com aviso
                if ($erro_numero_nao_existe) {
                    $aviso = 'AVISO: A API retornou erro "número não existe", mas o ' . $tipo . ' pode ter sido enviado. Verifique se realmente chegou.';
                    if ($log_callback) $log_callback($aviso);
                    return ['sucesso' => true, 'mensagem' => $aviso];
                }
                $erro_detalhado = $resposta_json['message'] ?? $resposta_json['error'] ?? 'Erro desconhecido';
                if ($log_callback) $log_callback("ERRO: Falha no envio do $tipo: $erro_detalhado");
                return ['sucesso' => false, 'mensagem' => 'Erro no envio do ' . $tipo . ': ' . $erro_detalhado];
            }
        } else {
            // Se não é JSON válido, mas HTTP code indica sucesso, considerar como enviado
            if ($http_code >= 200 && $http_code < 300 && empty($curl_error)) {
                if ($log_callback) $log_callback("✅ HTTP Code indica sucesso, $tipo pode ter sido enviado");
                return ['sucesso' => true, 'mensagem' => ucfirst($tipo) . ' pode ter sido enviado (resposta não-JSON)'];
            } else {
                if ($log_callback) $log_callback("ERRO: Resposta inválida ou erro HTTP");
                return ['sucesso' => false, 'mensagem' => 'Erro na resposta da API do WhatsApp (' . $tipo . ')'];
            }
        }
    }
    
    /**
     * Calcula delay aleatório entre envios para evitar spam
     * Retorna um número aleatório de segundos entre 5 e 15
     * 
     * @param int $minimo Segundos mínimos (padrão: 5)
     * @param int $maximo Segundos máximos (padrão: 15)
     * @return int Número de segundos para aguardar
     */
    public static function calcularDelayAleatorio($minimo = 5, $maximo = 15) {
        return rand($minimo, $maximo);
    }
    
    /**
     * Envia múltiplas mensagens com delay inteligente entre envios
     * Útil para evitar spam e deslogamento do WhatsApp
     * 
     * @param array $destinatarios Array de arrays com ['telefone' => string, 'mensagem' => string]
     * @param array $opcoes Opções adicionais:
     *   - 'log_callback': função para logging customizado
     *   - 'delay_minimo': segundos mínimos entre envios (padrão: 5)
     *   - 'delay_maximo': segundos máximos entre envios (padrão: 15)
     * @return array ['total' => int, 'sucessos' => int, 'falhas' => int, 'resultados' => array]
     */
    public static function enviarMensagensEmLote($destinatarios, $opcoes = []) {
        $log_callback = $opcoes['log_callback'] ?? null;
        $delay_minimo = $opcoes['delay_minimo'] ?? 5;
        $delay_maximo = $opcoes['delay_maximo'] ?? 15;
        
        $total = count($destinatarios);
        $sucessos = 0;
        $falhas = 0;
        $resultados = [];
        
        if ($log_callback) {
            $log_callback("Iniciando envio em lote para $total destinatários com delay entre $delay_minimo-$delay_maximo segundos");
        }
        
        foreach ($destinatarios as $index => $destinatario) {
            $telefone = $destinatario['telefone'] ?? '';
            $mensagem = $destinatario['mensagem'] ?? '';
            
            if (empty($telefone) || empty($mensagem)) {
                $falhas++;
                $resultados[] = [
                    'telefone' => $telefone,
                    'sucesso' => false,
                    'mensagem' => 'Telefone ou mensagem vazios'
                ];
                continue;
            }
            
            // Calcular delay aleatório (exceto para o primeiro envio)
            if ($index > 0) {
                $delay = self::calcularDelayAleatorio($delay_minimo, $delay_maximo);
                if ($log_callback) {
                    $log_callback("Aguardando $delay segundos antes do próximo envio... (" . ($index + 1) . "/$total)");
                }
                sleep($delay);
            }
            
            // Enviar mensagem
            $resultado = self::enviarMensagem($telefone, $mensagem, [
                'log_callback' => $log_callback,
                'tipo_notificacao' => $destinatario['tipo_notificacao'] ?? null,
                'usuario_id' => $destinatario['usuario_id'] ?? null,
                'nome_destinatario' => $destinatario['nome_destinatario'] ?? null,
                'tipo_mensagem' => $destinatario['tipo_mensagem'] ?? null
            ]);
            
            if ($resultado['sucesso']) {
                $sucessos++;
            } else {
                $falhas++;
            }
            
            $resultados[] = [
                'telefone' => $telefone,
                'sucesso' => $resultado['sucesso'],
                'mensagem' => $resultado['mensagem'] ?? ''
            ];
            
            if ($log_callback) {
                $status = $resultado['sucesso'] ? '✓' : '✗';
                $log_callback("$status Envio " . ($index + 1) . "/$total: " . ($resultado['sucesso'] ? 'Sucesso' : $resultado['mensagem']));
            }
        }
        
        return [
            'total' => $total,
            'sucessos' => $sucessos,
            'falhas' => $falhas,
            'resultados' => $resultados
        ];
    }
    
    /**
     * Aguarda um delay aleatório entre envios
     * Útil para ser chamado manualmente entre envios individuais
     * 
     * @param int $minimo Segundos mínimos (padrão: 5)
     * @param int $maximo Segundos máximos (padrão: 15)
     * @param callable|null $log_callback Função para logging
     */
    public static function aguardarDelayAleatorio($minimo = 5, $maximo = 15, $log_callback = null) {
        $delay = self::calcularDelayAleatorio($minimo, $maximo);
        if ($log_callback) {
            $log_callback("Aguardando $delay segundos para evitar spam...");
        }
        sleep($delay);
    }
    
    /**
     * Calcula delay variável com cauda longa (8-40s + pausas longas)
     * Mistura delays curtos e longos para parecer mais humano e evitar detecção como bot
     * 
     * @param int $minimo_curto Segundos mínimos para delay curto (padrão: 8)
     * @param int $maximo_curto Segundos máximos para delay curto (padrão: 40)
     * @param float $probabilidade_pausa_longa Probabilidade de pausa longa 0-1 (padrão: 0.15 = 15%)
     * @return int Número de segundos para aguardar
     */
    public static function calcularDelayVariado($minimo_curto = 8, $maximo_curto = 40, $probabilidade_pausa_longa = 0.15) {
        // 15% de chance de pausa longa (1-6 minutos)
        if (rand(1, 100) <= ($probabilidade_pausa_longa * 100)) {
            // Pausa longa: 60-360 segundos (1-6 minutos)
            return rand(60, 360);
        }
        // Delay normal: 8-40 segundos
        return rand($minimo_curto, $maximo_curto);
    }
    
    /**
     * Verifica se está dentro da janela de envio permitida
     * 
     * @param string $hora_inicio Hora de início no formato HH:MM (padrão: '07:00')
     * @param string $hora_fim Hora de fim no formato HH:MM (padrão: '08:30')
     * @return bool True se está na janela permitida
     */
    public static function estaNaJanelaEnvio($hora_inicio = '07:00', $hora_fim = '08:30') {
        $hora_atual = (int)date('H');
        $minuto_atual = (int)date('i');
        $hora_atual_minutos = ($hora_atual * 60) + $minuto_atual;
        
        list($h_inicio, $m_inicio) = explode(':', $hora_inicio);
        list($h_fim, $m_fim) = explode(':', $hora_fim);
        
        $inicio_minutos = ((int)$h_inicio * 60) + (int)$m_inicio;
        $fim_minutos = ((int)$h_fim * 60) + (int)$m_fim;
        
        return $hora_atual_minutos >= $inicio_minutos && $hora_atual_minutos <= $fim_minutos;
    }
    
    /**
     * Envia mensagens em batches com pausas longas entre batches
     * Útil para evitar detecção como bot enviando muitas mensagens seguidas
     * 
     * @param array $destinatarios Array de arrays com ['telefone' => string, 'mensagem' => string]
     * @param array $opcoes Opções adicionais:
     *   - 'log_callback': função para logging customizado
     *   - 'tamanho_batch': número de mensagens por batch (padrão: 20, aleatório entre 10-30)
     *   - 'pausa_entre_batches_min': minutos mínimos de pausa entre batches (padrão: 5)
     *   - 'pausa_entre_batches_max': minutos máximos de pausa entre batches (padrão: 15)
     * @return array ['total' => int, 'sucessos' => int, 'falhas' => int, 'resultados' => array]
     */
    public static function enviarMensagensEmBatches($destinatarios, $opcoes = []) {
        $log_callback = $opcoes['log_callback'] ?? null;
        $tamanho_batch = $opcoes['tamanho_batch'] ?? rand(10, 30); // Batch aleatório entre 10-30 mensagens
        $pausa_entre_batches_min = $opcoes['pausa_entre_batches_min'] ?? 5; // 5-15 minutos
        $pausa_entre_batches_max = $opcoes['pausa_entre_batches_max'] ?? 15;
        
        $total = count($destinatarios);
        $sucessos = 0;
        $falhas = 0;
        $resultados = [];
        
        // Dividir em batches
        $batches = array_chunk($destinatarios, $tamanho_batch);
        $total_batches = count($batches);
        
        if ($log_callback) {
            $log_callback("Iniciando envio em $total_batches batches de até $tamanho_batch mensagens cada");
        }
        
        foreach ($batches as $batch_index => $batch) {
            if ($log_callback) {
                $log_callback("=== BATCH " . ($batch_index + 1) . "/$total_batches ===");
            }
            
            // Enviar mensagens do batch com delay variado
            foreach ($batch as $index => $destinatario) {
                $telefone = $destinatario['telefone'] ?? '';
                $mensagem = $destinatario['mensagem'] ?? '';
                
                if (empty($telefone) || empty($mensagem)) {
                    $falhas++;
                    $resultados[] = [
                        'telefone' => $telefone,
                        'sucesso' => false,
                        'mensagem' => 'Telefone ou mensagem vazios'
                    ];
                    continue;
                }
                
                // Delay variado (exceto primeira mensagem do batch)
                if ($index > 0) {
                    $delay = self::calcularDelayVariado();
                    if ($log_callback) {
                        $log_callback("Aguardando $delay segundos antes do próximo envio... (" . ($index + 1) . "/" . count($batch) . " do batch)");
                    }
                    sleep($delay);
                }
                
                // Enviar mensagem
                $opcoes_envio = [
                    'log_callback' => $log_callback,
                    'usuario_id' => $destinatario['usuario_id'] ?? null,
                    'nome_destinatario' => $destinatario['nome'] ?? null,
                    'tipo_mensagem' => $destinatario['tipo_mensagem'] ?? null,
                    'tipo_notificacao' => $destinatario['tipo_notificacao'] ?? null
                ];
                $resultado = self::enviarMensagem($telefone, $mensagem, $opcoes_envio);
                
                if ($resultado['sucesso']) {
                    $sucessos++;
                } else {
                    $falhas++;
                }
                
                $resultados[] = [
                    'telefone' => $telefone,
                    'sucesso' => $resultado['sucesso'],
                    'mensagem' => $resultado['mensagem'] ?? ''
                ];
                
                if ($log_callback) {
                    $status = $resultado['sucesso'] ? '✓' : '✗';
                    $log_callback("$status Envio " . ($index + 1) . "/" . count($batch) . " do batch " . ($batch_index + 1) . ": " . ($resultado['sucesso'] ? 'Sucesso' : $resultado['mensagem']));
                }
            }
            
            // Pausa longa entre batches (exceto último batch)
            if ($batch_index < $total_batches - 1) {
                $pausa = rand($pausa_entre_batches_min * 60, $pausa_entre_batches_max * 60);
                if ($log_callback) {
                    $log_callback("⏸️ Pausa de " . round($pausa / 60, 1) . " minutos entre batches...");
                }
                sleep($pausa);
            }
        }
        
        return [
            'total' => $total,
            'sucessos' => $sucessos,
            'falhas' => $falhas,
            'resultados' => $resultados
        ];
    }
}

