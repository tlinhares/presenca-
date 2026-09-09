<?php
/**
 * GET /api/painel/app_versao/obter.php — config atual + quem alterou.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../auth/verifica_sessao_ajax.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAdmin();

try {
    $cfg = $conn->query(
        "SELECT c.versao_minima, c.url_android, c.url_ios, c.mensagem, c.atualizado_em, u.nome AS atualizado_por_nome
           FROM app_versao_config c
      LEFT JOIN usuarios u ON u.id = c.atualizado_por
          WHERE c.id = 1"
    )->fetch_assoc();
    echo json_encode(['status' => 'ok', 'config' => $cfg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Erro em app_versao/obter.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
