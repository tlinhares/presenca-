<?php
/**
 * DispositivoFacialService - Serviço centralizado para interação com dispositivos faciais
 * 
 * Este serviço centraliza todas as operações de comunicação com dispositivos
 * de reconhecimento facial (Intelbras/Dahua compatíveis).
 * 
 * Funcionalidades:
 * - Inserir usuário no dispositivo
 * - Remover usuário do dispositivo
 * - Inserir/Atualizar foto facial
 * - Remover foto facial
 * - Testar conexão com dispositivo
 * - Listar usuários do dispositivo
 * 
 * @version 1.0
 * @author Sistema de Presença AOM
 */

class DispositivoFacialService {
    
    // Timeouts padrão
    private const TIMEOUT_CONEXAO = 5;
    private const TIMEOUT_OPERACAO = 15;
    private const TIMEOUT_FOTO = 30;
    
    // Cache de protocolo por IP (evita tentativas duplas HTTP/HTTPS)
    private static $protocolo_cache = [];
    
    /**
     * Constrói URL do dispositivo (HTTP ou HTTPS)
     * 
     * @param string $ip IP do dispositivo
     * @param int $porta Porta do dispositivo
     * @param string $endpoint Endpoint da API
     * @return string URL completa
     */
    public static function construirUrl($ip, $porta, $endpoint) {
        // Verificar cache de protocolo
        if (isset(self::$protocolo_cache[$ip]) && self::$protocolo_cache[$ip] === 'https') {
            $protocolo = 'https';
        } else {
            $protocolo = ($porta == 443) ? 'https' : 'http';
        }
        
        $url = "{$protocolo}://{$ip}";
        if ($porta != 80 && $porta != 443) {
            $url .= ":{$porta}";
        }
        return $url . $endpoint;
    }
    
