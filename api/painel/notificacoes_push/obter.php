<?php
/**
 * GET /api/painel/notificacoes_push/obter.php
 * Retorna a config de push (sem expor o private_key da service account).
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAdmin();

try {
    $cfg = $conn->query("SELECT ativo, project_id, client_email, service_account_path, titulo_padrao, som_padrao, atualizado_em FROM notificacoes_push_config WHERE id = 1")->fetch_assoc();
    if (!$cfg) {
        $cfg = ['ativo' => 0, 'project_id' => '', 'client_email' => '', 'service_account_path' => '', 'titulo_padrao' => 'Presença AOM', 'som_padrao' => 'default', 'atualizado_em' => null];
    }

    $arquivo_existe = !empty($cfg['service_account_path']) && is_readable($cfg['service_account_path']);

    $dispositivos = (int) $conn->query("SELECT COUNT(*) t FROM notificacoes_push_dispositivos WHERE ativo = 1")->fetch_assoc()['t'];
    $usuarios_alcancaveis = (int) $conn->query("SELECT COUNT(DISTINCT id_usuario) t FROM notificacoes_push_dispositivos WHERE ativo = 1")->fetch_assoc()['t'];

    $envios_24h = (int) $conn->query("SELECT COUNT(*) t FROM notificacoes_push_envios WHERE enviado_em >= (NOW() - INTERVAL 24 HOUR)")->fetch_assoc()['t'];
    $sucessos_24h = (int) $conn->query("SELECT COUNT(*) t FROM notificacoes_push_envios WHERE enviado_em >= (NOW() - INTERVAL 24 HOUR) AND status = 'sucesso'")->fetch_assoc()['t'];

    echo json_encode([
        'status' => 'ok',
        'config' => [
            'ativo' => (int) $cfg['ativo'],
            'project_id' => $cfg['project_id'] ?? '',
            'client_email' => $cfg['client_email'] ?? '',
            'titulo_padrao' => $cfg['titulo_padrao'] ?? 'Presença AOM',
            'som_padrao' => $cfg['som_padrao'] ?? 'default',
            'service_account_configurado' => $arquivo_existe,
            'atualizado_em' => $cfg['atualizado_em'],
        ],
        'resumo' => [
            'dispositivos_ativos' => $dispositivos,
            'usuarios_alcancaveis' => $usuarios_alcancaveis,
            'envios_24h' => $envios_24h,
            'sucessos_24h' => $sucessos_24h,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Erro em notificacoes_push/obter.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
