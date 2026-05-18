<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../conexao.php';
include_once(__DIR__ . '/../../auth/verifica_sessao.php');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

$usuario_id = $_SESSION['usuario_id'];
$notificar_propria = isset($input['notificar_reserva_propria']) ? (int)$input['notificar_reserva_propria'] : 0;
$notificar_adicional = isset($input['notificar_reserva_adicional']) ? (int)$input['notificar_reserva_adicional'] : 0;
$notificar_multipla = isset($input['notificar_reserva_multipla']) ? (int)$input['notificar_reserva_multipla'] : 0;
$notificar_cancelada = isset($input['notificar_reserva_cancelada']) ? (int)$input['notificar_reserva_cancelada'] : 0;
$notificar_lembrete_diario = isset($input['notificar_lembrete_diario']) ? (int)$input['notificar_lembrete_diario'] : 0;

// Verificar se a tabela existe
$tabela_existe = $conn->query("SHOW TABLES LIKE 'notificacoes_usuario'")->num_rows > 0;

if (!$tabela_existe) {
    // Criar a tabela se não existir
    $sql_create = "CREATE TABLE IF NOT EXISTS notificacoes_usuario (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario BIGINT UNSIGNED NOT NULL,
        notificar_reserva_propria TINYINT(1) DEFAULT 0 COMMENT '1=Ativo, 0=Inativo',
        notificar_reserva_adicional TINYINT(1) DEFAULT 0 COMMENT '1=Ativo, 0=Inativo',
        notificar_reserva_multipla TINYINT(1) DEFAULT 0 COMMENT '1=Ativo, 0=Inativo',
        notificar_reserva_cancelada TINYINT(1) DEFAULT 0 COMMENT '1=Ativo, 0=Inativo',
        notificar_lembrete_diario TINYINT(1) DEFAULT 1 COMMENT '1=Ativo, 0=Inativo',
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_usuario (id_usuario),
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql_create)) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao criar tabela: ' . $conn->error
        ]);
        $conn->close();
        exit;
    }
}

// Verificar se já existe configuração
$stmt = $conn->prepare("SELECT id FROM notificacoes_usuario WHERE id_usuario = ?");
if (!$stmt) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao preparar query: ' . $conn->error
    ]);
    $conn->close();
    exit;
}

$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows > 0) {
    // Atualizar
    $stmt = $conn->prepare("UPDATE notificacoes_usuario SET 
        notificar_reserva_propria = ?,
        notificar_reserva_adicional = ?,
        notificar_reserva_multipla = ?,
        notificar_reserva_cancelada = ?,
        notificar_lembrete_diario = ?,
        atualizado_em = NOW()
    WHERE id_usuario = ?");
    if (!$stmt) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao preparar UPDATE: ' . $conn->error
        ]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("iiiiii", $notificar_propria, $notificar_adicional, $notificar_multipla, $notificar_cancelada, $notificar_lembrete_diario, $usuario_id);
} else {
    // Inserir
    $stmt = $conn->prepare("INSERT INTO notificacoes_usuario 
        (id_usuario, notificar_reserva_propria, notificar_reserva_adicional, notificar_reserva_multipla, notificar_reserva_cancelada, notificar_lembrete_diario) 
        VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao preparar INSERT: ' . $conn->error
        ]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("iiiiii", $usuario_id, $notificar_propria, $notificar_adicional, $notificar_multipla, $notificar_cancelada, $notificar_lembrete_diario);
}

if ($stmt && $stmt->execute()) {
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Configurações salvas com sucesso'
    ]);
} else {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao salvar configurações: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>

