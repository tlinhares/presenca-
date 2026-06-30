<?php
/**
 * GET /api/usuarios/reativar.php?id=N
 * Reativa (ativo=1) um usuário que foi previamente inativado.
 *
 * Não precisa re-sincronizar com dispositivo facial: o cron de remoção
 * detecta usuário ativo e com reserva e re-sincroniza automaticamente
 * quando o usuário voltar a fazer reservas.
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../conexao.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido']);
    exit;
}

try {
    // Verificar se o usuário existe e seu estado atual
    $check = $conn->prepare("SELECT id, nome, ativo FROM usuarios WHERE id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $usuario = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$usuario) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não encontrado']);
        exit;
    }
    if ((int) $usuario['ativo'] === 1) {
        echo json_encode(['status' => 'ok', 'mensagem' => 'Usuário já está ativo', 'ja_ativo' => true]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE usuarios SET ativo = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute() && $stmt->affected_rows >= 0) {
        $stmt->close();
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Usuário "' . $usuario['nome'] . '" reativado com sucesso',
        ]);
    } else {
        $stmt->close();
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao reativar usuário']);
    }
} catch (Throwable $e) {
    error_log('Erro em usuarios/reativar.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
