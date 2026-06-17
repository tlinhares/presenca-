<?php
/**
 * POST /api/privacidade/salvar.php
 * Body JSON: { conteudo_html, versao, vigente_desde? }
 *
 * Salva a política de privacidade. Apenas admin.
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../conexao.php';

MenuPermissaoService::exigirAdmin();

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $conteudo = trim((string) ($input['conteudo_html'] ?? ''));
    $versao   = trim((string) ($input['versao'] ?? ''));
    $vigente  = trim((string) ($input['vigente_desde'] ?? ''));

    if ($conteudo === '') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Conteúdo não pode ficar vazio']);
        exit;
    }
    if (strlen($conteudo) > 2 * 1024 * 1024) { // 2 MB
        echo json_encode(['status' => 'erro', 'mensagem' => 'Conteúdo excede o limite de 2 MB']);
        exit;
    }
    if ($versao === '') $versao = '1.0';
    if (mb_strlen($versao) > 20) $versao = mb_substr($versao, 0, 20);

    // Data: aceita YYYY-MM-DD; se ausente ou inválida, usa hoje
    if ($vigente === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vigente)) {
        $vigente = date('Y-m-d');
    }

    $usuario_id = (int) ($_SESSION['usuario_id'] ?? 0);

    $stmt = $conn->prepare(
        "INSERT INTO politica_privacidade (id, conteudo_html, versao, vigente_desde, atualizado_por)
         VALUES (1, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            conteudo_html = VALUES(conteudo_html),
            versao = VALUES(versao),
            vigente_desde = VALUES(vigente_desde),
            atualizado_por = VALUES(atualizado_por)"
    );
    $stmt->bind_param('sssi', $conteudo, $versao, $vigente, $usuario_id);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Erro ao salvar: ' . $conn->error);
    }
    $stmt->close();

    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Política de privacidade salva',
        'versao' => $versao,
        'vigente_desde' => $vigente,
    ]);
} catch (Throwable $e) {
    error_log('Erro em privacidade/salvar.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
