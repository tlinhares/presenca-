<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

// ╔════════════════════════════════════════════════════════════════╗
// ║  Acesso: Mesma permissão de culto_presencas                   ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('culto_presencas');

try {
    require_once '../../api/conexao.php';
    
    if (!isset($conn) || !$conn) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }
    
    $conn->set_charset("utf8");
    
    $usuario_id = intval($_POST['usuario_id'] ?? 0);
    $status_atual = $_POST['status_atual'] ?? '';
    $data_hoje = $_POST['data'] ?? date('Y-m-d');
    $horario_agora = date('H:i:s');
    $admin_id = $_SESSION['usuario_id'];
    
    if ($usuario_id <= 0) {
        throw new Exception('ID do usuário inválido');
    }
    
    // Validar se a data não é futura
    $data_hoje_real = date('Y-m-d');
    if ($data_hoje > $data_hoje_real) {
        throw new Exception('Não é possível alterar presenças de datas futuras');
    }
    
    // Verificar se há justificativa para esta data
    $stmt = $conn->prepare("SELECT id FROM justificativas_culto WHERE id_usuario = ? AND data_falta = ?");
    $stmt->bind_param("is", $usuario_id, $data_hoje);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows > 0) {
        throw new Exception('Não é possível alterar presença: existe uma justificativa para esta data');
    }
    
    // Determinar próximo status baseado no status atual
    $proximo_status = '';
    $tipo_confirmacao = 'manual';
    
    switch ($status_atual) {
        case 'sem-presenca':
        case 'falta':
            $proximo_status = 'presente';
            break;
        case 'presente':
            $proximo_status = 'atrasado';
            break;
        case 'atrasado':
            $proximo_status = 'sem-presenca';
            break;
        default:
            $proximo_status = 'presente';
    }
    
    // Se está removendo presença, deletar registro
    if ($proximo_status === 'sem-presenca') {
        $stmt = $conn->prepare("DELETE FROM presencas_culto WHERE id_usuario = ? AND data = ?");
        $stmt->bind_param("is", $usuario_id, $data_hoje);
        
        if ($stmt->execute()) {
            // Verificar se existem outras presenças no dia para determinar o status final
            $stmt_check = $conn->prepare("SELECT COUNT(*) as total FROM presencas_culto WHERE data = ?");
            $stmt_check->bind_param("s", $data_hoje);
            $stmt_check->execute();
            $check_result = $stmt_check->get_result();
            $check_row = $check_result->fetch_assoc();
            $tem_outras_presencas = $check_row['total'] > 0;
            
            // Se existem outras presenças, o usuário deve ficar como "falta"
            if ($tem_outras_presencas) {
                echo json_encode([
                    'status' => 'ok',
                    'mensagem' => 'Presença removida - marcado como falta',
                    'novo_status' => 'falta',
                    'novo_status_class' => 'status-falta',
                    'novo_badge_class' => 'bg-danger',
                    'novo_icon' => '<i class="bi bi-x-circle-fill"></i>',
                    'novo_texto' => 'Falta'
                ]);
            } else {
                echo json_encode([
                    'status' => 'ok',
                    'mensagem' => 'Presença removida com sucesso',
                    'novo_status' => 'sem-presenca',
                    'novo_status_class' => 'status-sem-presenca',
                    'novo_badge_class' => 'bg-secondary',
                    'novo_icon' => '<i class="bi bi-circle"></i>',
                    'novo_texto' => 'Sem Presença'
                ]);
            }
        } else {
            throw new Exception('Erro ao remover presença: ' . $stmt->error);
        }
    } else {
        // Inserir ou atualizar presença
        $stmt = $conn->prepare("
            INSERT INTO presencas_culto (id_usuario, data, horario_confirmacao, tipo_confirmacao, status, id_admin_manual) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                horario_confirmacao = VALUES(horario_confirmacao),
                tipo_confirmacao = VALUES(tipo_confirmacao),
                status = VALUES(status),
                id_admin_manual = VALUES(id_admin_manual)
        ");
        $stmt->bind_param("issssi", $usuario_id, $data_hoje, $horario_agora, $tipo_confirmacao, $proximo_status, $admin_id);
        
        if ($stmt->execute()) {
            $mensagem = '';
            $status_class = '';
            $badge_class = '';
            $icon = '';
            $texto = '';
            
            switch ($proximo_status) {
                case 'presente':
                    $mensagem = 'Presença confirmada com sucesso';
                    $status_class = 'status-presente';
                    $badge_class = 'bg-success';
                    $icon = '<i class="bi bi-check-circle-fill"></i>';
                    $texto = 'Presente';
                    break;
                case 'atrasado':
                    $mensagem = 'Presença marcada como atrasada';
                    $status_class = 'status-atrasado';
                    $badge_class = 'bg-primary';
                    $icon = '<i class="bi bi-clock-fill"></i>';
                    $texto = 'Atrasado';
                    break;
            }
            
            // Formatar horário para exibição (HH:MM)
            $horario_formatado = substr($horario_agora, 0, 5);
            
            echo json_encode([
                'status' => 'ok',
                'mensagem' => $mensagem,
                'novo_status' => $proximo_status,
                'novo_status_class' => $status_class,
                'novo_badge_class' => $badge_class,
                'novo_icon' => $icon,
                'novo_texto' => $texto,
                'horario' => $horario_formatado
            ]);
        } else {
            throw new Exception('Erro ao salvar presença: ' . $stmt->error);
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao alterar presença: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
