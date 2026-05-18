<?php
// api/presenca/sincronizar_individual.php - Sincronizar usuário individual
header('Content-Type: application/json; charset=UTF-8');

// Incluir arquivos necessários
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../autenticacao.php';

// Verifica se o usuário está autenticado
if (!estaLogado()) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Usuário não autenticado'
    ]);
    exit;
}

// Verifica permissões para gerenciar presença
if (!possuiPermissao('gerenciar_presenca') && !possuiPermissao('admin')) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Sem permissão para gerenciar presenças'
    ]);
    exit;
}

// Verificar se recebeu o ID do usuário
if (!isset($_POST['id_usuario']) || empty($_POST['id_usuario'])) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'ID do usuário não informado'
    ]);
    exit;
}

// Capturar dados do usuário
$id_usuario = intval($_POST['id_usuario']);
$data = isset($_POST['data']) ? $_POST['data'] : date('Y-m-d');

// Verificar se usuário existe
$sql_usuario = "SELECT id, nome FROM usuarios WHERE id = ?";
$stmt_usuario = $conn->prepare($sql_usuario);

if (!$stmt_usuario) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao preparar consulta: ' . $conn->error
    ]);
    exit;
}

$stmt_usuario->bind_param("i", $id_usuario);
$stmt_usuario->execute();
$stmt_usuario->store_result();

if ($stmt_usuario->num_rows == 0) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Usuário não encontrado'
    ]);
    $stmt_usuario->close();
    exit;
}

// Obter nome do usuário
$stmt_usuario->bind_result($id, $nome);
$stmt_usuario->fetch();
$stmt_usuario->close();

// Verificar se existe uma reserva para o usuário na data
$sql_reserva = "SELECT id FROM reservas_almoco WHERE id_usuario = ? AND data = ?";
$stmt_reserva = $conn->prepare($sql_reserva);

if (!$stmt_reserva) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao preparar consulta de reserva: ' . $conn->error
    ]);
    exit;
}

$stmt_reserva->bind_param("is", $id_usuario, $data);
$stmt_reserva->execute();
$stmt_reserva->store_result();
$tem_reserva = ($stmt_reserva->num_rows > 0);
$stmt_reserva->close();

if (!$tem_reserva) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => "Usuário $nome não possui reserva para a data $data"
    ]);
    exit;
}

// Verificar se já existe solicitação de sincronização pendente para este usuário e data
$sql_check = "SELECT id, status FROM facial_sync WHERE id_usuario = ? AND data = ?";
$stmt_check = $conn->prepare($sql_check);

if (!$stmt_check) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao preparar consulta: ' . $conn->error
    ]);
    exit;
}

$stmt_check->bind_param("is", $id_usuario, $data);
$stmt_check->execute();
$stmt_check->store_result();

$sincronizacao_existente = false;
$id_sincronizacao = null;
$status_atual = null;

if ($stmt_check->num_rows > 0) {
    $sincronizacao_existente = true;
    $stmt_check->bind_result($id_sincronizacao, $status_atual);
    $stmt_check->fetch();
}

$stmt_check->close();

// Se já existe uma sincronização para este usuário e data
if ($sincronizacao_existente) {
    // Se já está sincronizado, não faz nada
    if ($status_atual == 'sincronizado') {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => "Usuário $nome já está sincronizado para $data",
            'sincronizacao' => [
                'id' => $id_sincronizacao,
                'status' => $status_atual,
                'usuario' => $nome,
                'data' => $data
            ]
        ]);
        exit;
    }
    
    // Se está com falha ou pendente, atualiza para pendente novamente
    $sql_update = "UPDATE facial_sync SET status = 'pendente', detalhes = 'Sincronização manual solicitada em " . date('Y-m-d H:i:s') . "' WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    
    if (!$stmt_update) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao preparar atualização: ' . $conn->error
        ]);
        exit;
    }
    
    $stmt_update->bind_param("i", $id_sincronizacao);
    $stmt_update->execute();
    
    if ($stmt_update->affected_rows <= 0) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Não foi possível atualizar a sincronização'
        ]);
        $stmt_update->close();
        exit;
    }
    
    $stmt_update->close();
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => "Sincronização para $nome reagendada com sucesso",
        'sincronizacao' => [
            'id' => $id_sincronizacao,
            'status' => 'pendente',
            'usuario' => $nome,
            'data' => $data
        ]
    ]);
    exit;
}

// Criar nova solicitação de sincronização
$sql_insert = "INSERT INTO facial_sync (id_usuario, data, status, detalhes, horario_cadastro) VALUES (?, ?, 'pendente', 'Sincronização manual solicitada em " . date('Y-m-d H:i:s') . "', NOW())";
$stmt_insert = $conn->prepare($sql_insert);

if (!$stmt_insert) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao preparar inserção: ' . $conn->error
    ]);
    exit;
}

$stmt_insert->bind_param("is", $id_usuario, $data);
$stmt_insert->execute();

if ($stmt_insert->affected_rows <= 0) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Não foi possível criar a sincronização'
    ]);
    $stmt_insert->close();
    exit;
}

$id_sincronizacao = $stmt_insert->insert_id;
$stmt_insert->close();

// Processar sincronização agora (opcional, depende do design)
// ... código para sincronizar imediatamente, se necessário

// Buscar detalhes da sincronização
$sql_detalhes = "SELECT id, id_usuario, data, status, detalhes, horario_cadastro, horario_sync 
                FROM facial_sync 
                WHERE id = ?";
$stmt_detalhes = $conn->prepare($sql_detalhes);

if (!$stmt_detalhes) {
    echo json_encode([
        'status' => 'ok',
        'mensagem' => "Sincronização para $nome agendada com sucesso, mas não foi possível obter detalhes",
        'sincronizacao' => [
            'id' => $id_sincronizacao,
            'status' => 'pendente',
            'usuario' => $nome,
            'data' => $data
        ]
    ]);
    exit;
}

$stmt_detalhes->bind_param("i", $id_sincronizacao);
$stmt_detalhes->execute();

$stmt_detalhes->bind_result($id_sync, $id_usuario_sync, $data_sync, $status_sync, $detalhes_sync, $horario_cadastro, $horario_sync);
$stmt_detalhes->fetch();
$stmt_detalhes->close();

// Formatar hora de sincronização para exibição
$hora_formatada = null;
if ($horario_sync) {
    $timestamp = strtotime($horario_sync);
    $hora_formatada = date('d/m/Y H:i:s', $timestamp);
}

echo json_encode([
    'status' => 'ok',
    'mensagem' => "Sincronização para $nome agendada com sucesso",
    'sincronizacao' => [
        'id' => $id_sync,
        'id_usuario' => $id_usuario_sync,
        'usuario' => $nome,
        'data' => $data_sync,
        'status' => $status_sync,
        'detalhes' => $detalhes_sync,
        'horario_cadastro' => $horario_cadastro,
        'horario_sync' => $hora_formatada
    ]
]);
?> 