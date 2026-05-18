<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

include_once(__DIR__ . '/../conexao.php');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não logado']);
    exit;
}

$id_usuario = $_SESSION['id_usuario'];

try {
    $stmt = $conn->prepare("
        SELECT u.id, u.nome, u.email, u.telefone, u.foto_base64, u.id_valor, u.entidade_id,
               gv.descricao AS grupo_nome, e.entidade_nome
        FROM usuarios u
        LEFT JOIN grupo_valor gv ON u.id_valor = gv.id
        LEFT JOIN entidade e ON u.entidade_id = e.entidade_id
        WHERE u.id = ?
    ");
    
    if (!$stmt) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro no prepare: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
        echo json_encode([
            'status' => 'ok',
            'usuario' => $usuario
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não encontrado']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro: ' . $e->getMessage()]);
}

$conn->close();
?>
