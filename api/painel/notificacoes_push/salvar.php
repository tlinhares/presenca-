<?php
/**
 * POST /api/painel/notificacoes_push/salvar.php
 * Body JSON:
 *   { ativo: 0|1,
 *     titulo_padrao: string,
 *     som_padrao: string,
 *     service_account_json: string (opcional — JSON da service account Firebase)
 *   }
 *
 * Se service_account_json vier, é validado, persistido em arquivo seguro
 * (/var/backups/presenca/firebase/) e atualiza project_id / client_email /
 * service_account_path. Se não vier, só atualiza os demais campos.
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/PushNotificationService.php';

MenuPermissaoService::exigirAdmin();

const PUSH_SA_DIR  = '/var/backups/presenca/firebase';
const PUSH_SA_FILE = '/var/backups/presenca/firebase/service_account.json';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $ativo = !empty($input['ativo']) ? 1 : 0;
    $titulo = trim($input['titulo_padrao'] ?? 'Presença AOM');
    if ($titulo === '') $titulo = 'Presença AOM';
    if (mb_strlen($titulo) > 120) $titulo = mb_substr($titulo, 0, 120);
    $som = trim($input['som_padrao'] ?? 'default');
    if ($som === '') $som = 'default';

    // Estado atual (pra preservar se não vier service_account_json novo)
    $atual = $conn->query("SELECT project_id, client_email, service_account_path FROM notificacoes_push_config WHERE id = 1")->fetch_assoc()
        ?: ['project_id' => null, 'client_email' => null, 'service_account_path' => null];

    $project_id   = $atual['project_id'];
    $client_email = $atual['client_email'];
    $sa_path      = $atual['service_account_path'];

    if (!empty($input['service_account_json'])) {
        $sa = json_decode((string) $input['service_account_json'], true);
        if (!is_array($sa)) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'JSON da Service Account inválido (não é JSON válido)']);
            exit;
        }
        foreach (['type', 'project_id', 'private_key', 'client_email'] as $req) {
            if (empty($sa[$req])) {
                echo json_encode(['status' => 'erro', 'mensagem' => "Service Account incompleta: campo '$req' ausente."]);
                exit;
            }
        }
        if ($sa['type'] !== 'service_account') {
            echo json_encode(['status' => 'erro', 'mensagem' => 'JSON enviado não é uma Service Account (campo "type" ≠ "service_account")']);
            exit;
        }

        if (!is_dir(PUSH_SA_DIR)) {
            if (!@mkdir(PUSH_SA_DIR, 0770, true)) {
                throw new RuntimeException('Não foi possível criar diretório ' . PUSH_SA_DIR . '. Crie manualmente: mkdir -p ' . PUSH_SA_DIR . ' && chown root:www-data ' . PUSH_SA_DIR . ' && chmod 770 ' . PUSH_SA_DIR);
            }
        }
        if (!is_writable(PUSH_SA_DIR)) {
            throw new RuntimeException('Sem permissão de escrita em ' . PUSH_SA_DIR . '. Ajuste: chmod 770 ' . PUSH_SA_DIR . ' && chown root:www-data ' . PUSH_SA_DIR);
        }
        if (file_put_contents(PUSH_SA_FILE, json_encode($sa, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
            throw new RuntimeException('Falha ao gravar arquivo da Service Account em ' . PUSH_SA_FILE);
        }
        @chmod(PUSH_SA_FILE, 0640);
        @chgrp(PUSH_SA_FILE, 'www-data');

        $project_id   = $sa['project_id'];
        $client_email = $sa['client_email'];
        $sa_path      = PUSH_SA_FILE;
    }

    $usuario_id = (int) ($_SESSION['usuario_id'] ?? 0);
    $stmt = $conn->prepare(
        "INSERT INTO notificacoes_push_config (id, ativo, project_id, client_email, service_account_path, titulo_padrao, som_padrao, atualizado_por)
         VALUES (1, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            ativo = VALUES(ativo),
            project_id = VALUES(project_id),
            client_email = VALUES(client_email),
            service_account_path = VALUES(service_account_path),
            titulo_padrao = VALUES(titulo_padrao),
            som_padrao = VALUES(som_padrao),
            atualizado_por = VALUES(atualizado_por)"
    );
    $stmt->bind_param('isssssi', $ativo, $project_id, $client_email, $sa_path, $titulo, $som, $usuario_id);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Erro ao salvar config: ' . $conn->error);
    }
    $stmt->close();

    PushNotificationService::limparCache();

    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Configuração de push salva com sucesso',
        'config' => [
            'ativo' => $ativo,
            'project_id' => $project_id,
            'client_email' => $client_email,
            'service_account_configurado' => $sa_path && is_readable($sa_path),
        ],
    ]);
} catch (Throwable $e) {
    error_log('Erro em notificacoes_push/salvar.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
