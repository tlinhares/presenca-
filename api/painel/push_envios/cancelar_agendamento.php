<?php
/**
 * POST /api/painel/push_envios/cancelar_agendamento.php
 * Body JSON: { id: number }
 * Cancela um agendamento ainda pendente (não pode cancelar depois de executado).
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../auth/verifica_sessao_ajax.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAdmin();

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) throw new RuntimeException('ID inválido');

    $stmt = $conn->prepare("UPDATE notificacoes_push_agendadas SET status = 'cancelado' WHERE id = ? AND status = 'pendente'");
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) throw new RuntimeException('Erro: ' . $conn->error);
    $afetados = $stmt->affected_rows;
    $stmt->close();

    if ($afetados === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Agendamento não encontrado ou já foi executado/cancelado.']);
        exit;
    }
    echo json_encode(['status' => 'ok', 'mensagem' => 'Agendamento cancelado.']);
} catch (Throwable $e) {
    error_log('Erro em push_envios/cancelar_agendamento.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
