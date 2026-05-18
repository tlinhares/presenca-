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
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID do dependente não fornecido']);
        exit;
    }
    
    // Buscar dependente
    $sql = "SELECT id, id_usuario, nome, parentesco, nascimento, foto_base64
            FROM dependentes 
            WHERE id = ? AND ativo = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'status' => 'ok',
            'dados' => [
                'id' => $row['id'],
                'usuario_id' => $row['id_usuario'],
                'nome' => $row['nome'],
                'parentesco' => $row['parentesco'],
                'data_nascimento' => $row['nascimento'],
                'foto_base64' => $row['foto_base64']
            ]
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dependente não encontrado']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}

    ?>
