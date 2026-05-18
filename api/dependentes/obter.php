<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once(__DIR__ . '/../conexao.php');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não logado']);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID do dependente inválido']);
    exit;
}

$id_dependente = (int)$_GET['id'];
$id_usuario = $_SESSION['usuario_id'];

try {
    $stmt = $conn->prepare("
        SELECT id, nome, parentesco, nascimento, cobrar, foto_base64, ativo 
        FROM dependentes 
        WHERE id = ? AND id_usuario = ? AND ativo = 1
    ");
    $stmt->bind_param("ii", $id_dependente, $id_usuario);
    $stmt->execute();
    $stmt->bind_result($id, $nome, $parentesco, $nascimento, $cobrar, $foto_base64, $ativo);
    
    if ($stmt->fetch()) {
        // Calcular idade
        $idade = '';
        if ($nascimento) {
            $nascimento_date = new DateTime($nascimento);
            $hoje = new DateTime();
            $idade = $nascimento_date->diff($hoje)->y;
        }
        
        echo json_encode([
            'status' => 'sucesso',
            'dados' => [
                'id' => $id,
                'nome' => $nome,
                'parentesco' => $parentesco,
                'nascimento' => $nascimento,
                'idade' => $idade,
                'cobrar' => $cobrar,
                'foto_base64' => $foto_base64,
                'ativo' => $ativo
            ]
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dependente não encontrado']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao buscar dependente: ' . $e->getMessage()]);
}

?>
