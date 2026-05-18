<?php
/**
 * API para processar leitura facial e inserir presença
 * Implementa a lógica de presente/atrasado/falta baseada nos horários
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';
require_once '../../config/timezone.php';

function logLeituraFacial($mensagem) {
    $logFile = __DIR__ . '/../../logs/leitura_facial_culto_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $mensagem" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function retornarErro($mensagem, $codigo = 400) {
    http_response_code($codigo);
    echo json_encode([
        'success' => false,
        'error' => $mensagem,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

try {
    // Verificar se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        retornarErro('Método não permitido. Use POST.', 405);
    }
    
    // Obter dados do POST
    $input = file_get_contents('php://input');
    $dados = json_decode($input, true);
    
    if (!$dados) {
        retornarErro('Dados JSON inválidos.', 400);
    }
    
    // Validar campos obrigatórios
    $user_id = $dados['user_id'] ?? null;
    $event_type = $dados['event_type'] ?? null;
    $result = $dados['result'] ?? null;
    $time = $dados['time'] ?? null;
    
    if (!$user_id || !$event_type || !$result || !$time) {
        retornarErro('Campos obrigatórios: user_id, event_type, result, time', 400);
    }
    
    // Verificar se é leitura facial válida
    if ($event_type !== 'FaceRecognition' && $event_type !== 'CardRecognition') {
        retornarErro('Tipo de evento não suportado: ' . $event_type, 400);
    }
    
    if ($result !== 'Pass') {
        retornarErro('Leitura não aprovada: ' . $result, 400);
    }
    
    // Buscar usuário na tabela usuarios
    $stmt = $conn->prepare("SELECT id, nome, email FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 0) {
        retornarErro('Usuário não encontrado: ' . $user_id, 404);
    }
    
    $usuario = $resultado->fetch_assoc();
    $stmt->close();
    
    // Buscar configurações do culto
    $stmt = $conn->prepare("SELECT horario_inicio, horario_fim, tolerancia_atraso FROM configuracoes_culto WHERE id = 1");
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 0) {
        retornarErro('Configurações do culto não encontradas', 500);
    }
    
    $config = $resultado->fetch_assoc();
    $stmt->close();
    
    // Converter horários para comparação
    $data_hoje = date('Y-m-d');
    $horario_leitura = date('H:i:s', strtotime($time));
    $horario_inicio = $config['horario_inicio'];
    $horario_fim = $config['horario_fim'];
    $tolerancia_atraso = $config['tolerancia_atraso'];
    
    // Determinar status da presença
    $status = '';
    $falta = 0;
    $presente = 0;
    $atrasado = 0;
    $observacoes = '';
    
    if ($horario_leitura >= $horario_inicio && $horario_leitura <= $tolerancia_atraso) {
        // Presente: entre horário de início e tolerância
        $status = 'presente';
        $presente = 1;
        $observacoes = 'Leitura facial - Presente';
    } elseif ($horario_leitura > $tolerancia_atraso && $horario_leitura <= $horario_fim) {
        // Atrasado: entre tolerância e horário fim
        $status = 'atrasado';
        $atrasado = 1;
        $observacoes = 'Leitura facial - Atrasado';
    } else {
        // Falta: após horário fim
        $status = 'falta';
        $falta = 1;
        $observacoes = 'Leitura facial - Falta (após horário)';
    }
    
    // Verificar se já existe presença para este usuário hoje
    $stmt = $conn->prepare("SELECT id FROM presencas_culto WHERE id_usuario = ? AND data = ?");
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
            falta = ?, 
            presente = ?, 
            atrasado = ?, 
            observacoes = ?,
            data_cadastro = NOW()
            WHERE id_usuario = ? AND data = ?");
        $stmt->bind_param("ssiiiss", $horario_leitura, $status, $falta, $presente, $atrasado, $observacoes, $user_id, $data_hoje);
        
        if ($stmt->execute()) {
            $stmt->close();
            logLeituraFacial("Presença atualizada para usuário $user_id: $status");
            
            echo json_encode([
                'success' => true,
                'message' => 'Presença atualizada com sucesso',
                'usuario' => $usuario,
                'status' => $status,
                'horario' => $horario_leitura,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            $stmt->close();
            retornarErro('Erro ao atualizar presença: ' . $conn->error, 500);
        }
    } else {
        // Inserir nova presença
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO presencas_culto 
            (id_usuario, data, horario_confirmacao, tipo_confirmacao, status, falta, presente, atrasado, observacoes, data_cadastro) 
            VALUES (?, ?, ?, 'facial', ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isssiiis", $user_id, $data_hoje, $horario_leitura, $status, $falta, $presente, $atrasado, $observacoes);
        
        if ($stmt->execute()) {
            $stmt->close();
            logLeituraFacial("Nova presença inserida para usuário $user_id: $status");
            
            echo json_encode([
                'success' => true,
                'message' => 'Presença registrada com sucesso',
                'usuario' => $usuario,
                'status' => $status,
                'horario' => $horario_leitura,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            $stmt->close();
            retornarErro('Erro ao inserir presença: ' . $conn->error, 500);
        }
    }
    
} catch (Exception $e) {
    logLeituraFacial("ERRO: " . $e->getMessage());
    retornarErro('Erro interno: ' . $e->getMessage(), 500);
}
?>