    /**
     * Executa requisição cURL com suporte a HTTPS e redirecionamentos
     * 
     * @param string $url URL do endpoint
     * @param string $usuario Usuário do dispositivo
     * @param string $senha Senha do dispositivo
     * @param array $opcoes Opções adicionais do cURL
     * @param callable|null $log_callback Função de callback para logs
     * @return array ['resposta' => string, 'codigo' => int, 'erro' => string]
     */
    public static function executarRequisicao($url, $usuario, $senha, $opcoes = [], $log_callback = null) {
        // Extrair IP da URL para cache
        preg_match('/(?:https?:\/\/)?([^:\/]+)/', $url, $matches);
        $ip = $matches[1] ?? '';
        
        // Se já sabemos que este dispositivo usa HTTPS, usar direto
        if (isset(self::$protocolo_cache[$ip]) && self::$protocolo_cache[$ip] === 'https' && strpos($url, 'https://') === false) {
            $url = str_replace('http://', 'https://', $url);
        }
        
        if ($log_callback) $log_callback("Executando requisição: $url");
        
        $ch = curl_init();
        
        // Configurações padrão
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch, CURLOPT_USERPWD, "{$usuario}:{$senha}");
        curl_setopt($ch, CURLOPT_TIMEOUT, $opcoes['timeout'] ?? self::TIMEOUT_OPERACAO);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT_CONEXAO);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        // Aplicar opções adicionais
        if (isset($opcoes['post']) && $opcoes['post']) {
            curl_setopt($ch, CURLOPT_POST, true);
        }
        if (isset($opcoes['postfields'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $opcoes['postfields']);
        }
        if (isset($opcoes['headers']) && is_array($opcoes['headers'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $opcoes['headers']);
        }
        
        $resposta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch);
        curl_close($ch);
        
        // Se recebeu 302 ou 401 e não está usando HTTPS, tentar HTTPS
        if (($codigo == 302 || $codigo == 401) && strpos($url, 'https://') === false && !isset(self::$protocolo_cache[$ip])) {
            if ($log_callback) $log_callback("Tentando HTTPS...");
            
            $url_https = str_replace('http://', 'https://', $url);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url_https);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($ch, CURLOPT_USERPWD, "{$usuario}:{$senha}");
            curl_setopt($ch, CURLOPT_TIMEOUT, $opcoes['timeout'] ?? self::TIMEOUT_OPERACAO);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT_CONEXAO);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            
            if (isset($opcoes['post']) && $opcoes['post']) {
                curl_setopt($ch, CURLOPT_POST, true);
            }
            if (isset($opcoes['postfields'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $opcoes['postfields']);
            }
            if (isset($opcoes['headers']) && is_array($opcoes['headers'])) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $opcoes['headers']);
            }
            
            $resposta = curl_exec($ch);
            $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $erro = curl_error($ch);
            curl_close($ch);
            
            // Cachear o protocolo que funcionou
            if ($codigo >= 200 && $codigo < 400) {
                self::$protocolo_cache[$ip] = 'https';
            }
        }
        
        if ($log_callback) $log_callback("Resposta HTTP: $codigo");
        
        return ['resposta' => $resposta, 'codigo' => $codigo, 'erro' => $erro];
    }
    
    /**
     * Verifica se a resposta indica sucesso
     * 
     * @param string $resposta Resposta do dispositivo
     * @param int $codigo Código HTTP
     * @return bool
     */
    public static function respostaSucesso($resposta, $codigo) {
        if ($codigo < 200 || $codigo >= 400) {
            return false;
        }
        return (strpos($resposta, 'OK') !== false || $codigo == 200);
    }
    
    // ==========================================
    // FUNÇÕES PARA DISPOSITIVOS INTELBRAS/DAHUA
    // ==========================================
    
    /**
     * Testa conexão com dispositivo Intelbras/Dahua
     * 
     * @param string $ip IP do dispositivo
     * @param int $porta Porta do dispositivo
     * @param string $usuario Usuário do dispositivo
     * @param string $senha Senha do dispositivo
     * @param callable|null $log_callback Função de log
     * @return array ['sucesso' => bool, 'mensagem' => string, 'detalhes' => array]
     */
    public static function testarConexaoIntelbras($ip, $porta, $usuario, $senha, $log_callback = null) {
        $url = self::construirUrl($ip, $porta, '/cgi-bin/global.cgi?action=getCurrentTime');
        
        $resultado = self::executarRequisicao($url, $usuario, $senha, [], $log_callback);
        
        if ($resultado['codigo'] == 200) {
            return [
                'sucesso' => true,
                'mensagem' => 'Dispositivo online e respondendo',
                'detalhes' => [
                    'codigo_http' => $resultado['codigo'],
                    'resposta' => $resultado['resposta']
                ]
            ];
        } elseif ($resultado['codigo'] == 401) {
            return [
                'sucesso' => false,
                'mensagem' => 'Dispositivo online, mas credenciais inválidas',
                'detalhes' => ['codigo_http' => $resultado['codigo']]
            ];
        } else {
            return [
                'sucesso' => false,
                'mensagem' => 'Falha na conexão: ' . ($resultado['erro'] ?: "HTTP {$resultado['codigo']}"),
                'detalhes' => [
                    'codigo_http' => $resultado['codigo'],
                    'erro' => $resultado['erro']
                ]
            ];
        }
    }
    
    /**
     * Insere usuário no dispositivo Intelbras/Dahua
     * 
     * @param string $ip IP do dispositivo
     * @param int $porta Porta do dispositivo
     * @param string $usuario_disp Usuário do dispositivo
     * @param string $senha_disp Senha do dispositivo
     * @param int|string $user_id ID do usuário a inserir
     * @param string $user_name Nome do usuário
     * @param array $opcoes Opções adicionais (valid_from, valid_to, password, etc)
     * @param callable|null $log_callback Função de log
     * @return array ['sucesso' => bool, 'mensagem' => string]
     */
    public static function inserirUsuarioIntelbras($ip, $porta, $usuario_disp, $senha_disp, $user_id, $user_name, $opcoes = [], $log_callback = null) {
        if ($log_callback) $log_callback("Inserindo usuário #{$user_id} - {$user_name} no dispositivo {$ip}");
        
        // Preparar dados do usuário
        $valid_from = $opcoes['valid_from'] ?? date('Y-m-d H:i:s');
        $valid_to = $opcoes['valid_to'] ?? date('Y-m-d H:i:s', strtotime('+1 year'));
        $password = $opcoes['password'] ?? '123456';
        $user_type = $opcoes['user_type'] ?? 0; // 0 = usuário normal
        $authority = $opcoes['authority'] ?? 2; // 2 = usuário normal
        $doors = $opcoes['doors'] ?? [0];
        $time_sections = $opcoes['time_sections'] ?? [255];
        
        $dados_usuario = [
            "UserList" => [
                [
                    "UserID" => (string)$user_id,
                    "UserName" => $user_name,
                    "UserType" => $user_type,
                    "Authority" => $authority,
                    "Password" => $password,
                    "Doors" => $doors,
                    "TimeSections" => $time_sections,
                    "ValidFrom" => $valid_from,
                    "ValidTo" => $valid_to
                ]
            ]
        ];
        
        $json = json_encode($dados_usuario, JSON_UNESCAPED_UNICODE);
        $url = self::construirUrl($ip, $porta, '/cgi-bin/AccessUser.cgi?action=insertMulti');
        
        $resultado = self::executarRequisicao($url, $usuario_disp, $senha_disp, [
            'post' => true,
            'postfields' => $json,
            'headers' => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json)
            ]
        ], $log_callback);
        
        if (self::respostaSucesso($resultado['resposta'], $resultado['codigo'])) {
            return ['sucesso' => true, 'mensagem' => 'Usuário inserido com sucesso'];
        } else {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao inserir usuário: ' . ($resultado['erro'] ?: $resultado['resposta'])
            ];
        }
    }
    
    /**
     * Remove usuário do dispositivo Intelbras/Dahua
     * 
     * @param string $ip IP do dispositivo
     * @param int $porta Porta do dispositivo
     * @param string $usuario_disp Usuário do dispositivo
     * @param string $senha_disp Senha do dispositivo
     * @param int|string $user_id ID do usuário a remover
     * @param callable|null $log_callback Função de log
     * @return array ['sucesso' => bool, 'mensagem' => string]
     */
    public static function removerUsuarioIntelbras($ip, $porta, $usuario_disp, $senha_disp, $user_id, $log_callback = null) {
        if ($log_callback) $log_callback("Removendo usuário #{$user_id} do dispositivo {$ip}");
        
        $url = self::construirUrl($ip, $porta, "/cgi-bin/AccessUser.cgi?action=removeMulti&UserIDList[0]={$user_id}");
        
        $resultado = self::executarRequisicao($url, $usuario_disp, $senha_disp, [], $log_callback);
        
        // 404 também é considerado sucesso (usuário já não existe)
        if (self::respostaSucesso($resultado['resposta'], $resultado['codigo']) || $resultado['codigo'] == 404) {
            return ['sucesso' => true, 'mensagem' => 'Usuário removido com sucesso'];
        } else {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao remover usuário: ' . ($resultado['erro'] ?: $resultado['resposta'])
            ];
        }
    }
    
    /**
     * Insere foto facial no dispositivo Intelbras/Dahua
     * 
     * @param string $ip IP do dispositivo
     * @param int $porta Porta do dispositivo
     * @param string $usuario_disp Usuário do dispositivo
     * @param string $senha_disp Senha do dispositivo
     * @param int|string $user_id ID do usuário
     * @param string $foto_base64 Foto em base64 (sem prefixo data:image)
     * @param callable|null $log_callback Função de log
     * @return array ['sucesso' => bool, 'mensagem' => string]
     */
    public static function inserirFotoIntelbras($ip, $porta, $usuario_disp, $senha_disp, $user_id, $foto_base64, $log_callback = null) {
        if ($log_callback) $log_callback("Inserindo foto do usuário #{$user_id} no dispositivo {$ip}");
        
        // Remover prefixo data:image se existir
        if (strpos($foto_base64, 'base64,') !== false) {
            $foto_base64 = substr($foto_base64, strpos($foto_base64, 'base64,') + 7);
        }
        
        $dados_foto = [
            "FaceList" => [
                [
                    "UserID" => (string)$user_id,
                    "PhotoData" => [$foto_base64]
                ]
            ]
        ];
        
        $json = json_encode($dados_foto, JSON_UNESCAPED_UNICODE);
        $url = self::construirUrl($ip, $porta, '/cgi-bin/AccessFace.cgi?action=insertMulti');
        
        $resultado = self::executarRequisicao($url, $usuario_disp, $senha_disp, [
            'post' => true,
            'postfields' => $json,
            'headers' => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json)
            ],
            'timeout' => self::TIMEOUT_FOTO
        ], $log_callback);
        
        if (self::respostaSucesso($resultado['resposta'], $resultado['codigo'])) {
            return ['sucesso' => true, 'mensagem' => 'Foto inserida com sucesso'];
        } else {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao inserir foto: ' . ($resultado['erro'] ?: $resultado['resposta'])
            ];
        }
    }
    
    /**
     * Atualiza foto facial no dispositivo Intelbras/Dahua
     * 
     * @param string $ip IP do dispositivo
     * @param int $porta Porta do dispositivo
     * @param string $usuario_disp Usuário do dispositivo
     * @param string $senha_disp Senha do dispositivo
     * @param int|string $user_id ID do usuário
     * @param string $foto_base64 Foto em base64
     * @param callable|null $log_callback Função de log
     * @return array ['sucesso' => bool, 'mensagem' => string]
     */
    public static function atualizarFotoIntelbras($ip, $porta, $usuario_disp, $senha_disp, $user_id, $foto_base64, $log_callback = null) {
        if ($log_callback) $log_callback("Atualizando foto do usuário #{$user_id} no dispositivo {$ip}");
        
        // Remover prefixo data:image se existir
        if (strpos($foto_base64, 'base64,') !== false) {
            $foto_base64 = substr($foto_base64, strpos($foto_base64, 'base64,') + 7);
        }
        
        $dados_foto = [
            "FaceList" => [
                [
                    "UserID" => (string)$user_id,
                    "PhotoData" => [$foto_base64]
                ]
            ]
        ];
        
        $json = json_encode($dados_foto, JSON_UNESCAPED_UNICODE);
        $url = self::construirUrl($ip, $porta, '/cgi-bin/AccessFace.cgi?action=updateMulti');
        
        $resultado = self::executarRequisicao($url, $usuario_disp, $senha_disp, [
            'post' => true,
            'postfields' => $json,
            'headers' => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json)
            ],
            'timeout' => self::TIMEOUT_FOTO
        ], $log_callback);
        
        if (self::respostaSucesso($resultado['resposta'], $resultado['codigo'])) {
            return ['sucesso' => true, 'mensagem' => 'Foto atualizada com sucesso'];
        } else {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao atualizar foto: ' . ($resultado['erro'] ?: $resultado['resposta'])
            ];
        }
    }
    
    /**
     * Remove foto facial do dispositivo Intelbras/Dahua
     * 
     * @param string $ip IP do dispositivo
     * @param int $porta Porta do dispositivo
     * @param string $usuario_disp Usuário do dispositivo
     * @param string $senha_disp Senha do dispositivo
     * @param int|string $user_id ID do usuário
     * @param callable|null $log_callback Função de log
     * @return array ['sucesso' => bool, 'mensagem' => string]
     */
    public static function removerFotoIntelbras($ip, $porta, $usuario_disp, $senha_disp, $user_id, $log_callback = null) {
        if ($log_callback) $log_callback("Removendo foto do usuário #{$user_id} do dispositivo {$ip}");
        
        $url = self::construirUrl($ip, $porta, "/cgi-bin/AccessFace.cgi?action=removeMulti&UserIDList[0]={$user_id}");
        
        $resultado = self::executarRequisicao($url, $usuario_disp, $senha_disp, [], $log_callback);
        
        // 404 também é considerado sucesso (foto já não existe)
        if (self::respostaSucesso($resultado['resposta'], $resultado['codigo']) || $resultado['codigo'] == 404) {
            return ['sucesso' => true, 'mensagem' => 'Foto removida com sucesso'];
        } else {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao remover foto: ' . ($resultado['erro'] ?: $resultado['resposta'])
            ];
        }
    }
    
    /**
     * Sincroniza usuário completo (usuário + foto) no dispositivo Intelbras/Dahua
     * 
     * @param string $ip IP do dispositivo
     * @param int $porta Porta do dispositivo
     * @param string $usuario_disp Usuário do dispositivo
     * @param string $senha_disp Senha do dispositivo
     * @param int|string $user_id ID do usuário
     * @param string $user_name Nome do usuário
     * @param string|null $foto_base64 Foto em base64 (opcional)
     * @param array $opcoes Opções adicionais
     * @param callable|null $log_callback Função de log
     * @return array ['sucesso' => bool, 'mensagem' => string, 'detalhes' => array]
     */
    public static function sincronizarUsuarioIntelbras($ip, $porta, $usuario_disp, $senha_disp, $user_id, $user_name, $foto_base64 = null, $opcoes = [], $log_callback = null) {
        $detalhes = [];
        
        // 1. Inserir usuário
        $resultado_usuario = self::inserirUsuarioIntelbras($ip, $porta, $usuario_disp, $senha_disp, $user_id, $user_name, $opcoes, $log_callback);
        $detalhes['usuario'] = $resultado_usuario;
        
        if (!$resultado_usuario['sucesso']) {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao inserir usuário: ' . $resultado_usuario['mensagem'],
                'detalhes' => $detalhes
            ];
        }
        
        // 2. Inserir foto (se fornecida)
        if (!empty($foto_base64)) {
            $resultado_foto = self::inserirFotoIntelbras($ip, $porta, $usuario_disp, $senha_disp, $user_id, $foto_base64, $log_callback);
            $detalhes['foto'] = $resultado_foto;
            
            if ($resultado_foto['sucesso']) {
                return [
                    'sucesso' => true,
                    'mensagem' => 'Usuário sincronizado com foto',
                    'detalhes' => $detalhes
                ];
            } else {
                return [
                    'sucesso' => true, // Parcial
                    'mensagem' => 'Usuário sincronizado, mas erro na foto: ' . $resultado_foto['mensagem'],
                    'detalhes' => $detalhes
                ];
            }
        }
        
        return [
            'sucesso' => true,
            'mensagem' => 'Usuário sincronizado sem foto',
            'detalhes' => $detalhes
        ];
    }
    
    /**
     * Re-sincroniza usuário (remove e adiciona novamente) no dispositivo Intelbras/Dahua
     * 
     * @param string $ip IP do dispositivo
     * @param int $porta Porta do dispositivo
     * @param string $usuario_disp Usuário do dispositivo
     * @param string $senha_disp Senha do dispositivo
     * @param int|string $user_id ID do usuário
     * @param string $user_name Nome do usuário
     * @param string|null $foto_base64 Foto em base64 (opcional)
     * @param array $opcoes Opções adicionais
     * @param callable|null $log_callback Função de log
     * @return array ['sucesso' => bool, 'mensagem' => string, 'detalhes' => array]
     */
    public static function ressincronizarUsuarioIntelbras($ip, $porta, $usuario_disp, $senha_disp, $user_id, $user_name, $foto_base64 = null, $opcoes = [], $log_callback = null) {
        $detalhes = [];
        
        if ($log_callback) $log_callback("Re-sincronizando usuário #{$user_id} no dispositivo {$ip}");
        
        // 1. Remover foto (se existir)
        $resultado_remover_foto = self::removerFotoIntelbras($ip, $porta, $usuario_disp, $senha_disp, $user_id, $log_callback);
        $detalhes['remover_foto'] = $resultado_remover_foto;
        
        // 2. Remover usuário
        $resultado_remover_usuario = self::removerUsuarioIntelbras($ip, $porta, $usuario_disp, $senha_disp, $user_id, $log_callback);
        $detalhes['remover_usuario'] = $resultado_remover_usuario;
        
        // 3. Adicionar usuário novamente
        $resultado_adicionar = self::sincronizarUsuarioIntelbras($ip, $porta, $usuario_disp, $senha_disp, $user_id, $user_name, $foto_base64, $opcoes, $log_callback);
        $detalhes['adicionar'] = $resultado_adicionar;
        
        if ($resultado_adicionar['sucesso']) {
            return [
                'sucesso' => true,
                'mensagem' => 'Usuário re-sincronizado com sucesso',
                'detalhes' => $detalhes
            ];
        } else {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro na re-sincronização: ' . $resultado_adicionar['mensagem'],
                'detalhes' => $detalhes
            ];
        }
    }
    
    /**
     * Limpa todos os dados de um usuário do dispositivo Intelbras/Dahua
     * 
     * @param string $ip IP do dispositivo
     * @param int $porta Porta do dispositivo
     * @param string $usuario_disp Usuário do dispositivo
     * @param string $senha_disp Senha do dispositivo
     * @param int|string $user_id ID do usuário
     * @param callable|null $log_callback Função de log
     * @return array ['sucesso' => bool, 'mensagem' => string, 'detalhes' => array]
     */
    public static function limparUsuarioIntelbras($ip, $porta, $usuario_disp, $senha_disp, $user_id, $log_callback = null) {
        $detalhes = [];
        
        if ($log_callback) $log_callback("Limpando dados do usuário #{$user_id} do dispositivo {$ip}");
        
        // 1. Remover foto
        $resultado_foto = self::removerFotoIntelbras($ip, $porta, $usuario_disp, $senha_disp, $user_id, $log_callback);
        $detalhes['remover_foto'] = $resultado_foto;
        
        // 2. Remover usuário
        $resultado_usuario = self::removerUsuarioIntelbras($ip, $porta, $usuario_disp, $senha_disp, $user_id, $log_callback);
        $detalhes['remover_usuario'] = $resultado_usuario;
        
        $sucesso = $resultado_foto['sucesso'] && $resultado_usuario['sucesso'];
        
        return [
            'sucesso' => $sucesso,
            'mensagem' => $sucesso ? 'Dados do usuário limpos com sucesso' : 'Erro ao limpar dados do usuário',
            'detalhes' => $detalhes
        ];
    }
    
    // ==========================================
    // FUNÇÕES AUXILIARES PARA MÚLTIPLOS DISPOSITIVOS
    // ==========================================
    
    /**
     * Executa operação em múltiplos dispositivos
     * 
     * @param array $dispositivos Lista de dispositivos [['ip' => '', 'porta' => '', 'usuario' => '', 'senha' => ''], ...]
     * @param callable $operacao Função a executar em cada dispositivo
     * @param callable|null $log_callback Função de log
     * @return array ['total' => int, 'sucessos' => int, 'falhas' => int, 'resultados' => array]
     */
    public static function executarEmMultiplosDispositivos($dispositivos, $operacao, $log_callback = null) {
        $resultados = [];
        $sucessos = 0;
        $falhas = 0;
        
        foreach ($dispositivos as $dispositivo) {
            $ip = $dispositivo['ip'];
            $porta = $dispositivo['porta'] ?? 80;
            $usuario = $dispositivo['usuario'];
            $senha = $dispositivo['senha'];
            $nome = $dispositivo['nome'] ?? $ip;
            
            if ($log_callback) $log_callback("Processando dispositivo: {$nome} ({$ip})");
            
            try {
                $resultado = $operacao($ip, $porta, $usuario, $senha, $log_callback);
                $resultado['dispositivo'] = $nome;
                $resultado['ip'] = $ip;
                $resultados[] = $resultado;
                
                if ($resultado['sucesso']) {
                    $sucessos++;
                } else {
                    $falhas++;
                }
            } catch (Exception $e) {
                $falhas++;
                $resultados[] = [
                    'sucesso' => false,
                    'mensagem' => 'Exceção: ' . $e->getMessage(),
                    'dispositivo' => $nome,
                    'ip' => $ip
                ];
            }
        }
        
        return [
            'total' => count($dispositivos),
            'sucessos' => $sucessos,
            'falhas' => $falhas,
            'resultados' => $resultados
        ];
    }
}


