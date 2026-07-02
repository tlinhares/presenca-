<?php
/**
 * POST /api/painel/comunicados/excluir.php
 * Body JSON: { id }
 *
 * Exclusão definitiva — só permitida para rascunhos e arquivados.
 * Publicado precisa ser arquivado antes (evita sumiço acidental do app).
 * Remove também a imagem do disco e as leituras (FK CASCADE).
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../auth/verifica_sessao_ajax.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAdmin();

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido']);
        exit;
    }

    $stmt = $conn->prepare("SELECT status, imagem FROM comunicados WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $com = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$com) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Comunicado não encontrado']);
        exit;
    }
    if ($com['status'] === 'publicado') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Arquive o comunicado antes de excluir']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM comunicados WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    if ($com['imagem']) {
        $path = realpath(__DIR__ . '/../../../' . ltrim($com['imagem'], '/'));
        $base = realpath(__DIR__ . '/../../../uploads/comunicados');
        if ($path && $base && strpos($path, $base) === 0) {
            @unlink($path);
        }
    }

    echo json_encode(['status' => 'ok', 'mensagem' => 'Comunicado excluído']);
} catch (Throwable $e) {
    error_log('Erro em painel/comunicados/excluir.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
