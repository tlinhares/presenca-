<?php
include_once(__DIR__ . '/../../conexao.php');
include_once(__DIR__ . '/../../../auth/verifica_sessao.php');
header('Content-Type: application/json');



$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$usuario_id = !empty($_GET['usuario_id']) ? intval($_GET['usuario_id']) : null;

// Buscar todas as datas de culto no período
$sql_datas = "SELECT DISTINCT data FROM presencas_culto WHERE data BETWEEN ? AND ? ORDER BY data";
$stmt_datas = $conn->prepare($sql_datas);
$stmt_datas->bind_param("ss", $data_inicio, $data_fim);
$stmt_datas->execute();
$result_datas = $stmt_datas->get_result();
$datas_culto = [];
while ($row = $result_datas->fetch_assoc()) {
    $datas_culto[] = $row['data'];
}

// Buscar usuários com culto = 1
$sql_usuarios = "SELECT id, nome FROM usuarios WHERE culto = 1 AND ativo = 1";
if ($usuario_id) {
    $sql_usuarios .= " AND id = ?";
    $stmt_usuarios = $conn->prepare($sql_usuarios);
    $stmt_usuarios->bind_param("i", $usuario_id);
} else {
    $stmt_usuarios = $conn->prepare($sql_usuarios);
}
$stmt_usuarios->execute();
$result_usuarios = $stmt_usuarios->get_result();

$faltas = [];
$total_faltas = 0;
$faltas_justificadas = 0;

while ($usuario = $result_usuarios->fetch_assoc()) {
    foreach ($datas_culto as $data) {
        $sql_presenca = "SELECT id FROM presencas_culto WHERE id_usuario = ? AND data = ?";
        $stmt_presenca = $conn->prepare($sql_presenca);
        $stmt_presenca->bind_param("is", $usuario['id'], $data);
        $stmt_presenca->execute();
        $result_presenca = $stmt_presenca->get_result();
        
        if ($result_presenca->num_rows == 0) {
            // Verificar se tem justificativa
            $sql_just = "SELECT id, motivo, status FROM justificativas_culto WHERE id_usuario = ? AND data_falta = ?";
            $stmt_just = $conn->prepare($sql_just);
            $stmt_just->bind_param("is", $usuario['id'], $data);
            $stmt_just->execute();
            $result_just = $stmt_just->get_result();
            $justificativa = $result_just->fetch_assoc();
            
            $faltas[] = [
                'data' => $data,
                'nome_usuario' => $usuario['nome'],
                'id_usuario' => $usuario['id'],
                'justificada' => $justificativa !== null,
                'motivo' => $justificativa['motivo'] ?? null,
                'status_justificativa' => $justificativa['status'] ?? null
            ];
            
            $total_faltas++;
            if ($justificativa) $faltas_justificadas++;
        }
    }
}

echo json_encode([
    'status' => 'ok',
    'total_faltas' => $total_faltas,
    'faltas_justificadas' => $faltas_justificadas,
    'faltas' => $faltas
]);
?>
