<?php
/**
 * GET /api/estoque/config_alertas/obter.php
 * Retorna a configuração de alertas de estoque e um resumo das pendências.
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAcessoAPI('estoque_config_alertas');

try {
    $cfg = $conn->query("SELECT id, ativo, intervalo_horas, telefone_fallback, atualizado_em FROM estoque_config_alertas WHERE id = 1")->fetch_assoc();
    if (!$cfg) {
        $cfg = ['ativo' => 1, 'intervalo_horas' => 24, 'telefone_fallback' => null, 'atualizado_em' => null];
    }

    // Resumo de pendências atuais (mesmo critério do cron/sino)
    $pendentes = (int) $conn->query(
        "SELECT COUNT(*) t FROM estoque_produtos WHERE ativo = 1 AND quantidade_atual <= quantidade_minima"
    )->fetch_assoc()['t'];

    // Departamentos com alerta que NÃO têm responsável com telefone (cairiam no fallback)
    $sem_resp = (int) $conn->query(
        "SELECT COUNT(*) t FROM (
            SELECT p.id_departamento
            FROM estoque_produtos p
            WHERE p.ativo = 1 AND p.quantidade_atual <= p.quantidade_minima
              AND NOT EXISTS (
                SELECT 1 FROM estoque_responsaveis r JOIN usuarios u ON u.id = r.id_usuario
                WHERE r.id_departamento = p.id_departamento AND r.ativo = 1 AND u.ativo = 1
                  AND u.telefone IS NOT NULL AND u.telefone <> ''
              )
            GROUP BY p.id_departamento
        ) x"
    )->fetch_assoc()['t'];

    echo json_encode([
        'status' => 'ok',
        'config' => [
            'ativo' => (int) $cfg['ativo'],
            'intervalo_horas' => (int) $cfg['intervalo_horas'],
            'telefone_fallback' => $cfg['telefone_fallback'] ?? '',
            'atualizado_em' => $cfg['atualizado_em'],
        ],
        'resumo' => [
            'produtos_em_alerta' => $pendentes,
            'departamentos_sem_responsavel' => $sem_resp,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Erro em config_alertas/obter.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
