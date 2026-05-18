<?php
/**
 * API: Buscar preferência de tema do usuário
 * Endpoint: GET /api/usuarios/buscar_tema.php
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../auth/verifica_sessao.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Usuário não autenticado',
        'tema' => 'light' // Tema padrão
    ]);
    exit;
}

try {
    $usuario_id = $_SESSION['usuario_id'];
    
    // Verificar se o campo tema existe na tabela
    $check_column = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'tema'");
    if ($check_column->num_rows == 0) {
        // Tentar criar o campo se não existir (pode falhar se não tiver permissão, mas não é crítico)
        @$conn->query("ALTER TABLE usuarios ADD COLUMN tema VARCHAR(10) DEFAULT 'light' COMMENT 'Tema preferido: light ou dark'");
        $tema = 'light';
    } else {
        // Buscar tema do usuário
        $stmt = $conn->prepare("SELECT COALESCE(tema, 'light') as tema FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $tema = !empty($row['tema']) ? $row['tema'] : 'light';
        } else {
            $tema = 'light';
        }
        
        $stmt->close();
    }
    
    // Validar tema
    if (!in_array($tema, ['light', 'dark'])) {
        $tema = 'light';
    }
    
    // Atualizar sessão
    $_SESSION['usuario_tema'] = $tema;
    
    echo json_encode([
        'status' => 'ok',
        'tema' => $tema
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar tema: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar tema',
        'tema' => 'light' // Tema padrão em caso de erro
    ]);
}

$conn->close();
?>
