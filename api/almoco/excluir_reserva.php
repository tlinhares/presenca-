<?php
header('Content-Type: application/json');
require_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

session_start();
$id_usuario = $_SESSION['id_usuario'] ?? '';

if (empty($id_usuario)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não logado']);
    exit;
}

$id_reserva = $_POST['id'] ?? '';
$tipo = $_POST['tipo'] ?? ''; // 'principal' ou 'dependente'

if (empty($id_reserva) || empty($tipo)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados não fornecidos']);
    exit;
}

try {
    // Buscar configurações do sistema
    $config = [];
    $result = $conn->query("SELECT chave, valor FROM configuracoes");
    while ($row = $result->fetch_assoc()) {
        $config[$row['chave']] = $row['valor'];
    }
    
    $hora_limite = $config['hora_limite'] ?? '10:00';
    
    if ($tipo === 'principal') {
        // Verificar se a reserva pertence ao usuário
        $stmt = $conn->prepare("SELECT data FROM reservas_almoco WHERE id = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $id_reserva, $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Reserva não encontrada']);
            exit;
        }
        
        $reserva = $result->fetch_assoc();
        $stmt->close();
        
        // Verificar se pode excluir (horário limite para hoje)
        $dataReserva = new DateTime($reserva['data']);
        $hoje = new DateTime();
        
        if ($dataReserva->format('Y-m-d') === $hoje->format('Y-m-d')) {
            $horaAtual = $hoje->format('H:i');
            if ($horaAtual > $hora_limite) {
                echo json_encode(['status' => 'erro', 'mensagem' => 'Horário limite para exclusão ultrapassado']);
                exit;
            }
        }
        
        // Excluir reserva principal
        $stmt = $conn->prepare("DELETE FROM reservas_almoco WHERE id = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $id_reserva, $id_usuario);
        
        if ($stmt->execute()) {
            // Enviar notificação se habilitada
            require_once __DIR__ . '/../notificacao/enviar_notificacao_reserva.php';
            $horario_atual = date('H:i');
            $dados_notificacao = [
                'data' => date('d/m/Y', strtotime($reserva['data'])),
                'horario' => $horario_atual,
                'tipo_reserva' => 'própria'
            ];
            enviarNotificacaoReserva($id_usuario, 'cancelada', $dados_notificacao, $conn);
            
            echo json_encode(['status' => 'ok', 'mensagem' => 'Reserva excluída com sucesso']);
        } else {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao excluir reserva: ' . $stmt->error]);
        }
        $stmt->close();
        
    } else if ($tipo === 'dependente') {
        // Verificar se a reserva do dependente pertence ao usuário
        $stmt = $conn->prepare("SELECT ra.data FROM reservas_adicionais ra 
                               INNER JOIN dependentes d ON ra.id_usuario = d.id 
                               WHERE ra.id = ? AND d.id_usuario = ?");
        $stmt->bind_param("ii", $id_reserva, $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Reserva não encontrada']);
            exit;
        }
        
        $reserva = $result->fetch_assoc();
        $stmt->close();
        
        // Verificar se pode excluir (horário limite para hoje)
        $dataReserva = new DateTime($reserva['data']);
        $hoje = new DateTime();
        
        if ($dataReserva->format('Y-m-d') === $hoje->format('Y-m-d')) {
            $horaAtual = $hoje->format('H:i');
            if ($horaAtual > $hora_limite) {
                echo json_encode(['status' => 'erro', 'mensagem' => 'Horário limite para exclusão ultrapassado']);
                exit;
            }
        }
        
        // Buscar nome do dependente antes de excluir
        $stmt_dep = $conn->prepare("SELECT d.nome FROM reservas_adicionais ra 
                                   INNER JOIN dependentes d ON ra.id_dependente = d.id 
                                   WHERE ra.id = ? AND ra.id_usuario = ?");
        $stmt_dep->bind_param("ii", $id_reserva, $id_usuario);
        $stmt_dep->execute();
        $result_dep = $stmt_dep->get_result();
        $dependente_nome = null;
        if ($result_dep->num_rows > 0) {
            $row_dep = $result_dep->fetch_assoc();
            $dependente_nome = $row_dep['nome'];
        }
        $stmt_dep->close();
        
        // Excluir reserva do dependente
        $stmt = $conn->prepare("DELETE FROM reservas_adicionais WHERE id = ?");
        $stmt->bind_param("i", $id_reserva);
        
        if ($stmt->execute()) {
            // Enviar notificação se habilitada
            require_once __DIR__ . '/../notificacao/enviar_notificacao_reserva.php';
            $horario_atual = date('H:i');
            $dados_notificacao = [
                'data' => date('d/m/Y', strtotime($reserva['data'])),
                'horario' => $horario_atual,
                'tipo_reserva' => 'adicional',
                'dependente_nome' => $dependente_nome
            ];
            enviarNotificacaoReserva($id_usuario, 'cancelada', $dados_notificacao, $conn);
            
            echo json_encode(['status' => 'ok', 'mensagem' => 'Reserva excluída com sucesso']);
        } else {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao excluir reserva: ' . $stmt->error]);
        }
        $stmt->close();
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro: ' . $e->getMessage()]);
}

$conn->close();
?>
