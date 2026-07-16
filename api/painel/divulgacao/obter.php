<?php
/**
 * GET /api/painel/divulgacao/obter.php
 * Devolve config da divulgação (links das lojas, assunto, mensagem) e
 * contagens de usuários alcançáveis por canal.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../auth/verifica_sessao_ajax.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAdmin();

try {
    $cfg = $conn->query("SELECT link_android, link_ios, assunto, mensagem, atualizado_em FROM divulgacao_config WHERE id = 1")->fetch_assoc();

    // Alcançáveis por canal (usuários ativos com telefone/e-mail preenchidos)
    $tot = $conn->query("SELECT
            COUNT(*) AS ativos,
            SUM(CASE WHEN telefone IS NOT NULL AND LENGTH(REGEXP_REPLACE(telefone, '[^0-9]', '')) >= 10 THEN 1 ELSE 0 END) AS com_telefone,
            SUM(CASE WHEN email IS NOT NULL AND email LIKE '%@%' THEN 1 ELSE 0 END) AS com_email
        FROM usuarios WHERE ativo = 1")->fetch_assoc();

    echo json_encode([
        'status' => 'ok',
        'config' => $cfg,
        'alcance' => [
            'ativos'       => (int) $tot['ativos'],
            'com_telefone' => (int) $tot['com_telefone'],
            'com_email'    => (int) $tot['com_email'],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Erro em divulgacao/obter.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
