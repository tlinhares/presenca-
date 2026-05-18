<?php
/**
 * API para verificar se dependente tem reserva ativa e sincronizar foto no facial
 */
header('Content-Type: application/json; charset=UTF-8');
include_once __DIR__ . '/../conexao.php';

$response = ['status' => 'erro', 'mensagem' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dependenteId = filter_input(INPUT_POST, 'dependente_id', FILTER_VALIDATE_INT);
    $data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING) ?: date('Y-m-d');

    if ($dependenteId) {
        try {
            // Verificar horário limite
            $sqlConfig = "SELECT valor FROM configuracoes WHERE chave = 'horario_limite_agendamento'";
            $resultConfig = $conn->query($sqlConfig);
            $horarioLimite = '13:00'; // padrão
            if ($rowConfig = $resultConfig->fetch_assoc()) {
                $horarioLimite = $rowConfig['valor'];
            }
            
            $horaAtual = date('H:i');
            if ($horaAtual > $horarioLimite) {
                $response['status'] = 'sucesso';
                $response['mensagem'] = 'Horário limite ultrapassado - sincronização não necessária';
                $response['tem_reserva'] = false;
                $response['horario_limite'] = $horarioLimite;
                $response['hora_atual'] = $horaAtual;
                echo json_encode($response);
                exit;
            }
            
            // Verificar se dependente tem reserva para hoje
            $sqlReserva = "SELECT id FROM reservas_adicionais WHERE id_dependente = ? AND data = ?";
            $stmtReserva = $conn->prepare($sqlReserva);
            $stmtReserva->bind_param("is", $dependenteId, $data);
            $stmtReserva->execute();
            $stmtReserva->store_result();
            
            $temReserva = ($stmtReserva->num_rows > 0);
            $stmtReserva->close();
            
            if ($temReserva) {
                // Dependente tem reserva - verificar se já existe sincronização
                $sqlSync = "SELECT id, status FROM facial_sync WHERE id_usuario = ? AND data = ?";
                $stmtSync = $conn->prepare($sqlSync);
                $stmtSync->bind_param("is", $dependenteId, $data);
                $stmtSync->execute();
                $stmtSync->store_result();
                
                if ($stmtSync->num_rows > 0) {
                    // Já existe sincronização - atualizar para pendente
                    $stmtSync->bind_result($syncId, $status);
                    $stmtSync->fetch();
                    $stmtSync->close();
                    
                    $sqlUpdate = "UPDATE facial_sync SET status = 'pendente', detalhes = 'Foto do dependente atualizada em " . date('Y-m-d H:i:s') . "' WHERE id = ?";
                    $stmtUpdate = $conn->prepare($sqlUpdate);
                    $stmtUpdate->bind_param("i", $syncId);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                    
                    $response['status'] = 'sucesso';
                    $response['mensagem'] = 'Sincronização facial do dependente reagendada com sucesso';
                    $response['tem_reserva'] = true;
                    $response['sincronizacao_id'] = $syncId;
                } else {
                    // Criar nova sincronização
                    $sqlInsert = "INSERT INTO facial_sync (id_usuario, data, status, detalhes, horario_cadastro) VALUES (?, ?, 'pendente', 'Foto do dependente atualizada em " . date('Y-m-d H:i:s') . "', NOW())";
                    $stmtInsert = $conn->prepare($sqlInsert);
                    $stmtInsert->bind_param("is", $dependenteId, $data);
                    $stmtInsert->execute();
                    $syncId = $stmtInsert->insert_id;
                    $stmtInsert->close();
                    
                    $response['status'] = 'sucesso';
                    $response['mensagem'] = 'Sincronização facial do dependente agendada com sucesso';
                    $response['tem_reserva'] = true;
                    $response['sincronizacao_id'] = $syncId;
                }
            } else {
                // Dependente não tem reserva - não precisa sincronizar
                $response['status'] = 'sucesso';
                $response['mensagem'] = 'Dependente não possui reserva para hoje - sincronização não necessária';
                $response['tem_reserva'] = false;
            }
        } catch (Exception $e) {
            $response['mensagem'] = 'Erro ao verificar reserva do dependente: ' . $e->getMessage();
        }
    } else {
        $response['mensagem'] = 'ID do dependente inválido';
    }
} else {
    $response['mensagem'] = 'Método de requisição inválido';
}

echo json_encode($response);
?>
