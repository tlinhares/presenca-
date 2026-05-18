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
    // Buscar entidades (departamentos)
    $sql = "SELECT entidade_id, entidade_nome FROM entidade WHERE ativo = 1 ORDER BY entidade_nome";
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Erro ao buscar entidades: ' . $conn->error);
    }
    
    $entidades = [];
    while ($row = $result->fetch_assoc()) {
        $entidades[] = [
            'id' => intval($row['entidade_id']),
            'nome' => $row['entidade_nome']
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'entidades' => $entidades
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}

$conn->close();
?>
