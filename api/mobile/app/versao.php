<?php
/**
 * API Mobile — Versão mínima do app (atualização obrigatória)
 *
 * Endpoint: GET /api/mobile/app/versao.php   (SEM autenticação — chamado na
 * abertura do app, antes do login; consumido desde a versão 1.0.6)
 *
 * O app compara a própria versão com versao_minima (parte a parte) e, se
 * menor, mostra tela bloqueante com o link da loja da plataforma.
 * Qualquer falha aqui é fail-open no app (ele abre normalmente).
 *
 * Config editável no painel: /painel/app_versao.php (tabela app_versao_config).
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/../../conexao.php';

    $cfg = $conn->query("SELECT versao_minima, url_android, url_ios, mensagem FROM app_versao_config WHERE id = 1")->fetch_assoc();
    if (!$cfg || !preg_match('/^\d+(\.\d+)*$/', (string) $cfg['versao_minima'])) {
        echo json_encode(['success' => false, 'message' => 'Configuração de versão indisponível']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'versao_minima' => $cfg['versao_minima'],
            'url_android'   => $cfg['url_android'],
            'url_ios'       => $cfg['url_ios'],
            'mensagem'      => $cfg['mensagem'],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Erro em mobile/app/versao.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno']);
}
