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
    
    // Receber dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    $ids = $input['ids'] ?? [];
    $decisao = $input['decisao'] ?? '';
    $observacoes_admin = trim($input['observacoes_admin'] ?? '');
    $admin_id = $_SESSION['usuario_id'];
    
    // Validações
    if (empty($ids) || !is_array($ids)) {
        throw new Exception('Nenhuma justificativa selecionada');
    }
    
    if (!in_array($decisao, ['aprovada', 'rejeitada'])) {
        throw new Exception('Decisão inválida');
    }
    
    // Sanitizar IDs
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function($id) { return $id > 0; });
    
    if (empty($ids)) {
        throw new Exception('IDs inválidos');
    }
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        $processadas = 0;
        $erros = [];
        
        // Buscar nome do administrador uma vez
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
        
        // Array para armazenar notificações a serem enviadas (com delay)
        $notificacoes_pendentes = [];
        
        foreach ($ids as $index => $justificativa_id) {
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
                $erros[] = "ID $justificativa_id: Justificativa não encontrada";
                continue;
            }
            
            $justificativa = $resultado->fetch_assoc();
            
            if ($justificativa['status'] !== 'pendente') {
                $erros[] = "ID $justificativa_id: Já foi processada";
                continue;
            }
            
            // Armazenar dados para notificação (será enviada após commit)
            $notificacoes_pendentes[] = [
                'usuario_id' => $justificativa['id_usuario'],
                'dados' => [
                    'data_falta' => date('d/m/Y', strtotime($justificativa['data_falta'])),
                    'motivo' => $justificativa['motivo'] ?? 'Não informado',
                    'observacoes' => $justificativa['observacoes'] ?? '',
                    'observacoes_admin' => $observacoes_admin,
                    'admin_nome' => $admin_nome
                ]
            ];
            
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
                $erros[] = "ID $justificativa_id: Erro ao atualizar";
                continue;
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
                            observacoes = 'Presença aprovada via justificativa (lote)'
                        WHERE id_usuario = ? AND data = ?
                    ");
                    $stmt->bind_param("is", $justificativa['id_usuario'], $justificativa['data_falta']);
                } else {
                    // Inserir nova presença
                    $stmt = $conn->prepare("
                        INSERT INTO presencas_culto 
                        (id_usuario, data, horario_confirmacao, tipo_confirmacao, status, observacoes) 
                        VALUES (?, ?, '00:00:00', 'manual', 'presente', 'Presença aprovada via justificativa (lote)')
                    ");
                    $stmt->bind_param("is", $justificativa['id_usuario'], $justificativa['data_falta']);
                }
                
                $stmt->execute();
            }
            
            $processadas++;
        }
        
        // Confirmar transação
        $conn->commit();
        
        // Enviar notificações com delay aleatório para evitar spam
        if (!empty($notificacoes_pendentes)) {
            try {
                require_once __DIR__ . '/../notificacao/enviar_notificacao_justificativa.php';
                require_once __DIR__ . '/../../core/services/WhatsAppService.php';
                
                foreach ($notificacoes_pendentes as $index => $notificacao) {
                    // Delay aleatório entre envios (exceto primeiro)
                    if ($index > 0) {
                        $delay = WhatsAppService::calcularDelayAleatorio(5, 15);
                        sleep($delay);
                    }
                    
                    enviarNotificacaoJustificativa(
                        $notificacao['usuario_id'],
                        $decisao,
                        $notificacao['dados'],
                        $conn
                    );
                }
            } catch (Exception $e) {
                // Log do erro mas não interrompe o processo (notificação é opcional)
                error_log("Erro ao enviar notificações de justificativas em lote: " . $e->getMessage());
            }
        }
        
        $decisaoTexto = $decisao === 'aprovada' ? 'aprovada(s)' : 'rejeitada(s)';
        
        $response = [
            'status' => 'ok',
            'mensagem' => "$processadas justificativa(s) $decisaoTexto com sucesso!",
            'processadas' => $processadas,
            'total' => count($ids)
        ];
        
        if (!empty($erros)) {
            $response['avisos'] = $erros;
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Erro em decidir_justificativas_lote.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao processar decisão: ' . $e->getMessage()]);
}

$conn->close();
?>


