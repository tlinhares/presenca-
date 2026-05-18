<?php
session_start();

// ╔════════════════════════════════════════════════════════════════╗
// ║  Acesso: Mesma permissão de culto_justificativas_admin        ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('culto_justificativas_admin');

header('Content-Type: application/json; charset=UTF-8');

try {
    require_once __DIR__ . '/../../api/conexao.php';
    
    if (!isset($conn) || !$conn) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }
    
    $conn->set_charset("utf8");
    
    $justificativa_id = intval($_POST['justificativa_id'] ?? 0);
    $decisao = $_POST['decisao'] ?? '';
    $observacoes_admin = trim($_POST['observacoes_admin'] ?? '');
    $admin_id = $_SESSION['usuario_id'];
    
    if ($justificativa_id <= 0) {
        throw new Exception('ID da justificativa inválido');
    }
    
    if (!in_array($decisao, ['aprovada', 'rejeitada'])) {
        throw new Exception('Decisão inválida');
    }
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // Buscar dados da justificativa
        $stmt = $conn->prepare("
            SELECT id_usuario, data_falta, status, motivo, observacoes 
            FROM justificativas_culto 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $justificativa_id);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        if ($resultado->num_rows === 0) {
            throw new Exception('Justificativa não encontrada');
        }
        
        $justificativa = $resultado->fetch_assoc();
        
        if ($justificativa['status'] !== 'pendente') {
            throw new Exception('Esta justificativa já foi processada');
        }
        
        // Buscar nome do administrador
        $stmt_admin = $conn->prepare("SELECT nome FROM usuarios WHERE id = ?");
        $stmt_admin->bind_param("i", $admin_id);
        $stmt_admin->execute();
        $result_admin = $stmt_admin->get_result();
        $admin_nome = 'Administrador';
        if ($result_admin->num_rows > 0) {
            $admin_data = $result_admin->fetch_assoc();
            $admin_nome = $admin_data['nome'];
        }
        $stmt_admin->close();
        
        // Atualizar justificativa
        $stmt = $conn->prepare("
            UPDATE justificativas_culto 
            SET status = ?, 
                id_admin_aprovador = ?, 
                data_aprovacao = NOW(), 
                observacoes_admin = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sisi", $decisao, $admin_id, $observacoes_admin, $justificativa_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao atualizar justificativa: ' . $stmt->error);
        }
        
        // Se aprovada, atualizar presença para "presente"
        if ($decisao === 'aprovada') {
            // Verificar se já existe presença para esta data
            $stmt = $conn->prepare("
                SELECT id FROM presencas_culto 
                WHERE id_usuario = ? AND data = ?
            ");
            $stmt->bind_param("is", $justificativa['id_usuario'], $justificativa['data_falta']);
            $stmt->execute();
            $resultado = $stmt->get_result();
            
            if ($resultado->num_rows > 0) {
                // Atualizar presença existente
                $stmt = $conn->prepare("
                    UPDATE presencas_culto 
                    SET status = 'presente', 
                        tipo_confirmacao = 'manual',
                        observacoes = 'Presença aprovada via justificativa'
                    WHERE id_usuario = ? AND data = ?
                ");
                $stmt->bind_param("is", $justificativa['id_usuario'], $justificativa['data_falta']);
            } else {
                // Inserir nova presença
                $stmt = $conn->prepare("
                    INSERT INTO presencas_culto 
                    (id_usuario, data, horario_confirmacao, tipo_confirmacao, status, observacoes) 
                    VALUES (?, ?, '00:00:00', 'manual', 'presente', 'Presença aprovada via justificativa')
                ");
                $stmt->bind_param("is", $justificativa['id_usuario'], $justificativa['data_falta']);
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao atualizar presença: ' . $stmt->error);
            }
        }
        
        // Confirmar transação
        $conn->commit();
        
        // Enviar notificação ao usuário
        try {
            require_once __DIR__ . '/../notificacao/enviar_notificacao_justificativa.php';
            
            $dados_notificacao = [
                'data_falta' => date('d/m/Y', strtotime($justificativa['data_falta'])),
                'motivo' => $justificativa['motivo'] ?? 'Não informado',
                'observacoes' => $justificativa['observacoes'] ?? '',
                'observacoes_admin' => $observacoes_admin,
                'admin_nome' => $admin_nome
            ];
            
            enviarNotificacaoJustificativa($justificativa['id_usuario'], $decisao, $dados_notificacao, $conn);
        } catch (Exception $e) {
            // Log do erro mas não interrompe o processo (notificação é opcional)
            error_log("Erro ao enviar notificação de justificativa: " . $e->getMessage());
        }
        
        $mensagem = $decisao === 'aprovada' ? 
            'Justificativa aprovada com sucesso! A presença foi marcada como presente.' :
            'Justificativa rejeitada com sucesso.';
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => $mensagem,
            'decisao' => $decisao
        ]);
        
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Erro em decidir_justificativa.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao processar decisão: ' . $e->getMessage()]);
}

$conn->close();
?>
