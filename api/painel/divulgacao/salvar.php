<?php
/**
 * POST /api/painel/divulgacao/salvar.php
 * Body JSON: { link_android, link_ios, assunto, mensagem }
 * Salva a config da divulgação. Placeholders aceitos na mensagem:
 * {nome}, {link_android}, {link_ios}.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../auth/verifica_sessao_ajax.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAdmin();

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $link_android = trim((string) ($input['link_android'] ?? ''));
    $link_ios     = trim((string) ($input['link_ios'] ?? ''));
    $assunto      = trim((string) ($input['assunto'] ?? ''));
    $mensagem     = trim((string) ($input['mensagem'] ?? ''));

    foreach (['link_android' => $link_android, 'link_ios' => $link_ios] as $campo => $url) {
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['status' => 'erro', 'mensagem' => "Link inválido em $campo — use a URL completa (https://...)"]);
            exit;
        }
    }
    if ($assunto === '')  { echo json_encode(['status' => 'erro', 'mensagem' => 'Assunto é obrigatório (usado no e-mail)']); exit; }
    if ($mensagem === '') { echo json_encode(['status' => 'erro', 'mensagem' => 'Mensagem é obrigatória']); exit; }

    $stmt = $conn->prepare("UPDATE divulgacao_config SET link_android = ?, link_ios = ?, assunto = ?, mensagem = ? WHERE id = 1");
    $stmt->bind_param('ssss', $link_android, $link_ios, $assunto, $mensagem);
    if (!$stmt->execute()) {
        throw new RuntimeException('Erro ao salvar: ' . $stmt->error);
    }
    $stmt->close();

    echo json_encode(['status' => 'ok', 'mensagem' => 'Configuração salva']);
} catch (Throwable $e) {
    error_log('Erro em divulgacao/salvar.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
