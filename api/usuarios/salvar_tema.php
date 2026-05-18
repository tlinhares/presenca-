<?php
/**
 * API: Salvar preferência de tema do usuário
 * Endpoint: POST /api/usuarios/salvar_tema.php
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../auth/verifica_sessao.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Usuário não autenticado'
    ]);
    exit;
}

try {
    $usuario_id = $_SESSION['usuario_id'];
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $tema = isset($data['tema']) ? trim($data['tema']) : 'light';
    
    // Validar tema
    if (!in_array($tema, ['light', 'dark'])) {
        $tema = 'light';
    }
    
    // Verificar se o campo tema existe na tabela
    $check_column = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'tema'");
    if ($check_column->num_rows == 0) {
        // Tentar criar o campo se não existir (pode falhar se não tiver permissão)
        if (!@$conn->query("ALTER TABLE usuarios ADD COLUMN tema VARCHAR(10) DEFAULT 'light' COMMENT 'Tema preferido: light ou dark'")) {
            throw new Exception('Campo tema não existe e não foi possível criá-lo. Execute o script de instalação: api/usuarios/instalar_campo_tema.php');
        }
    }
    
    // Atualizar tema do usuário
    $stmt = $conn->prepare("UPDATE usuarios SET tema = ? WHERE id = ?");
    $stmt->bind_param("si", $tema, $usuario_id);
    
    if ($stmt->execute()) {
        // Atualizar também na sessão
        $_SESSION['usuario_tema'] = $tema;
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Tema salvo com sucesso',
            'tema' => $tema
        ]);
    } else {
        throw new Exception('Erro ao salvar tema: ' . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Erro ao salvar tema: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao salvar tema: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
