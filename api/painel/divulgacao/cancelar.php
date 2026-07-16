<?php
/**
 * POST /api/painel/divulgacao/cancelar.php
 * Cancela todos os envios ainda pendentes (o que já foi enviado, foi).
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../auth/verifica_sessao_ajax.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAdmin();

try {
    $conn->query("UPDATE divulgacao_fila SET status = 'cancelado' WHERE status = 'pendente'");
    $n = $conn->affected_rows;
    echo json_encode([
        'status' => 'ok',
        'mensagem' => $n > 0 ? "$n envio(s) pendente(s) cancelado(s)." : 'Nada pendente pra cancelar.',
        'cancelados' => $n,
    ]);
} catch (Throwable $e) {
    error_log('Erro em divulgacao/cancelar.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
