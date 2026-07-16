<?php
/**
 * POST /api/painel/divulgacao/disparar.php
 * Body JSON: { canais: ["whatsapp","email"] }
 *
 * Enfileira a divulgação para TODOS os usuários ativos nos canais escolhidos.
 * O cron processar_divulgacao.php (a cada minuto) faz o envio gradual —
 * WhatsApp precisa de intervalos humanizados pra não bloquear o número.
 *
 * A mensagem é renderizada por usuário JÁ NO ENFILEIRAMENTO (congela o texto:
 * editar a config depois não altera um lote já disparado).
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../auth/verifica_sessao_ajax.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/DivulgacaoService.php';

MenuPermissaoService::exigirAdmin();

date_default_timezone_set('America/Cuiaba');

try {
    $input  = json_decode(file_get_contents('php://input'), true) ?: [];
    $canais = array_values(array_intersect((array) ($input['canais'] ?? []), ['whatsapp', 'email']));
    if (empty($canais)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Selecione ao menos um canal (WhatsApp ou E-mail)']);
        exit;
    }

    $cfg = $conn->query("SELECT link_android, link_ios, assunto, mensagem FROM divulgacao_config WHERE id = 1")->fetch_assoc();
    if (!$cfg || trim($cfg['mensagem']) === '') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Salve a configuração (mensagem) antes de disparar']);
        exit;
    }
    if (trim($cfg['link_android']) === '' && trim($cfg['link_ios']) === '') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Cadastre ao menos um link de loja antes de disparar pra todo mundo']);
        exit;
    }

    // Evita dois lotes simultâneos — um disparo em massa por vez.
    $pend = (int) $conn->query("SELECT COUNT(*) n FROM divulgacao_fila WHERE status IN ('pendente','processando')")->fetch_assoc()['n'];
    if ($pend > 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => "Já existe um disparo em andamento ($pend envio(s) na fila). Aguarde concluir ou cancele os pendentes."]);
        exit;
    }

    $id_lote    = date('Ymd_His');
    $criado_por = (int) $_SESSION['usuario_id'];

    $rs = $conn->query("SELECT id, nome, email, telefone FROM usuarios WHERE ativo = 1 ORDER BY nome");
    $enfileirados = ['whatsapp' => 0, 'email' => 0];
    $sem_destino  = ['whatsapp' => 0, 'email' => 0];

    $stmt = $conn->prepare(
        "INSERT INTO divulgacao_fila (id_lote, id_usuario, nome, canal, destino, mensagem, assunto, criado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    while ($u = $rs->fetch_assoc()) {
        $mensagem = DivulgacaoService::render($cfg['mensagem'], $u['nome'], $cfg['link_android'], $cfg['link_ios']);

        foreach ($canais as $canal) {
            if ($canal === 'whatsapp') {
                $destino = trim((string) $u['telefone']);
                if (strlen(preg_replace('/\D/', '', $destino)) < 10) { $sem_destino['whatsapp']++; continue; }
            } else {
                $destino = trim((string) $u['email']);
                if ($destino === '' || strpos($destino, '@') === false) { $sem_destino['email']++; continue; }
            }
            $assunto = ($canal === 'email') ? $cfg['assunto'] : null;
            $uid = (int) $u['id'];
            $stmt->bind_param('sisssssi', $id_lote, $uid, $u['nome'], $canal, $destino, $mensagem, $assunto, $criado_por);
            if ($stmt->execute()) $enfileirados[$canal]++;
        }
    }
    $stmt->close();

    $total = array_sum($enfileirados);
    if ($total === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Nenhum destinatário elegível nos canais escolhidos.']);
        exit;
    }

    // Estimativa: e-mail ~15/min; whatsapp ~4/min (delays anti-bloqueio)
    $min_email = (int) ceil($enfileirados['email'] / 15);
    $min_whats = (int) ceil($enfileirados['whatsapp'] / 4);
    $estimativa = max($min_email, $min_whats, 1);

    echo json_encode([
        'status'  => 'ok',
        'mensagem' => sprintf(
            'Disparo iniciado! %d envio(s) na fila (%d WhatsApp, %d e-mail). Conclusão estimada: ~%d minuto(s).',
            $total, $enfileirados['whatsapp'], $enfileirados['email'], $estimativa
        ),
        'id_lote' => $id_lote,
        'enfileirados' => $enfileirados,
        'sem_destino'  => $sem_destino,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Erro em divulgacao/disparar.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
