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
    $usuario_id = $_GET['usuario_id'] ?? null;
    
    if (!$usuario_id) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID do usuário não fornecido']);
        exit;
    }
    
    // Buscar dependentes do usuário
    $sql = "SELECT id, nome, parentesco, nascimento, foto_base64, 
                   DATE_FORMAT(STR_TO_DATE(nascimento, '%Y-%m-%d'), '%d/%m/%Y') as data_nascimento
            FROM dependentes 
            WHERE id_usuario = ? AND ativo = 1
            ORDER BY nome";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $dependentes = [];
    while ($row = $result->fetch_assoc()) {
        $dependentes[] = [
            'id' => $row['id'],
            'nome' => $row['nome'],
            'parentesco' => $row['parentesco'],
            'data_nascimento' => $row['data_nascimento'],
            'foto_base64' => $row['foto_base64']
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'dados' => $dependentes
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}

    ?>
