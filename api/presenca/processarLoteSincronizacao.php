<?php
/**
 * processarLoteSincronizacao.php - Processa lotes de sincronização de usuários
 * 
 * Esta versão é compatível com PHP 5.5.38 e não depende da extensão mysqlnd.
 * 
 * @version 1.0
 * @modified 2023-11-09
 */

// Incluir arquivo com função de sincronização
require_once __DIR__ . '/sincronizarUsuario.php';

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
 * Verifica se a tabela facial_sync existe e a cria se necessário
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @return bool True se a tabela existe ou foi criada com sucesso, False em caso de erro
 */
function verificarTabelaSync($conn) {
    try {
        registrarLogSinc("Verificando se a tabela facial_sync existe...");
        
        // Verificar se a tabela existe
        $result = $conn->query("SHOW TABLES LIKE 'facial_sync'");
        if ($result && $result->num_rows > 0) {
            registrarLogSinc("A tabela facial_sync já existe");
            return true;
        }
        
        registrarLogSinc("A tabela facial_sync não existe. Criando tabela...");
        
        // Script SQL para criar a tabela
        $sql = "CREATE TABLE `facial_sync` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `id_usuario` int(11) NOT NULL,
            `data` date NOT NULL,
            `status` enum('pendente','sincronizado','falha') NOT NULL DEFAULT 'pendente',
            `horario_sync` datetime DEFAULT NULL,
            `detalhes` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `id_usuario` (`id_usuario`),
            KEY `data` (`data`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        if ($conn->query($sql)) {
            registrarLogSinc("Tabela facial_sync criada com sucesso");
            return true;
        } else {
            registrarLogSinc("Erro ao criar tabela facial_sync: " . $conn->error);
            return false;
        }
    } catch (Exception $e) {
        registrarLogSinc("Exceção ao verificar/criar tabela: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém configurações do dispositivo da tabela facial_config
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @return array Configurações do dispositivo ou null em caso de erro
 */
function obterConfiguracoesDispositivo($conn) {
    try {
        // Verificar se a tabela existe
        $result = $conn->query("SHOW TABLES LIKE 'facial_config'");
        if (!$result || $result->num_rows == 0) {
            // Tabela não existe, usar valores padrão
            registrarLogSinc("Tabela facial_config não existe. Usando valores padrão.");
            
            return [
                'ip_address' => '10.144.129.69',
                'port' => '80',
                'username' => 'admin',
                'password' => 'Arcs2901'
            ];
        }
        
        $sql = "SELECT ip_address, port, username, password FROM facial_config LIMIT 1";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            registrarLogSinc("Erro ao preparar consulta de configurações: " . $conn->error);
            return null;
        }
        
        if (!$stmt->execute()) {
            registrarLogSinc("Erro ao executar consulta de configurações: " . $stmt->error);
            $stmt->close();
            return null;
        }
        
        // Bind das variáveis para receber o resultado
        $stmt->bind_result($ip_address, $port, $username, $password);
        
        if ($stmt->fetch()) {
            $config = [
                'ip_address' => $ip_address ?: '10.144.129.69',
                'port' => $port ?: '80', // Porta padrão se não estiver definida
                'username' => $username ?: 'admin',
                'password' => $password ?: 'Arcs2901'
            ];
            $stmt->close();
            return $config;
        } else {
            registrarLogSinc("Nenhuma configuração encontrada na tabela facial_config");
            $stmt->close();
            
            // Valores padrão
            return [
                'ip_address' => '10.144.129.69',
                'port' => '80',
                'username' => 'admin',
                'password' => 'Arcs2901'
            ];
        }
    } catch (Exception $e) {
        registrarLogSinc("Exceção ao obter configurações: " . $e->getMessage());
        
        // Valores padrão em caso de erro
        return [
            'ip_address' => '10.144.129.69',
            'port' => '80',
            'username' => 'admin',
            'password' => 'Arcs2901'
        ];
    }
}

/**
 * Processa um lote de sincronizações pendentes
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param string $data_inicio Data para filtrar sincronizações (opcional)
 * @param int $limite Número máximo de registros a processar (opcional)
 * @return array Resultado do processamento
 */
function processarLoteSincronizacao($conn, $data_inicio = null, $limite = 50) {
    registrarLogSinc("======== INICIANDO PROCESSAMENTO DE LOTE ========");
    
    $resultado = [
        'total_processados' => 0,
        'sucessos' => 0,
        'falhas' => 0,
        'registros' => []
    ];
    
    try {
        // Verificar e criar tabela se necessário
        if (!verificarTabelaSync($conn)) {
            return [
                'total_processados' => 0,
                'sucessos' => 0,
                'falhas' => 0,
                'registros' => [],
                'erro' => 'Erro ao verificar/criar tabela facial_sync'
            ];
        }
        
        // Validar parâmetros
        if (!is_numeric($limite) || $limite <= 0) {
            $limite = 50; // Valor padrão
        }
        
        if ($data_inicio !== null && !strtotime($data_inicio)) {
            $data_inicio = null; // Data inválida, ignorar filtro
        }
        
        registrarLogSinc("Parâmetros - limite: $limite, data_inicio: " . ($data_inicio ?: 'não especificada'));
        
        // Obter configurações do dispositivo
        $config = obterConfiguracoesDispositivo($conn);
        if (!$config) {
            return [
                'total_processados' => 0,
                'sucessos' => 0,
                'falhas' => 0,
                'registros' => [],
                'erro' => 'Configurações do dispositivo não encontradas'
            ];
        }
        
        registrarLogSinc("Configurações do dispositivo - IP: {$config['ip_address']}, Porta: {$config['port']}");
        
        // Consultar registros pendentes
        $sql = "SELECT id, id_usuario, data, status FROM facial_sync 
                WHERE status = 'pendente'";
        
        if ($data_inicio !== null) {
            $sql .= " AND data >= ?";
        }
        
        $sql .= " ORDER BY id ASC LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            registrarLogSinc("Erro ao preparar consulta: " . $conn->error);
            return [
                'total_processados' => 0,
                'sucessos' => 0,
                'falhas' => 0,
                'registros' => [],
                'erro' => 'Erro ao preparar consulta: ' . $conn->error
            ];
        }
        
        // Bind dos parâmetros
        if ($data_inicio !== null) {
            $stmt->bind_param("si", $data_inicio, $limite);
        } else {
            $stmt->bind_param("i", $limite);
        }
        
        if (!$stmt->execute()) {
            registrarLogSinc("Erro ao executar consulta: " . $stmt->error);
            $stmt->close();
            return [
                'total_processados' => 0,
                'sucessos' => 0,
                'falhas' => 0,
                'registros' => [],
                'erro' => 'Erro ao executar consulta: ' . $stmt->error
            ];
        }
        
        // Bind das variáveis para receber o resultado
        $stmt->bind_result($id_sync, $id_usuario, $data, $status);
        
        // Array para armazenar os registros
        $registros_pendentes = [];
        
        // Fetch dos resultados
        while ($stmt->fetch()) {
            $registros_pendentes[] = [
                'id_sync' => $id_sync,
                'id_usuario' => $id_usuario,
                'data' => $data,
                'status' => $status
            ];
        }
        
        $stmt->close();
        
        $total_pendentes = count($registros_pendentes);
        registrarLogSinc("Encontrados $total_pendentes registros pendentes para processamento");
        
        if ($total_pendentes == 0) {
            registrarLogSinc("======== FIM DO PROCESSAMENTO - SEM REGISTROS PENDENTES ========");
            return $resultado;
        }
        
        // Processar cada registro
        foreach ($registros_pendentes as $registro) {
            $id_sync = $registro['id_sync'];
            $id_usuario = $registro['id_usuario'];
            
            // Obter dados do usuário
            $sql_usuario = "SELECT id, nome, foto_base64 FROM usuarios WHERE id = ?";
            $stmt_usuario = $conn->prepare($sql_usuario);
            
            if (!$stmt_usuario) {
                registrarLogSinc("Erro ao preparar consulta de usuário: " . $conn->error);
                $resultado['falhas']++;
                continue;
            }
            
            $stmt_usuario->bind_param("i", $id_usuario);
            
            if (!$stmt_usuario->execute()) {
                registrarLogSinc("Erro ao executar consulta de usuário: " . $stmt_usuario->error);
                $stmt_usuario->close();
                $resultado['falhas']++;
                continue;
            }
            
            $stmt_usuario->bind_result($id, $nome, $foto_base64);
            
            if (!$stmt_usuario->fetch()) {
                registrarLogSinc("Usuário ID $id_usuario não encontrado");
                $stmt_usuario->close();
                
                // Atualizar status para 'falha'
                $sql_update = "UPDATE facial_sync SET status = 'falha', horario_sync = NOW(), 
                              detalhes = 'Usuário não encontrado' WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("i", $id_sync);
                $stmt_update->execute();
                $stmt_update->close();
                
                $resultado['falhas']++;
                continue;
            }
            
            $stmt_usuario->close();
            
            // Verificar se o usuário tem reserva ativa
            $tem_reserva = false;
            
            $sql_reserva = "SELECT COUNT(*) as total FROM reservas_almoco 
                           WHERE id_usuario = ? AND data = ?";
            $stmt_reserva = $conn->prepare($sql_reserva);
            
            if ($stmt_reserva) {
                $stmt_reserva->bind_param("is", $id_usuario, $registro['data']);
                
                if ($stmt_reserva->execute()) {
                    $stmt_reserva->bind_result($total_reservas);
                    $stmt_reserva->fetch();
                    $tem_reserva = ($total_reservas > 0);
                }
                
                $stmt_reserva->close();
            }
            
            if (!$tem_reserva) {
                registrarLogSinc("Usuário ID $id_usuario não possui reserva para a data {$registro['data']}");
                
                // Atualizar status para 'falha'
                $sql_update = "UPDATE facial_sync SET status = 'falha', horario_sync = NOW(), 
                              detalhes = 'Usuário não possui reserva de almoço para esta data' WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("i", $id_sync);
                $stmt_update->execute();
                $stmt_update->close();
                
                $resultado['falhas']++;
                $resultado['registros'][] = [
                    'id_sync' => $id_sync,
                    'id_usuario' => $id_usuario,
                    'nome' => $nome,
                    'status' => 'falha',
                    'mensagem' => 'Usuário não possui reserva de almoço para esta data',
                    'tem_reserva' => false
                ];
                
                continue;
            }
            
            // Preparar dados do usuário
            $usuario = [
                'id' => $id,
                'nome' => $nome,
                'foto_base64' => $foto_base64
            ];
            
            registrarLogSinc("Sincronizando usuário ID: $id_usuario, Nome: $nome");
            
            // Sincronizar usuário com o dispositivo
            $resultado_sinc = sincronizarUsuario(
                $usuario,
                $config['ip_address'],
                $config['port'],
                $config['username'],
                $config['password']
            );
            
            // Atualizar registro de sincronização
            $novo_status = $resultado_sinc['sucesso'] ? 'sincronizado' : 'falha';
            $mensagem = $resultado_sinc['mensagem'];
            
            $sql_update = "UPDATE facial_sync SET status = ?, horario_sync = NOW(), 
                          detalhes = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ssi", $novo_status, $mensagem, $id_sync);
            $stmt_update->execute();
            $stmt_update->close();
            
            // Atualizar contadores
            $resultado['total_processados']++;
            if ($resultado_sinc['sucesso']) {
                $resultado['sucessos']++;
            } else {
                $resultado['falhas']++;
            }
            
            // Adicionar ao resultado
            $resultado['registros'][] = [
                'id_sync' => $id_sync,
                'id_usuario' => $id_usuario,
                'nome' => $nome,
                'status' => $novo_status,
                'mensagem' => $mensagem,
                'tem_reserva' => $tem_reserva
            ];
            
            // Log do resultado
            registrarLogSinc("Resultado para usuário ID $id_usuario: $novo_status - $mensagem");
        }
        
        registrarLogSinc("Lote processado: " . $resultado['total_processados'] . " registros, " . 
                      $resultado['sucessos'] . " sucessos, " . $resultado['falhas'] . " falhas");
        registrarLogSinc("======== FIM DO PROCESSAMENTO ========");
        
        return $resultado;
        
    } catch (Exception $e) {
        registrarLogSinc("Exceção ao processar lote: " . $e->getMessage());
        registrarLogSinc("======== FIM DO PROCESSAMENTO COM ERRO ========");
        
        return [
            'total_processados' => $resultado['total_processados'],
            'sucessos' => $resultado['sucessos'],
            'falhas' => $resultado['falhas'] + 1,
            'registros' => $resultado['registros'],
            'erro' => "Exceção: " . $e->getMessage()
        ];
    }
} 