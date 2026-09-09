<?php
/**
 * POST /api/painel/app_versao/salvar.php
 * Body JSON: { versao_minima, url_android, url_ios, mensagem }
 *
 * Salva a config de versão mínima do app. Auditoria: grava quem alterou
 * (coluna atualizado_por) e loga toda mudança de versao_minima em
 * logs/app_versao_audit.log — subir a versão mínima BLOQUEIA aparelhos
 * desatualizados, então precisa ser rastreável.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../auth/verifica_sessao_ajax.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAdmin();

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $versao   = trim((string) ($input['versao_minima'] ?? ''));
    $urlAnd   = trim((string) ($input['url_android'] ?? ''));
    $urlIos   = trim((string) ($input['url_ios'] ?? ''));
    $mensagem = trim((string) ($input['mensagem'] ?? ''));

    if (!preg_match('/^\d+\.\d+\.\d+$/', $versao)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Versão mínima inválida — use o formato x.y.z (ex.: 1.0.5)']);
        exit;
    }
    foreach (['Link Android' => $urlAnd, 'Link iOS' => $urlIos] as $campo => $url) {
        if (strpos($url, 'https://') !== 0 || !filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['status' => 'erro', 'mensagem' => "$campo inválido — precisa começar com https://"]);
            exit;
        }
    }
    if (mb_strlen($mensagem) > 300) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Mensagem muito longa (máx. 300 caracteres)']);
        exit;
    }

    $uid = (int) $_SESSION['usuario_id'];

    // Auditoria da mudança de versão mínima
    $anterior = $conn->query("SELECT versao_minima FROM app_versao_config WHERE id = 1")->fetch_assoc()['versao_minima'] ?? '?';
    if ($anterior !== $versao) {
        @file_put_contents(__DIR__ . '/../../../logs/app_versao_audit.log',
            '[' . date('Y-m-d H:i:s') . "] versao_minima: $anterior -> $versao | por usuario_id=$uid (" . ($_SESSION['usuario_nome'] ?? '?') . ")\n",
            FILE_APPEND);
    }

    $stmt = $conn->prepare("UPDATE app_versao_config SET versao_minima = ?, url_android = ?, url_ios = ?, mensagem = ?, atualizado_por = ? WHERE id = 1");
    $stmt->bind_param('ssssi', $versao, $urlAnd, $urlIos, $mensagem, $uid);
    if (!$stmt->execute()) {
        throw new RuntimeException('Erro ao salvar: ' . $stmt->error);
    }
    $stmt->close();

    echo json_encode(['status' => 'ok', 'mensagem' => 'Configuração salva. O app aplica na próxima abertura.']);
} catch (Throwable $e) {
    error_log('Erro em app_versao/salvar.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
