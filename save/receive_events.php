<?php
// receive_events.php
declare(strict_types=1);

// Incluir configuração de timezone e conexão com banco
require_once __DIR__ . '/../config/timezone.php';
require_once __DIR__ . '/../api/conexao.php';

// Caminhos
$baseDir   = __DIR__;
$logFile   = $baseDir . '/events.log';
$rawDir    = $baseDir . '/raw';
$imgDir    = $baseDir . '/images';

// Garante pastas
@is_dir($rawDir) || @mkdir($rawDir, 0775, true);
@is_dir($imgDir) || @mkdir($imgDir, 0775, true);

// Captura IP e horário
$now = gmdate('c');
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Função para log de eventos
function logEvento($mensagem) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $mensagem" . PHP_EOL;
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Função para processar presença do culto
function processarPresencaCulto($user_id, $time_evento, $ip_dispositivo) {
    global $conn;
    
    try {
        // Verificar se o dispositivo é do tipo 'culto'
        $stmt = $conn->prepare("SELECT id, nome, tipo_dispositivo FROM dispositivos_faciais WHERE ip = ? AND tipo_dispositivo = 'culto' AND ativo = 1");
        if (!$stmt) {
            logEvento("ERRO: Falha ao preparar consulta de dispositivo: " . $conn->error);
            return false;
        }
        $stmt->bind_param("s", $ip_dispositivo);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        if ($resultado->num_rows === 0) {
            logEvento("Dispositivo $ip_dispositivo não é do tipo 'culto' ou está inativo - evento ignorado");
            $stmt->close();
            return false;
        }
        
        $dispositivo = $resultado->fetch_assoc();
        $stmt->close();
        
        // Buscar usuário na tabela usuarios
        $stmt = $conn->prepare("SELECT id, nome, email FROM usuarios WHERE id = ?");
        if (!$stmt) {
            logEvento("ERRO: Falha ao preparar consulta de usuário: " . $conn->error);
            return false;
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        if ($resultado->num_rows === 0) {
            logEvento("Usuário ID $user_id não encontrado - evento ignorado");
            $stmt->close();
            return false;
        }
        
        $usuario = $resultado->fetch_assoc();
        $stmt->close();
        
        // Buscar configurações do culto
        $stmt = $conn->prepare("SELECT chave, valor FROM configuracoes_culto WHERE chave IN ('horario_inicio', 'horario_fim', 'tolerancia_atraso', 'dias_semana', 'permitir_atraso')");
        if (!$stmt) {
            logEvento("ERRO: Falha ao preparar consulta de configurações: " . $conn->error);
            return false;
        }
        
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        $config = [];
        while ($row = $resultado->fetch_assoc()) {
            $config[$row['chave']] = $row['valor'];
        }
        $stmt->close();
        
        // Valores padrão se não encontrar configurações
        $horario_inicio = $config['horario_inicio'] ?? '07:30:00';
        $horario_fim = $config['horario_fim'] ?? '08:30:00';
        $tolerancia_atraso = (int)($config['tolerancia_atraso'] ?? '15');
        $dias_semana = $config['dias_semana'] ?? '1,2,3,4,5';
        $permitir_atraso = (int)($config['permitir_atraso'] ?? '1'); // 1=permitir, 0=não permitir
        
        // Verificar se hoje é dia de culto
        $dia_atual = date('N'); // 1=segunda, 7=domingo
        $dias_culto = explode(',', $dias_semana);
        $dias_culto = array_map('trim', $dias_culto);
        
        if (!in_array($dia_atual, $dias_culto)) {
            logEvento("Leitura recebida - Usuário {$usuario['nome']} (ID: $user_id) às $time_evento - NÃO PROCESSADA: Hoje não é dia de culto (dia $dia_atual). Dias configurados: $dias_semana");
            return false; // Não processar a leitura
        }
        
        // Converter horários para comparação
        $data_hoje = date('Y-m-d');
        $horario_leitura = date('H:i:s', strtotime($time_evento));
        
        // Calcular horário limite para presente (início + tolerância)
        $horario_limite_presente = date('H:i:s', strtotime($horario_inicio . ' +' . $tolerancia_atraso . ' minutes'));
        
        // Determinar status da presença
        $status = '';
        $observacoes = '';
        $status_display = '';
        
        if ($horario_leitura >= $horario_inicio && $horario_leitura <= $horario_limite_presente) {
            // Presente: entre horário de início e limite de tolerância
            $status = 'presente';
            $status_display = 'Presente';
            $observacoes = 'Leitura facial - Presente';
        } elseif ($horario_leitura > $horario_limite_presente && $horario_leitura <= $horario_fim) {
            // Verificar se atraso é permitido
            if ($permitir_atraso == 1) {
                // Atrasado: após tolerância mas antes do horário fim (permitido)
                $status = 'atrasado';
                $status_display = 'Atrasado';
                $observacoes = 'Leitura facial - Atrasado';
            } else {
                // Atrasado não permitido: tratar como falta
                $status = 'falta';
                $status_display = 'Falta (atraso não permitido)';
                $observacoes = 'Leitura facial - Falta (atraso não permitido)';
            }
        } else {
            // Fora de horário: antes do início ou após o fim
            $status = 'falta';
            $status_display = 'Fora de horário';
            $observacoes = 'Leitura facial - Fora de horário';
        }
        
        // Se estiver fora de horário, descartar e manter falta (não atualizar presença)
        if ($status === 'falta') {
            logEvento("Leitura recebida - Usuário {$usuario['nome']} (ID: $user_id) às $time_evento - DESCARTADA: $status_display (horário: $horario_leitura, início: $horario_inicio, fim: $horario_fim)");
            return false; // Não processar, manter falta
        }
        
        // Log do resultado do processamento
        logEvento("Leitura recebida - Usuário {$usuario['nome']} (ID: $user_id) às $time_evento - PROCESSADA: $status_display");
        
        // Verificar se já existe presença para este usuário hoje
        $stmt = $conn->prepare("SELECT id FROM presencas_culto WHERE id_usuario = ? AND data = ?");
        if (!$stmt) {
            logEvento("ERRO: Falha ao preparar consulta de presença: " . $conn->error);
            return false;
        }
        $stmt->bind_param("is", $user_id, $data_hoje);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        if ($resultado->num_rows > 0) {
            // Atualizar presença existente
            $stmt->close();
            $stmt = $conn->prepare("UPDATE presencas_culto SET 
                horario_confirmacao = ?, 
                tipo_confirmacao = 'facial', 
                status = ?, 
                observacoes = ?,
                data_cadastro = NOW()
                WHERE id_usuario = ? AND data = ?");
            if (!$stmt) {
                logEvento("ERRO: Falha ao preparar update de presença: " . $conn->error);
                return false;
            }
            $stmt->bind_param("sssis", $horario_leitura, $status, $observacoes, $user_id, $data_hoje);
            
            if ($stmt->execute()) {
                $stmt->close();
                logEvento("Presença atualizada para usuário {$usuario['nome']} (ID: $user_id): $status_display");
                return true;
            } else {
                logEvento("ERRO ao atualizar presença: " . $conn->error);
                $stmt->close();
                return false;
            }
        } else {
            // Inserir nova presença
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO presencas_culto 
                (id_usuario, data, horario_confirmacao, tipo_confirmacao, status, observacoes, data_cadastro) 
                VALUES (?, ?, ?, 'facial', ?, ?, NOW())");
            if (!$stmt) {
                logEvento("ERRO: Falha ao preparar insert de presença: " . $conn->error);
                return false;
            }
            $stmt->bind_param("issss", $user_id, $data_hoje, $horario_leitura, $status, $observacoes);
            
            if ($stmt->execute()) {
                $stmt->close();
                logEvento("Nova presença inserida para usuário {$usuario['nome']} (ID: $user_id): $status_display");
                return true;
            } else {
                logEvento("ERRO ao inserir presença: " . $conn->error);
                $stmt->close();
                return false;
            }
        }
        
    } catch (Exception $e) {
        logEvento("ERRO ao processar presença: " . $e->getMessage());
        return false;
    }
}

// Tenta JSON puro do corpo
$raw = file_get_contents('php://input') ?: '';
$payload = null;
$filesSaved = [];

$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') !== false) {
    $payload = json_decode($raw, true);
} elseif (!empty($_POST)) {
    // Multipart: alguns dispositivos mandam JSON em um campo, ex: "info"
    $payload = $_POST;
    if (isset($_POST['info']) && is_string($_POST['info'])) {
        $maybe = json_decode($_POST['info'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $payload['info'] = $maybe;
        }
    }
} else {
    // Se não identificou, ainda assim guarda o bruto
    $payload = ['raw' => $raw];
}

// Salva arquivos enviados (ex.: foto)
foreach ($_FILES as $field => $f) {
    if (is_array($f['name'])) {
        for ($i = 0; $i < count($f['name']); $i++) {
            if ($f['error'][$i] === UPLOAD_ERR_OK) {
                $name = preg_replace('/[^\w\.\-]+/', '_', $f['name'][$i]);
                $dest = $imgDir . '/' . date('Ymd_His') . "_{$field}_$i" . "_" . $name;
                if (move_uploaded_file($f['tmp_name'][$i], $dest)) {
                    $filesSaved[] = basename($dest);
                }
            }
        }
    } else {
        if ($f['error'] === UPLOAD_ERR_OK) {
            $name = preg_replace('/[^\w\.\-]+/', '_', $f['name']);
            $dest = $imgDir . '/' . date('Ymd_His') . "_{$field}_" . $name;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $filesSaved[] = basename($dest);
            }
        }
    }
}

// Sempre guarda também o bruto se veio algo no corpo
if ($raw !== '') {
    file_put_contents($rawDir . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.bin', $raw);
}

// Processar evento de leitura facial (se for do tipo culto)
$presenca_processada = false;
if ($payload && is_array($payload)) {
    // Tentar extrair UserID de diferentes formatos possíveis
    $user_id = null;
    $time_evento = date('Y-m-d H:i:s'); // Padrão: agora
    
    // Formato 1: UserID direto
    if (isset($payload['UserID'])) {
        $user_id = (int)$payload['UserID'];
    } elseif (isset($payload['user_id'])) {
        $user_id = (int)$payload['user_id'];
    } elseif (isset($payload['PersonID'])) {
        $user_id = (int)$payload['PersonID'];
    }
    
    // Formato 2: Dentro de Events array
    if (!$user_id && isset($payload['Events']) && is_array($payload['Events'])) {
        foreach ($payload['Events'] as $event_item) {
            if (isset($event_item['UserID'])) {
                $user_id = (int)$event_item['UserID'];
                // Tentar extrair timestamp do evento
                if (isset($event_item['Time'])) {
                    $time_evento = date('Y-m-d H:i:s', strtotime($event_item['Time']));
                } elseif (isset($event_item['ISOTime'])) {
                    $time_evento = $event_item['ISOTime'];
                } elseif (isset($event_item['UTCTime']) && is_numeric($event_item['UTCTime'])) {
                    $time_evento = date('Y-m-d H:i:s', $event_item['UTCTime']);
                }
                break;
            }
        }
    }
    
    // Formato 3: Dentro de Data
    if (!$user_id && isset($payload['Data']) && is_array($payload['Data'])) {
        if (isset($payload['Data']['UserID'])) {
            $user_id = (int)$payload['Data']['UserID'];
        }
        if (isset($payload['Data']['Time'])) {
            $time_evento = date('Y-m-d H:i:s', strtotime($payload['Data']['Time']));
        } elseif (isset($payload['Data']['ISOTime'])) {
            $time_evento = $payload['Data']['ISOTime'];
        }
    }
    
    // Formato 4: Dentro de info (multipart)
    if (!$user_id && isset($payload['info']) && is_array($payload['info'])) {
        if (isset($payload['info']['UserID'])) {
            $user_id = (int)$payload['info']['UserID'];
        }
    }
    
    // Se encontrou UserID, processar presença
    if ($user_id && $user_id > 0) {
        $presenca_processada = processarPresencaCulto($user_id, $time_evento, $ip);
    } else {
        logEvento("Evento recebido mas UserID não encontrado no payload");
    }
}

// Monta registro
$event = [
    'ts'        => $now,
    'remote_ip' => $ip,
    'headers'   => array_change_key_case(getallheaders() ?: [], CASE_LOWER),
    'payload'   => $payload,
    'files'     => $filesSaved,
    'presenca_processada' => $presenca_processada,
];

// Grava linha JSON em events.log (JSONL)
$line = json_encode($event, JSON_UNESCAPED_UNICODE) . PHP_EOL;
file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

// Resposta simples
header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'presenca_processada' => $presenca_processada
]);
