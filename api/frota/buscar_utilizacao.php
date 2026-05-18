<?php
/**
 * API para Buscar Utilização por ID
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../conexao.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$usuario_id = $_SESSION['usuario_id'] ?? 0;

if (!$id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID não informado']);
    exit;
}

try {
    $sql = "SELECT fu.*, 
                   v.placa, v.modelo, v.marca, v.cor,
                   u.nome as usuario_nome,
                   e.entidade_nome,
                   fd.nome as departamento_nome,
                   DATE_FORMAT(fu.data_saida, '%d/%m/%Y %H:%i') as data_saida_formatada,
                   TIMESTAMPDIFF(MINUTE, fu.data_saida, NOW()) as minutos_uso
            FROM frota_utilizacoes fu
            JOIN frota_veiculos v ON fu.id_veiculo = v.id
            JOIN usuarios u ON fu.id_usuario = u.id
            LEFT JOIN entidade e ON fu.id_entidade = e.entidade_id
            LEFT JOIN frota_departamentos fd ON fu.id_departamento = fd.id
            WHERE fu.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Verificar se é o mesmo usuário
        if ($row['id_usuario'] != $usuario_id) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado']);
            exit;
        }
        
        // Verificar se a utilização ainda está em andamento (para devolução)
        $para_devolucao = isset($_GET['para_devolucao']) ? (bool)$_GET['para_devolucao'] : false;
        if ($para_devolucao && $row['status'] !== 'em_andamento') {
            echo json_encode([
                'status' => 'erro', 
                'mensagem' => 'Esta utilização já foi finalizada e não pode ser devolvida novamente.',
                'ja_finalizada' => true
            ]);
            exit;
        }
        
        // Calcular tempo de uso
        $minutos = intval($row['minutos_uso']);
        $horas = floor($minutos / 60);
        $mins = $minutos % 60;
        $tempo_uso = $horas > 0 ? "{$horas}h {$mins}min" : "{$mins}min";
        
        echo json_encode([
            'status' => 'ok',
            'utilizacao' => [
                'id' => intval($row['id']),
                'id_veiculo' => intval($row['id_veiculo']),
                'placa' => $row['placa'],
                'modelo' => $row['modelo'],
                'marca' => $row['marca'],
                'cor' => $row['cor'],
                'entidade' => $row['entidade_nome'],
                'data_saida' => $row['data_saida'],
                'data_saida_formatada' => $row['data_saida_formatada'],
                'km_saida' => intval($row['km_saida']),
                'destino' => $row['destino'],
                'motivo' => $row['motivo'],
                'status' => $row['status'],
                'tempo_uso' => $tempo_uso
            ]
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Utilização não encontrada']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar utilização: ' . $e->getMessage()
    ]);
}
?>



