<?php
/**
 * NotificacaoService - Serviço para gravar histórico de notificações
 * 
 * Centraliza o registro de todas as notificações enviadas (WhatsApp e Email)
 * 
 * @version 1.0
 * @author Sistema de Presença
 */

class NotificacaoService {
    
    /**
     * Grava notificação enviada no histórico
     * 
     * @param array $dados Dados da notificação:
     *   - 'usuario_id' => int|null (ID do usuário, opcional)
     *   - 'tipo_notificacao' => 'whatsapp'|'email'
     *   - 'tipo_mensagem' => string|null (ex: 'lembrete_reserva')
     *   - 'destinatario' => string (telefone ou email)
     *   - 'nome_destinatario' => string|null
     *   - 'assunto' => string|null (para emails)
     *   - 'mensagem' => string|null (conteúdo)
     *   - 'status' => 'sucesso'|'falha'
     *   - 'mensagem_erro' => string|null
     *   - 'resposta_api' => string|null (JSON da resposta)
     * @return int|false ID da notificação gravada ou false em caso de erro
     */
    public static function gravarNotificacao($dados) {
        global $conn, $db_conn;
        
        // Tentar usar conexão global primeiro
        if (isset($db_conn) && $db_conn instanceof mysqli) {
            try {
                if ($db_conn->ping()) {
                    $conn = $db_conn;
                }
            } catch (Exception $e) {
                // Continuar para tentar outras opções
            }
        }
        
        if (!isset($conn) || !($conn instanceof mysqli)) {
            // Tentar obter conexão
            $paths = [
                __DIR__ . '/../../api/conexao.php',
                __DIR__ . '/../../config/config.php'
            ];
            
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    unset($conn);
                    include $path;
                    if (isset($conn) && $conn instanceof mysqli) {
                        try {
                            if ($conn->ping()) {
                                break;
                            }
                        } catch (Exception $e) {
                            // Continuar para tentar próximo path
                        }
                    }
                }
            }
        }
        
        // Se ainda não tem conexão, criar nova diretamente
        if (!isset($conn) || !($conn instanceof mysqli)) {
            try {
                $host = 'localhost';
                $usuario = 'root';
                $senha = '@Arcs2901';
                $banco = 'presenca_aom';
                
                $conn = new mysqli($host, $usuario, $senha, $banco);
                if (!$conn->connect_error) {
                    $conn->set_charset("utf8");
                } else {
                    error_log("NotificacaoService: Erro ao criar conexão: " . $conn->connect_error);
                    return false;
                }
            } catch (Exception $e) {
                error_log("NotificacaoService: Erro ao criar conexão: " . $e->getMessage());
                return false;
            }
        }
        
        if (!isset($conn) || !($conn instanceof mysqli)) {
            error_log("NotificacaoService: Não foi possível obter conexão com banco");
            return false;
        }
        
        // Verificar se tabela existe
        try {
            $result = $conn->query("SHOW TABLES LIKE 'notificacoes_enviadas'");
            if (!$result || $result->num_rows === 0) {
                // Tabela não existe - não quebra o sistema, apenas loga
                error_log("NotificacaoService: Tabela notificacoes_enviadas não existe");
                return false;
            }
        } catch (Exception $e) {
            error_log("NotificacaoService: Erro ao verificar tabela: " . $e->getMessage());
            return false;
        }
        
        // Preparar dados
        // Se usuario_id for 0 ou inválido, usar NULL para evitar erro de foreign key
        $usuario_id_raw = isset($dados['usuario_id']) ? $dados['usuario_id'] : null;
        $usuario_id = ($usuario_id_raw && intval($usuario_id_raw) > 0) ? intval($usuario_id_raw) : null;
        $tipo_notificacao = $dados['tipo_notificacao'] ?? 'whatsapp';
        $tipo_mensagem = $dados['tipo_mensagem'] ?? null;
        $destinatario = $dados['destinatario'] ?? '';
        $nome_destinatario = $dados['nome_destinatario'] ?? null;
        $assunto = $dados['assunto'] ?? null;
        $mensagem = $dados['mensagem'] ?? null;
        $status = $dados['status'] ?? 'sucesso';
        $mensagem_erro = $dados['mensagem_erro'] ?? null;
        $resposta_api = $dados['resposta_api'] ?? null;
        
        // Validar tipo_notificacao
        if (!in_array($tipo_notificacao, ['whatsapp', 'email'])) {
            $tipo_notificacao = 'whatsapp';
        }
        
        // Validar status
        if (!in_array($status, ['sucesso', 'falha'])) {
            $status = 'sucesso';
        }
        
        // Limitar tamanho de campos
        $destinatario = substr($destinatario, 0, 255);
        $nome_destinatario = $nome_destinatario ? substr($nome_destinatario, 0, 255) : null;
        $assunto = $assunto ? substr($assunto, 0, 255) : null;
        $tipo_mensagem = $tipo_mensagem ? substr($tipo_mensagem, 0, 50) : null;
        
        // Limitar tamanho de mensagem (TEXT pode ter até 65KB, mas vamos limitar a 10KB)
        if ($mensagem && strlen($mensagem) > 10000) {
            $mensagem = substr($mensagem, 0, 10000) . '... [truncado]';
        }
        
        // Limitar tamanho de resposta_api
        if ($resposta_api && strlen($resposta_api) > 5000) {
            $resposta_api = substr($resposta_api, 0, 5000) . '... [truncado]';
        }
        
        try {
            $stmt = $conn->prepare("
                INSERT INTO notificacoes_enviadas 
                (usuario_id, tipo_notificacao, tipo_mensagem, destinatario, nome_destinatario, assunto, mensagem, status, mensagem_erro, resposta_api)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if (!$stmt) {
                error_log("NotificacaoService: Erro ao preparar query: " . $conn->error);
                return false;
            }
            
            $stmt->bind_param(
                "isssssssss",
                $usuario_id,
                $tipo_notificacao,
                $tipo_mensagem,
                $destinatario,
                $nome_destinatario,
                $assunto,
                $mensagem,
                $status,
                $mensagem_erro,
                $resposta_api
            );
            
            if ($stmt->execute()) {
                $id = $conn->insert_id;
                $stmt->close();
                return $id;
            } else {
                error_log("NotificacaoService: Erro ao executar query: " . $stmt->error);
                $stmt->close();
                return false;
            }
            
        } catch (Exception $e) {
            error_log("NotificacaoService: Exceção ao gravar notificação: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Grava notificação de WhatsApp
     * 
     * @param string $telefone Telefone do destinatário
     * @param string $mensagem Mensagem enviada
     * @param array $resultado Resultado do envio (do WhatsAppService)
     * @param int|null $usuario_id ID do usuário
     * @param string|null $nome_destinatario Nome do destinatário
     * @param string|null $tipo_mensagem Tipo da mensagem (ex: 'lembrete_reserva')
     * @return int|false
     */
    public static function gravarWhatsApp($telefone, $mensagem, $resultado, $usuario_id = null, $nome_destinatario = null, $tipo_mensagem = null) {
        $dados = [
            'usuario_id' => $usuario_id,
            'tipo_notificacao' => 'whatsapp',
            'tipo_mensagem' => $tipo_mensagem,
            'destinatario' => $telefone,
            'nome_destinatario' => $nome_destinatario,
            'mensagem' => $mensagem,
            'status' => $resultado['sucesso'] ? 'sucesso' : 'falha',
            'mensagem_erro' => $resultado['sucesso'] ? null : ($resultado['mensagem'] ?? 'Erro desconhecido'),
            'resposta_api' => isset($resultado['detalhes']) ? json_encode($resultado['detalhes'], JSON_UNESCAPED_UNICODE) : null
        ];
        
        return self::gravarNotificacao($dados);
    }
    
    /**
     * Grava notificação de Email
     * 
     * @param string $email Email do destinatário
     * @param string $assunto Assunto do email
     * @param string $mensagem Mensagem enviada
     * @param bool $sucesso Se o envio foi bem-sucedido
     * @param string|null $erro Mensagem de erro se falhou
     * @param int|null $usuario_id ID do usuário
     * @param string|null $nome_destinatario Nome do destinatário
     * @param string|null $tipo_mensagem Tipo da mensagem
     * @return int|false
     */
    public static function gravarEmail($email, $assunto, $mensagem, $sucesso, $erro = null, $usuario_id = null, $nome_destinatario = null, $tipo_mensagem = null) {
        $dados = [
            'usuario_id' => $usuario_id,
            'tipo_notificacao' => 'email',
            'tipo_mensagem' => $tipo_mensagem,
            'destinatario' => $email,
            'nome_destinatario' => $nome_destinatario,
            'assunto' => $assunto,
            'mensagem' => $mensagem,
            'status' => $sucesso ? 'sucesso' : 'falha',
            'mensagem_erro' => $sucesso ? null : ($erro ?? 'Erro desconhecido')
        ];
        
        return self::gravarNotificacao($dados);
    }
}

