<?php
/**
 * API para excluir múltiplas reservas administrativamente (sem restrições de horário)
 * Acesso apenas para gestores com permissão
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não logado']);
    exit;
}

// Verificar permissão
if (!MenuPermissaoService::podeAcessar('gestao_reservas')) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

// Ler dados JSON do corpo da requisição
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$reservas = $data['reservas'] ?? [];

if (empty($reservas) || !is_array($reservas)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Nenhuma reserva selecionada']);
    exit;
}

// Validar formato das reservas
foreach ($reservas as $reserva) {
    if (!isset($reserva['id']) || !isset($reserva['tipo'])) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Formato de dados inválido']);
        exit;
    }
}

try {
    // Buscar nome do administrador que está excluindo
    $admin_id = $_SESSION['usuario_id'];
    $stmt = $conn->prepare("SELECT nome FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result_admin = $stmt->get_result();
    $admin_nome = 'Administrador';
    if ($result_admin->num_rows > 0) {
        $admin_data = $result_admin->fetch_assoc();
        $admin_nome = $admin_data['nome'];
    }
    $stmt->close();
    
    $conn->begin_transaction();
    
    $excluidas = 0;
    $falhas = 0;
    $detalhes_falhas = [];
    $notificacoes_enviadas = [];
    
    foreach ($reservas as $reserva) {
        $reserva_id = (int)$reserva['id'];
        $tipo = $reserva['tipo']; // 'propria' ou 'dependente'
        
        if ($reserva_id <= 0 || empty($tipo)) {
            $falhas++;
            $detalhes_falhas[] = "Reserva ID {$reserva_id}: Dados inválidos";
            continue;
        }
        
        $usuario_id_notificacao = null;
        $dados_notificacao = null;
        
        try {
            if ($tipo === 'propria') {
                // Buscar dados da reserva antes de excluir
                $stmt = $conn->prepare("SELECT id_usuario, data, valor_refeicao FROM reservas_almoco WHERE id = ?");
                $stmt->bind_param("i", $reserva_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $falhas++;
                    $detalhes_falhas[] = "Reserva ID {$reserva_id}: Não encontrada";
                    $stmt->close();
                    continue;
                }
                
                $reserva_data = $result->fetch_assoc();
                $usuario_id_notificacao = $reserva_data['id_usuario'];
                $stmt->close();
                
                // Preparar dados para notificação
                $dados_notificacao = [
                    'data' => date('d/m/Y', strtotime($reserva_data['data'])),
                    'horario' => date('H:i'),
                    'tipo_reserva' => 'propria',
                    'excluida_por_admin' => true,
                    'admin_nome' => $admin_nome
                ];
                
                // Excluir reserva própria
                $stmt = $conn->prepare("DELETE FROM reservas_almoco WHERE id = ?");
                $stmt->bind_param("i", $reserva_id);
                
                if (!$stmt->execute()) {
                    $falhas++;
                    $detalhes_falhas[] = "Reserva ID {$reserva_id}: " . $stmt->error;
                    $stmt->close();
                    continue;
                }
                $stmt->close();
                
            } elseif ($tipo === 'dependente') {
                // Buscar dados da reserva antes de excluir
                $stmt = $conn->prepare("SELECT ra.id_usuario, ra.data, ra.valor_refeicao, d.nome as dependente_nome 
                                       FROM reservas_adicionais ra 
                                       LEFT JOIN dependentes d ON ra.id_dependente = d.id 
                                       WHERE ra.id = ?");
                $stmt->bind_param("i", $reserva_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $falhas++;
                    $detalhes_falhas[] = "Reserva ID {$reserva_id}: Não encontrada";
                    $stmt->close();
                    continue;
                }
                
                $reserva_data = $result->fetch_assoc();
                $usuario_id_notificacao = $reserva_data['id_usuario'];
                $stmt->close();
                
                // Preparar dados para notificação
                $dados_notificacao = [
                    'data' => date('d/m/Y', strtotime($reserva_data['data'])),
                    'horario' => date('H:i'),
                    'tipo_reserva' => 'adicional',
                    'dependente_nome' => $reserva_data['dependente_nome'] ?? null,
                    'excluida_por_admin' => true,
                    'admin_nome' => $admin_nome
                ];
                
                // Excluir reserva de dependente
                $stmt = $conn->prepare("DELETE FROM reservas_adicionais WHERE id = ?");
                $stmt->bind_param("i", $reserva_id);
                
                if (!$stmt->execute()) {
                    $falhas++;
                    $detalhes_falhas[] = "Reserva ID {$reserva_id}: " . $stmt->error;
                    $stmt->close();
                    continue;
                }
                $stmt->close();
                
            } else {
                $falhas++;
                $detalhes_falhas[] = "Reserva ID {$reserva_id}: Tipo inválido";
                continue;
            }
            
            $excluidas++;
            
            // Enviar notificação ao usuário se habilitada (não bloquear se falhar)
            if ($usuario_id_notificacao && $dados_notificacao) {
                try {
                    require_once __DIR__ . '/../notificacao/enviar_notificacao_reserva.php';
                    enviarNotificacaoReserva($usuario_id_notificacao, 'cancelada', $dados_notificacao, $conn);
                    $notificacoes_enviadas[] = $usuario_id_notificacao;
                } catch (Exception $e) {
                    // Log do erro mas não interrompe o processo
                    error_log("Erro ao enviar notificação de cancelamento de reserva por admin (ID: {$reserva_id}): " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            $falhas++;
            $detalhes_falhas[] = "Reserva ID {$reserva_id}: " . $e->getMessage();
        }
    }
    
    if ($excluidas > 0) {
        $conn->commit();
    } else {
        $conn->rollback();
    }
    
    $mensagem = "Exclusão concluída: {$excluidas} reserva(s) excluída(s)";
    if ($falhas > 0) {
        $mensagem .= ", {$falhas} falha(s)";
    }
    
    echo json_encode([
        'status' => $falhas === 0 ? 'ok' : ($excluidas > 0 ? 'parcial' : 'erro'),
        'mensagem' => $mensagem,
        'excluidas' => $excluidas,
        'falhas' => $falhas,
        'detalhes_falhas' => $detalhes_falhas
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao excluir reservas: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
