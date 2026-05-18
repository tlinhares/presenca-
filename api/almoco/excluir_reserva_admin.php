<?php
/**
 * API para excluir reservas administrativamente (sem restrições de horário)
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

$reserva_id = (int)($data['id'] ?? $_POST['id'] ?? 0);
$tipo = $data['tipo'] ?? $_POST['tipo'] ?? ''; // 'propria' ou 'dependente'

if ($reserva_id <= 0 || empty($tipo)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos']);
    exit;
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
    
    $usuario_id_notificacao = null;
    $dados_notificacao = null;
    
    if ($tipo === 'propria') {
        // Buscar dados da reserva antes de excluir (para log e notificação)
        $stmt = $conn->prepare("SELECT id_usuario, data, valor_refeicao FROM reservas_almoco WHERE id = ?");
        $stmt->bind_param("i", $reserva_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $conn->rollback();
            echo json_encode(['status' => 'erro', 'mensagem' => 'Reserva não encontrada']);
            exit;
        }
        
        $reserva = $result->fetch_assoc();
        $usuario_id_notificacao = $reserva['id_usuario'];
        $stmt->close();
        
        // Preparar dados para notificação
        $dados_notificacao = [
            'data' => date('d/m/Y', strtotime($reserva['data'])),
            'horario' => date('H:i'),
            'tipo_reserva' => 'propria',
            'excluida_por_admin' => true,
            'admin_nome' => $admin_nome
        ];
        
        // Excluir reserva própria
        $stmt = $conn->prepare("DELETE FROM reservas_almoco WHERE id = ?");
        $stmt->bind_param("i", $reserva_id);
        
        if (!$stmt->execute()) {
            $conn->rollback();
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao excluir reserva: ' . $stmt->error]);
            $stmt->close();
            exit;
        }
        $stmt->close();
        
    } elseif ($tipo === 'dependente') {
        // Buscar dados da reserva antes de excluir (para log e notificação)
        $stmt = $conn->prepare("SELECT ra.id_usuario, ra.data, ra.valor_refeicao, d.nome as dependente_nome 
                               FROM reservas_adicionais ra 
                               LEFT JOIN dependentes d ON ra.id_dependente = d.id 
                               WHERE ra.id = ?");
        $stmt->bind_param("i", $reserva_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $conn->rollback();
            echo json_encode(['status' => 'erro', 'mensagem' => 'Reserva não encontrada']);
            exit;
        }
        
        $reserva = $result->fetch_assoc();
        $usuario_id_notificacao = $reserva['id_usuario'];
        $stmt->close();
        
        // Preparar dados para notificação
        $dados_notificacao = [
            'data' => date('d/m/Y', strtotime($reserva['data'])),
            'horario' => date('H:i'),
            'tipo_reserva' => 'adicional',
            'dependente_nome' => $reserva['dependente_nome'] ?? null,
            'excluida_por_admin' => true,
            'admin_nome' => $admin_nome
        ];
        
        // Excluir reserva de dependente
        $stmt = $conn->prepare("DELETE FROM reservas_adicionais WHERE id = ?");
        $stmt->bind_param("i", $reserva_id);
        
        if (!$stmt->execute()) {
            $conn->rollback();
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao excluir reserva: ' . $stmt->error]);
            $stmt->close();
            exit;
        }
        $stmt->close();
        
    } else {
        $conn->rollback();
        echo json_encode(['status' => 'erro', 'mensagem' => 'Tipo de reserva inválido']);
        exit;
    }
    
    $conn->commit();
    
    // Enviar notificação ao usuário se habilitada
    if ($usuario_id_notificacao && $dados_notificacao) {
        try {
            require_once __DIR__ . '/../notificacao/enviar_notificacao_reserva.php';
            enviarNotificacaoReserva($usuario_id_notificacao, 'cancelada', $dados_notificacao, $conn);
        } catch (Exception $e) {
            // Log do erro mas não interrompe o processo (notificação é opcional)
            error_log("Erro ao enviar notificação de cancelamento de reserva por admin: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Reserva excluída com sucesso'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao excluir reserva: ' . $e->getMessage()
    ]);
}

$conn->close();
?>

