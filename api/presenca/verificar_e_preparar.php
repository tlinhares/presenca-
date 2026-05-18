<?php
header('Content-Type: application/json');

// INCLUIR CONFIGURAÇÃO DE TIMEZONE PRIMEIRO
include_once(__DIR__ . '/../../config/timezone.php');
include_once(__DIR__ . '/../../api/conexao.php');

// Aceitar data via GET (web) ou via argumento de linha de comando (cron)
$data = $_GET['data'] ?? $argv[1] ?? date('Y-m-d');
$inseridos = 0;
$total_usuarios = 0;
$total_sync = 0;
$total_sync_depois = 0;
$logs = [];

// LOG: início do processo
$logs[] = "Iniciando verificação e preparação para data: $data";

// Buscar dispositivos ativos do tipo restaurante
$sql_dispositivos = "SELECT id FROM dispositivos_faciais WHERE ativo = 1 AND tipo_dispositivo = 'restaurante'";
$result_dispositivos = $conn->query($sql_dispositivos);
$dispositivos = [];
while ($row = $result_dispositivos->fetch_assoc()) {
    $dispositivos[] = $row['id'];
}
$logs[] = "Encontrados " . count($dispositivos) . " dispositivos de restaurante";

// 1. BUSCAR USUÁRIOS COM RESERVA (reservas_almoco + usuarios)
$sql_usuarios = "
    SELECT DISTINCT r.id as idreserva, r.id_usuario, u.nome
    FROM reservas_almoco r
    JOIN usuarios u ON r.id_usuario = u.id
    WHERE r.data = ?
";
$stmt_usuarios = $conn->prepare($sql_usuarios);
$stmt_usuarios->bind_param("s", $data);
$stmt_usuarios->execute();
$result_usuarios = $stmt_usuarios->get_result();

while ($row = $result_usuarios->fetch_assoc()) {
    $id_usuario = $row['id_usuario'];
    $idreserva = $row['idreserva'];

    // Criar registro para cada dispositivo
    foreach ($dispositivos as $id_dispositivo) {
        // Verificar se já existe registro para este usuário e dispositivo
        $check = $conn->prepare("SELECT id FROM facial_sync WHERE id_usuario = ? AND data = ? AND id_dispositivo = ?");
        $check->bind_param("isi", $id_usuario, $data, $id_dispositivo);
        $check->execute();
        $check->store_result();

        if ($check->num_rows == 0) {
            $ins = $conn->prepare("INSERT INTO facial_sync (id_usuario, id_dispositivo, data, status, origem, id_reserva) VALUES (?, ?, ?, 'pendente', 'usuario', ?)");
            $ins->bind_param("iisi", $id_usuario, $id_dispositivo, $data, $idreserva);
            $ins->execute();
            $inseridos++;
            $logs[] = "Inserido usuário ID $id_usuario para dispositivo $id_dispositivo na fila facial_sync.";
        }

        $check->close();
    }
}
$stmt_usuarios->close();

// 2. BUSCAR DEPENDENTES COM RESERVA (reservas_adicionais + dependentes)
$sql_dependentes = "
    SELECT DISTINCT r.id as idreserva, r.id_dependente AS id_usuario, d.nome
    FROM reservas_adicionais r
    JOIN dependentes d ON r.id_dependente = d.id
    WHERE r.data = ?
";
$stmt_dep = $conn->prepare($sql_dependentes);
$stmt_dep->bind_param("s", $data);
$stmt_dep->execute();
$result_dep = $stmt_dep->get_result();

while ($row = $result_dep->fetch_assoc()) {
    $id_usuario = $row['id_usuario'];
    $idreserva = $row['idreserva'];

    // Criar registro para cada dispositivo
    foreach ($dispositivos as $id_dispositivo) {
        // Verificar se já existe registro para este dependente e dispositivo
        $check = $conn->prepare("SELECT id FROM facial_sync WHERE id_usuario = ? AND data = ? AND id_dispositivo = ?");
        $check->bind_param("isi", $id_usuario, $data, $id_dispositivo);
        $check->execute();
        $check->store_result();

        if ($check->num_rows == 0) {
            $ins = $conn->prepare("INSERT INTO facial_sync (id_usuario, id_dispositivo, data, status, origem, id_reserva) VALUES (?, ?, ?, 'pendente', 'dependente', ?)");
            $ins->bind_param("iisi", $id_usuario, $id_dispositivo, $data, $idreserva);
            $ins->execute();
            $inseridos++;
            $logs[] = "Inserido dependente ID $id_usuario para dispositivo $id_dispositivo na fila facial_sync.";
        }

        $check->close();
    }
}
$stmt_dep->close();

// Contagens finais
$res_total = $conn->prepare("SELECT COUNT(*) FROM usuarios");
$res_total->execute();
$res_total->bind_result($total_usuarios);
$res_total->fetch();
$res_total->close();

$res_sync = $conn->prepare("SELECT COUNT(*) FROM facial_sync WHERE data = ?");
$res_sync->bind_param("s", $data);
$res_sync->execute();
$res_sync->bind_result($total_sync_depois);
$res_sync->fetch();
$res_sync->close();

// JSON de retorno
echo json_encode([
    'status' => 'ok',
    'data' => $data,
    'inseridos' => $inseridos,
    'total_usuarios' => $total_usuarios,
    'total_sync' => $total_sync,
    'total_sync_depois' => $total_sync_depois,
    'logs' => $logs
]);
?>
