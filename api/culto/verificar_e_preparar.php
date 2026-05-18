<?php
header('Content-Type: application/json');
include_once(__DIR__ . '/../../api/conexao.php');

$data = $_GET['data'] ?? date('Y-m-d');
$inseridos = 0;
$total_usuarios = 0;
$total_sync = 0;
$total_sync_depois = 0;
$logs = [];

// LOG: início do processo
$logs[] = "Iniciando verificação e preparação para culto - data: $data";

// Buscar dispositivos ativos do tipo culto
$sql_dispositivos = "SELECT id, nome FROM dispositivos_faciais WHERE ativo = 1 AND tipo_dispositivo = 'culto'";
$result_dispositivos = $conn->query($sql_dispositivos);
$dispositivos_ativos = [];

if ($result_dispositivos && $result_dispositivos->num_rows > 0) {
    while ($row = $result_dispositivos->fetch_assoc()) {
        $dispositivos_ativos[] = $row;
    }
    $logs[] = "Encontrados " . count($dispositivos_ativos) . " dispositivos ativos do tipo culto";
} else {
    $logs[] = "ERRO: Nenhum dispositivo ativo do tipo culto encontrado";
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Nenhum dispositivo ativo do tipo culto encontrado',
        'data' => $data,
        'logs' => $logs
    ]);
    exit;
}

// 1. BUSCAR USUÁRIOS ATIVOS (todos os usuários ativos para culto)
$sql_usuarios = "
    SELECT id, nome
    FROM usuarios
    WHERE ativo = 1
";
$result_usuarios = $conn->query($sql_usuarios);

while ($row = $result_usuarios->fetch_assoc()) {
    $id_usuario = $row['id'];
    $total_usuarios++;

    // Para cada dispositivo ativo, verificar se já está na tabela facial_sync_culto
    foreach ($dispositivos_ativos as $dispositivo) {
        $id_dispositivo = $dispositivo['id'];
        
        $check = $conn->prepare("SELECT id FROM facial_sync_culto WHERE id_usuario = ? AND id_dispositivo = ? AND data = ?");
        $check->bind_param("iis", $id_usuario, $id_dispositivo, $data);
        $check->execute();
        $check->store_result();

        if ($check->num_rows == 0) {
            $ins = $conn->prepare("INSERT INTO facial_sync_culto (id_usuario, id_dispositivo, data, status, origem) VALUES (?, ?, ?, 'pendente', 'culto')");
            $ins->bind_param("iis", $id_usuario, $id_dispositivo, $data);
            $ins->execute();
            $inseridos++;
            $logs[] = "Inserido usuário ID $id_usuario no dispositivo {$dispositivo['nome']} (ID: $id_dispositivo) para culto.";
        }

        $check->close();
    }
}

// 2. CONTAR TOTAL DE REGISTROS NA TABELA facial_sync_culto
$count_sync = $conn->query("SELECT COUNT(*) as total FROM facial_sync_culto WHERE data = '$data'");
$total_sync = $count_sync->fetch_assoc()['total'];

$total_sync_depois = $total_sync;

// LOG: resumo final
$logs[] = "Processo concluído. Usuários processados: $total_usuarios, Registros inseridos: $inseridos, Total na tabela: $total_sync_depois";

echo json_encode([
    'status' => 'ok',
    'data' => $data,
    'usuarios_processados' => $total_usuarios,
    'registros_inseridos' => $inseridos,
    'total_sync' => $total_sync_depois,
    'dispositivos_ativos' => count($dispositivos_ativos),
    'logs' => $logs
]);

$conn->close();
?>
