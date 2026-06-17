<?php
/**
 * GET /api/privacidade/obter.php
 * Retorna a política de privacidade publicada (público — sem auth).
 * Usado por /privacidade.html.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: public, max-age=300'); // 5 min

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../conexao.php';

try {
    $r = $conn->query("SELECT conteudo_html, versao, vigente_desde, atualizado_em FROM politica_privacidade WHERE id = 1 LIMIT 1");
    $row = $r ? $r->fetch_assoc() : null;

    if (!$row) {
        echo json_encode([
            'status' => 'ok',
            'conteudo_html' => '<p>Política de privacidade ainda não publicada.</p>',
            'versao' => '0.0',
            'vigente_desde' => null,
            'atualizado_em' => null,
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'conteudo_html' => $row['conteudo_html'],
        'versao' => $row['versao'],
        'vigente_desde' => $row['vigente_desde'],
        'atualizado_em' => $row['atualizado_em'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Erro em privacidade/obter.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao carregar política']);
}

$conn->close();
