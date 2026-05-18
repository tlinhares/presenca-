<?php
/**
 * API para verificar se usuário tem reserva ativa e sincronizar foto no facial
 */
header('Content-Type: application/json; charset=UTF-8');
include_once __DIR__ . '/../conexao.php';

$response = ['status' => 'erro', 'mensagem' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioId = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);
    $data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING) ?: date('Y-m-d');

    if ($usuarioId) {
        try {
            // Verificar se usuário tem reserva para hoje
            $sqlReserva = "SELECT id FROM reservas_almoco WHERE id_usuario = ? AND data = ?";
            $stmtReserva = $conn->prepare($sqlReserva);
            $stmtReserva->bind_param("is", $usuarioId, $data);
            $stmtReserva->execute();
            $stmtReserva->store_result();
            
            $temReserva = ($stmtReserva->num_rows > 0);
            $stmtReserva->close();
            
            if ($temReserva) {
                // Usuário tem reserva - verificar se já existe sincronização
                $sqlSync = "SELECT id, status FROM facial_sync WHERE id_usuario = ? AND data = ?";
                $stmtSync = $conn->prepare($sqlSync);
                $stmtSync->bind_param("is", $usuarioId, $data);
                $stmtSync->execute();
                $stmtSync->store_result();
                
                if ($stmtSync->num_rows > 0) {
                    // Já existe sincronização - atualizar para pendente
                    $stmtSync->bind_result($syncId, $status);
                    $stmtSync->fetch();
                    $stmtSync->close();
                    
                    $sqlUpdate = "UPDATE facial_sync SET status = 'pendente', detalhes = 'Foto atualizada em " . date('Y-m-d H:i:s') . "' WHERE id = ?";
                    $stmtUpdate = $conn->prepare($sqlUpdate);
                    $stmtUpdate->bind_param("i", $syncId);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                    
                    $response['status'] = 'sucesso';
                    $response['mensagem'] = 'Sincronização facial reagendada com sucesso';
                    $response['tem_reserva'] = true;
                    $response['sincronizacao_id'] = $syncId;
                } else {
                    // Criar nova sincronização
                    $sqlInsert = "INSERT INTO facial_sync (id_usuario, data, status, detalhes, horario_cadastro) VALUES (?, ?, 'pendente', 'Foto atualizada em " . date('Y-m-d H:i:s') . "', NOW())";
                    $stmtInsert = $conn->prepare($sqlInsert);
                    $stmtInsert->bind_param("is", $usuarioId, $data);
                    $stmtInsert->execute();
                    $syncId = $stmtInsert->insert_id;
                    $stmtInsert->close();
                    
                    $response['status'] = 'sucesso';
                    $response['mensagem'] = 'Sincronização facial agendada com sucesso';
                    $response['tem_reserva'] = true;
                    $response['sincronizacao_id'] = $syncId;
                }
            } else {
                // Usuário não tem reserva - não precisa sincronizar
                $response['status'] = 'sucesso';
                $response['mensagem'] = 'Usuário não possui reserva para hoje - sincronização não necessária';
                $response['tem_reserva'] = false;
            }
        } catch (Exception $e) {
            $response['mensagem'] = 'Erro ao verificar reserva: ' . $e->getMessage();
        }
    } else {
        $response['mensagem'] = 'ID do usuário inválido';
    }
} else {
    $response['mensagem'] = 'Método de requisição inválido';
}

echo json_encode($response);
?>
