<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

try {
    require_once '../../api/conexao.php';
    
    if (!isset($conn) || !$conn) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }
    
    $conn->set_charset("utf8");
    $usuario_id = $_SESSION['usuario_id'];
    
    $datas_falta = $_POST['datas_falta'] ?? [];
    $motivo = trim($_POST['motivo'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    if (empty($datas_falta) || !is_array($datas_falta)) {
        throw new Exception('Nenhuma data selecionada');
    }
    
    if (empty($motivo)) {
        throw new Exception('Motivo é obrigatório');
    }
    
    $conn->begin_transaction();
    
    try {
        $sucessos = 0;
        $erros = 0;
        $erro_mensagens = [];
        
        foreach ($datas_falta as $data_falta) {
            // Verificar se já existe justificativa
            $stmt = $conn->prepare("SELECT id FROM justificativas_culto WHERE id_usuario = ? AND data_falta = ?");
            $stmt->bind_param("is", $usuario_id, $data_falta);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                $erros++;
                $erro_mensagens[] = "Já existe justificativa para {$data_falta}";
                continue;
            }
            
            // Verificar se realmente houve falta nesta data (usando a mesma lógica da API individual)
            $stmt = $conn->prepare("SELECT status FROM presencas_culto WHERE id_usuario = ? AND data = ?");
            $stmt->bind_param("is", $usuario_id, $data_falta);
            $stmt->execute();
            $resultado = $stmt->get_result();
            
            if ($resultado->num_rows === 0) {
                // Verificar se há presenças de outros usuários nesta data (para gerar falta automática)
                $stmt_check = $conn->prepare("SELECT COUNT(*) as total FROM presencas_culto WHERE data = ?");
                $stmt_check->bind_param("s", $data_falta);
                $stmt_check->execute();
                $check_result = $stmt_check->get_result();
                $check_data = $check_result->fetch_assoc();
                
                if ($check_data['total'] > 0) {
                    // Criar registro de falta automaticamente
                    $stmt_insert = $conn->prepare("INSERT INTO presencas_culto (id_usuario, data, horario_confirmacao, status, tipo_confirmacao) VALUES (?, ?, '00:00:00', 'falta', 'manual')");
                    $stmt_insert->bind_param("is", $usuario_id, $data_falta);
                    $stmt_insert->execute();
                } else {
                    $erros++;
                    $erro_mensagens[] = "Não há registro de presença para {$data_falta}";
                    continue;
                }
            } else {
                $presenca = $resultado->fetch_assoc();
                if ($presenca['status'] !== 'falta') {
                    $erros++;
                    $erro_mensagens[] = "Só é possível justificar faltas para {$data_falta}";
                    continue;
                }
            }
            
            // Inserir justificativa
            $stmt = $conn->prepare("
                INSERT INTO justificativas_culto (id_usuario, data_falta, motivo, observacoes, status) 
                VALUES (?, ?, ?, ?, 'pendente')
            ");
            $stmt->bind_param("isss", $usuario_id, $data_falta, $motivo, $observacoes);
            
            if ($stmt->execute()) {
                $sucessos++;
            } else {
                $erros++;
                $erro_mensagens[] = "Erro ao salvar justificativa para {$data_falta}";
            }
        }
        
        $conn->commit();
        
        $mensagem = "Justificativas enviadas: {$sucessos} sucesso(s)";
        if ($erros > 0) {
            $mensagem .= ", {$erros} erro(s)";
            if (!empty($erro_mensagens)) {
                $mensagem .= "\n\nDetalhes dos erros:\n" . implode("\n", $erro_mensagens);
            }
        }
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => $mensagem,
            'sucessos' => $sucessos,
            'erros' => $erros,
            'erro_mensagens' => $erro_mensagens
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Erro em enviar_justificativa_lote.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
?>
