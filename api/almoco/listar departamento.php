<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

include_once(__DIR__ . '/../conexao.php');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

// Verificar se é admin
$isAdmin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';

if (!$isAdmin) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado']);
    exit;
}

try {
    $where = [];
    $params = [];
    $types = '';

    if (!empty($_GET['data_inicio'])) {
        $where[] = 'rd.data >= ?';
        $params[] = $_GET['data_inicio'];
        $types .= 's';
    }
    if (!empty($_GET['data_fim'])) {
        $where[] = 'rd.data <= ?';
        $params[] = $_GET['data_fim'];
        $types .= 's';
    }
    if (!empty($_GET['entidade_id'])) {
        $where[] = 'rd.entidade_id = ?';
        $params[] = (int)$_GET['entidade_id'];
        $types .= 'i';
    }

    $sql = "SELECT rd.id, rd.data, rd.quantidade, rd.evento_motivo, rd.valor_total, 
                   rd.data_cadastro, e.entidade_nome, u.nome as criado_por
            FROM reservas_departamento rd
            LEFT JOIN entidade e ON rd.entidade_id = e.entidade_id
            LEFT JOIN usuarios u ON rd.criado_por = u.id";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY rd.data DESC, rd.data_cadastro DESC";

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception('Erro ao buscar reservas: ' . $conn->error);
    }
    
    $reservas = [];
    while ($row = $result->fetch_assoc()) {
        // Formatar data
        $data_formatada = '';
        if ($row['data']) {
            $dt = DateTime::createFromFormat('Y-m-d', $row['data']);
            if ($dt) {
                $data_formatada = $dt->format('d/m/Y');
            }
        }
        
        // Formatar data de cadastro
        $data_cadastro_formatada = '';
        if ($row['data_cadastro']) {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $row['data_cadastro']);
            if ($dt) {
                $data_cadastro_formatada = $dt->format('d/m/Y H:i');
            }
        }
        
        $reservas[] = [
            'id' => $row['id'],
            'data' => $data_formatada,
            'data_original' => $row['data'],
            'quantidade' => intval($row['quantidade']),
            'evento_motivo' => $row['evento_motivo'],
            'valor_total' => floatval($row['valor_total']),
            'data_cadastro' => $data_cadastro_formatada,
            'entidade_nome' => $row['entidade_nome'],
            'criado_por' => $row['criado_por']
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'reservas' => $reservas
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}

$conn->close();
?>
