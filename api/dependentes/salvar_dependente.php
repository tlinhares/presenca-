<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

include_once(__DIR__ . '/../conexao.php');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não logado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

$nome = $_POST['nome_dependente'] ?? '';
$parentesco = $_POST['parentesco_dependente'] ?? '';
$nascimento = $_POST['nascimento_dependente'] ?? '';
$cobrar = isset($_POST['cobrar']) ? (int)$_POST['cobrar'] : 0;
$foto_base64 = $_POST['foto_base64'] ?? '';

try {
    // Validar dados
    if (empty($nome) || empty($parentesco)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Nome e parentesco são obrigatórios']);
        exit;
    }

    // Processar foto base64
    $foto_processada = null;
    if (!empty($foto_base64)) {
        if (strpos($foto_base64, 'data:image/') === 0) {
            $foto_processada = substr($foto_base64, strpos($foto_base64, ',') + 1);
        } else {
            $foto_processada = $foto_base64;
        }
    }

    // Inserir dependente
    $stmt = $conn->prepare("
        INSERT INTO dependentes (id_usuario, nome, nascimento, parentesco, cobrar, foto_base64, ativo) 
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->bind_param("isssis", $_SESSION['usuario_id'], $nome, $nascimento, $parentesco, $cobrar, $foto_processada);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Dependente cadastrado com sucesso',
            'id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao cadastrar dependente']);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao cadastrar dependente: ' . $e->getMessage()
    ]);
}
?>