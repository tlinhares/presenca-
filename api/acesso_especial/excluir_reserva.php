<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

include_once(__DIR__ . '/../../utils/acesso_especial.php');
include_once(__DIR__ . '/../conexao.php');

// Verificar se a conexão foi estabelecida
if (!isset($conn) || !$conn) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro de conexão com o banco de dados']);
    exit;
}

// Verificar se a sessão está ativa
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

// Verificar acesso especial
if (!pode_acessar_especial()) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado']);
    exit;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

// Obter dados do formulário
$reserva_id = isset($_POST['reserva_id']) ? (int)$_POST['reserva_id'] : 0;
$tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : '';

// Debug removido - problema identificado e corrigido

// Validações
if (!$reserva_id || !$tipo) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados obrigatórios não fornecidos']);
    exit;
}

try {
    $conn->begin_transaction();

    $tabela = '';
    $detalhes_log = '';

    // Debug removido - problema identificado e corrigido

    $usuario_id_notificacao = null;
    $dados_notificacao = null;
    
    switch ($tipo) {
        case 'propria':
            // Verificar se a reserva existe
            $stmt = $conn->prepare("
                SELECT ra.id, u.id as usuario_id, u.nome, ra.data 
                FROM reservas_almoco ra 
                JOIN usuarios u ON ra.id_usuario = u.id 
                WHERE ra.id = ?
            ");
            $stmt->bind_param("i", $reserva_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Reserva não encontrada');
            }
            
            $reserva = $result->fetch_assoc();
            $usuario_id_notificacao = $reserva['usuario_id'];
            $stmt->close();
            
            // Preparar dados para notificação
            $horario_atual = date('H:i');
            $dados_notificacao = [
                'data' => date('d/m/Y', strtotime($reserva['data'])),
                'horario' => $horario_atual,
                'tipo_reserva' => 'própria'
            ];
            
            // Excluir a reserva
            $stmt = $conn->prepare("DELETE FROM reservas_almoco WHERE id = ?");
            $stmt->bind_param("i", $reserva_id);
            $stmt->execute();
            $stmt->close();
            
            $tabela = 'reservas_almoco';
            $detalhes_log = "Reserva própria especial excluída para {$reserva['nome']} na data {$reserva['data']}";
            break;

        case 'adicional':
        case 'marmitex':
        case 'presencial':
            // Verificar se a reserva existe
            $stmt = $conn->prepare("
                SELECT ra.id, u.id as usuario_id, u.nome, ra.data, ra.tipo, ra.id_dependente
                FROM reservas_adicionais ra 
                JOIN usuarios u ON ra.id_usuario = u.id 
                WHERE ra.id = ?
            ");
            $stmt->bind_param("i", $reserva_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Reserva não encontrada');
            }
            
            $reserva = $result->fetch_assoc();
            $usuario_id_notificacao = $reserva['usuario_id'];
            $stmt->close();
            
            // Buscar nome do dependente para notificação
            $dependente_nome = null;
            if ($reserva['id_dependente']) {
                $stmt_dep = $conn->prepare("SELECT nome FROM dependentes WHERE id = ?");
                $stmt_dep->bind_param("i", $reserva['id_dependente']);
                $stmt_dep->execute();
                $result_dep = $stmt_dep->get_result();
                if ($result_dep->num_rows > 0) {
                    $dep_row = $result_dep->fetch_assoc();
                    $dependente_nome = $dep_row['nome'];
                }
                $stmt_dep->close();
            }
            
            // Preparar dados para notificação
            $horario_atual = date('H:i');
            $dados_notificacao = [
                'data' => date('d/m/Y', strtotime($reserva['data'])),
                'horario' => $horario_atual,
                'tipo_reserva' => 'adicional',
                'dependente_nome' => $dependente_nome
            ];
            
            // Excluir a reserva
            $stmt = $conn->prepare("DELETE FROM reservas_adicionais WHERE id = ?");
            $stmt->bind_param("i", $reserva_id);
            $stmt->execute();
            $stmt->close();
            
            $tabela = 'reservas_adicionais';
            $detalhes_log = "Reserva {$reserva['tipo']} especial excluída para {$reserva['nome']} na data {$reserva['data']}";
            break;

        default:
            throw new Exception("Tipo de reserva inválido: '$tipo'. Tipos aceitos: propria, adicional, marmitex, presencial");
    }

    // Registrar log da ação especial
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $stmt = $conn->prepare("INSERT INTO logs_acesso_especial (usuario_id, acao, detalhes, ip, data_hora) VALUES (?, ?, ?, ?, NOW())");
    $acao = 'exclusao_especial';
    $stmt->bind_param("isss", $_SESSION['usuario_id'], $acao, $detalhes_log, $ip);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    
    // Enviar notificação de cancelamento se habilitada (respeitando configurações do usuário)
    if ($usuario_id_notificacao && $dados_notificacao) {
        try {
            require_once __DIR__ . '/../notificacao/enviar_notificacao_reserva.php';
            enviarNotificacaoReserva($usuario_id_notificacao, 'cancelada', $dados_notificacao, $conn);
        } catch (Exception $e) {
            // Log do erro mas não interrompe o processo (notificação é opcional)
            error_log("Erro ao enviar notificação de cancelamento de reserva especial: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Reserva excluída com sucesso',
        'tabela' => $tabela
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}
?>
