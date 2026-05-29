<?php
/**
 * Cron - Alertas de estoque baixo via WhatsApp
 *
 * Roda de hora em hora. Varre produtos ativos com quantidade_atual <= quantidade_minima
 * (mesmo critério do sino do dashboard), registra/atualiza em estoque_alertas e
 * notifica os responsáveis do departamento por WhatsApp. Respeita um intervalo de
 * repetição configurável (estoque_config_alertas.intervalo_horas) para não gerar spam:
 * cada produto só é re-avisado se o último aviso foi há mais que o intervalo.
 *
 * Departamentos sem responsável caem para o número de fallback configurado.
 *
 * Configuração: estoque/configuracoes/alertas.php
 */

date_default_timezone_set('America/Cuiaba');

require_once __DIR__ . '/../utils/logger.php';

function logAlertaEstoque($mensagem) {
    Logger::emergencial('alertas_estoque', $mensagem);
}

logAlertaEstoque('=== INICIANDO VERIFICACAO DE ALERTAS DE ESTOQUE ===');

try {
    require_once __DIR__ . '/../api/conexao.php';
    require_once __DIR__ . '/../core/services/WhatsAppService.php';

    // 1) Configuração
    $cfg = $conn->query("SELECT * FROM estoque_config_alertas WHERE id = 1")->fetch_assoc();
    if (!$cfg) {
        logAlertaEstoque('Configuracao (estoque_config_alertas) nao encontrada - saindo');
        exit(0);
    }
    if ((int)$cfg['ativo'] !== 1) {
        logAlertaEstoque('Notificacoes de alerta desativadas (ativo=0) - saindo');
        exit(0);
    }
    $intervalo_horas   = max(1, (int)$cfg['intervalo_horas']);
    $telefone_fallback = trim($cfg['telefone_fallback'] ?? '');

    // 2) Produtos atualmente em alerta (mesmo critério do dashboard/alertas.php)
    $sql = "SELECT p.id, p.nome, p.codigo, p.quantidade_atual, p.quantidade_minima,
                   p.id_departamento, u.sigla AS unidade, d.nome AS departamento
            FROM estoque_produtos p
            JOIN estoque_departamentos d ON d.id = p.id_departamento
            LEFT JOIN estoque_unidades u ON u.id = p.id_unidade
            WHERE p.ativo = 1 AND p.quantidade_atual <= p.quantidade_minima
            ORDER BY p.id_departamento, p.nome";
    $res = $conn->query($sql);
    $em_alerta = [];
    while ($r = $res->fetch_assoc()) {
        $r['tipo'] = ((float)$r['quantidade_atual'] <= 0) ? 'estoque_zerado' : 'estoque_minimo';
        $em_alerta[] = $r;
    }
    logAlertaEstoque('Produtos em alerta: ' . count($em_alerta));

    // 3) Upsert dos alertas ativos. Se a linha estava resolvida (produto se recuperou e
    //    voltou a cair), reseta notificado_em para avisar de imediato.
    $upsert = $conn->prepare(
        "INSERT INTO estoque_alertas (id_produto, id_departamento, tipo, mensagem, resolvido, notificado_em)
         VALUES (?, ?, ?, ?, 0, NULL)
         ON DUPLICATE KEY UPDATE
            id_departamento = VALUES(id_departamento),
            mensagem        = VALUES(mensagem),
            notificado_em   = IF(resolvido = 1, NULL, notificado_em),
            resolvido       = 0,
            resolvido_em    = NULL"
    );
    $chaves_ativas = []; // "id_produto:tipo"
    foreach ($em_alerta as $p) {
        $msg = $p['tipo'] === 'estoque_zerado'
            ? 'Estoque zerado'
            : ('Estoque abaixo do minimo (' . rtrim(rtrim((string)$p['quantidade_atual'], '0'), '.') . ' de ' . rtrim(rtrim((string)$p['quantidade_minima'], '0'), '.') . ')');
        $upsert->bind_param('iiss', $p['id'], $p['id_departamento'], $p['tipo'], $msg);
        $upsert->execute();
        $chaves_ativas[] = $p['id'] . ':' . $p['tipo'];
    }
    $upsert->close();

    // 4) Resolver alertas que não estão mais em estado de alerta
    if (empty($chaves_ativas)) {
        $afetados = $conn->query("UPDATE estoque_alertas SET resolvido = 1, resolvido_em = NOW(), notificado_em = NULL WHERE resolvido = 0")->affected_rows ?? 0;
    } else {
        // CONCAT(id_produto,':',tipo) NOT IN (...) — monta lista escapada
        $lista = implode(',', array_map(function ($k) use ($conn) {
            return "'" . $conn->real_escape_string($k) . "'";
        }, $chaves_ativas));
        $conn->query("UPDATE estoque_alertas SET resolvido = 1, resolvido_em = NOW(), notificado_em = NULL
                      WHERE resolvido = 0 AND CONCAT(id_produto, ':', tipo) NOT IN ($lista)");
    }

    // 5) Alertas "vencidos" para notificação (nunca avisados ou avisados há mais que o intervalo)
    $stmt = $conn->prepare(
        "SELECT a.id AS alerta_id, a.id_produto, a.id_departamento, a.tipo,
                p.nome, p.codigo, p.quantidade_atual, p.quantidade_minima,
                u.sigla AS unidade, d.nome AS departamento
         FROM estoque_alertas a
         JOIN estoque_produtos p ON p.id = a.id_produto
         JOIN estoque_departamentos d ON d.id = a.id_departamento
         LEFT JOIN estoque_unidades u ON u.id = p.id_unidade
         WHERE a.resolvido = 0
           AND p.ativo = 1
           AND (a.notificado_em IS NULL OR a.notificado_em < (NOW() - INTERVAL ? HOUR))
         ORDER BY a.id_departamento, p.nome"
    );
    $stmt->bind_param('i', $intervalo_horas);
    $stmt->execute();
    $rs = $stmt->get_result();
    $pendentes_por_depto = []; // id_depto => ['nome'=>, 'itens'=>[], 'alerta_ids'=>[]]
    while ($a = $rs->fetch_assoc()) {
        $d = $a['id_departamento'];
        if (!isset($pendentes_por_depto[$d])) {
            $pendentes_por_depto[$d] = ['nome' => $a['departamento'], 'itens' => [], 'alerta_ids' => []];
        }
        $pendentes_por_depto[$d]['itens'][] = $a;
        $pendentes_por_depto[$d]['alerta_ids'][] = (int)$a['alerta_id'];
    }
    $stmt->close();

    if (empty($pendentes_por_depto)) {
        logAlertaEstoque('Nenhum alerta pendente de notificacao (intervalo ' . $intervalo_horas . 'h). Fim.');
        exit(0);
    }

    // Helper: monta o bloco de texto de um departamento
    $montarBloco = function ($nomeDepto, $itens) {
        $linhas = ["*Alerta de Estoque - {$nomeDepto}*", '', 'Itens que precisam de reposicao:'];
        foreach ($itens as $it) {
            $atual = rtrim(rtrim((string)$it['quantidade_atual'], '0'), '.');
            $min   = rtrim(rtrim((string)$it['quantidade_minima'], '0'), '.');
            $un    = $it['unidade'] ?? '';
            $cod   = $it['codigo'] ? " ({$it['codigo']})" : '';
            if ((float)$it['quantidade_atual'] <= 0) {
                $linhas[] = "- {$it['nome']}{$cod}: *ZERADO*";
            } else {
                $linhas[] = "- {$it['nome']}{$cod}: {$atual} {$un} (minimo {$min} {$un})";
            }
        }
        $linhas[] = '';
        $linhas[] = 'Acesse o sistema de estoque para providenciar a reposicao.';
        return mb_convert_encoding(implode("\n", $linhas), 'UTF-8', 'auto');
    };

    // Helper: envia e, em sucesso, marca os alertas como notificados
    $marcarNotificados = function ($alerta_ids) use ($conn) {
        if (empty($alerta_ids)) return;
        $ids = implode(',', array_map('intval', $alerta_ids));
        $conn->query("UPDATE estoque_alertas SET notificado_em = NOW() WHERE id IN ($ids)");
    };

    $total_enviados = 0;
    $total_falhas   = 0;
    $fallback_blocos = [];
    $fallback_alerta_ids = [];
    $primeiro_envio = true;

    foreach ($pendentes_por_depto as $id_depto => $dados) {
        // Responsáveis ativos do departamento com telefone válido
        $stmtResp = $conn->prepare(
            "SELECT u.id, u.nome, u.telefone
             FROM estoque_responsaveis r
             JOIN usuarios u ON u.id = r.id_usuario
             WHERE r.id_departamento = ? AND r.ativo = 1 AND u.ativo = 1
               AND u.telefone IS NOT NULL AND u.telefone <> ''"
        );
        $stmtResp->bind_param('i', $id_depto);
        $stmtResp->execute();
        $rResp = $stmtResp->get_result();
        $destinatarios = [];
        while ($u = $rResp->fetch_assoc()) {
            if (!empty(WhatsAppService::normalizarTelefone($u['telefone']))) {
                $destinatarios[] = $u;
            }
        }
        $stmtResp->close();

        if (empty($destinatarios)) {
            // Sem responsável -> acumula para o fallback
            $fallback_blocos[] = $montarBloco($dados['nome'], $dados['itens']);
            $fallback_alerta_ids = array_merge($fallback_alerta_ids, $dados['alerta_ids']);
            logAlertaEstoque("Depto '{$dados['nome']}' sem responsavel com telefone - enviado ao fallback");
            continue;
        }

        $mensagem = $montarBloco($dados['nome'], $dados['itens']);
        $algum_sucesso = false;
        foreach ($destinatarios as $dest) {
            if (!$primeiro_envio) {
                sleep(WhatsAppService::calcularDelayAleatorio(3, 8));
            }
            $primeiro_envio = false;

            $r = WhatsAppService::enviarMensagem($dest['telefone'], $mensagem, [
                'log_callback' => function ($m) { logAlertaEstoque("WhatsApp: $m"); },
                'usuario_id' => $dest['id'],
                'nome_destinatario' => $dest['nome'],
                'tipo_mensagem' => 'alerta_estoque',
            ]);
            if (!empty($r['sucesso'])) {
                $algum_sucesso = true;
                $total_enviados++;
                logAlertaEstoque("OK -> {$dest['nome']} ({$dest['telefone']}) depto '{$dados['nome']}'");
            } else {
                $total_falhas++;
                logAlertaEstoque("FALHA -> {$dest['nome']} ({$dest['telefone']}): " . ($r['mensagem'] ?? 'erro'));
            }
        }
        if ($algum_sucesso) {
            $marcarNotificados($dados['alerta_ids']);
        }
    }

    // Envio agrupado ao fallback
    if (!empty($fallback_blocos)) {
        if ($telefone_fallback !== '' && !empty(WhatsAppService::normalizarTelefone($telefone_fallback))) {
            if (!$primeiro_envio) {
                sleep(WhatsAppService::calcularDelayAleatorio(3, 8));
            }
            $cabecalho = mb_convert_encoding("Departamentos sem responsavel cadastrado:\n\n", 'UTF-8', 'auto');
            $mensagem_fallback = $cabecalho . implode("\n\n", $fallback_blocos);
            $r = WhatsAppService::enviarMensagem($telefone_fallback, $mensagem_fallback, [
                'log_callback' => function ($m) { logAlertaEstoque("WhatsApp(fallback): $m"); },
                'tipo_mensagem' => 'alerta_estoque',
            ]);
            if (!empty($r['sucesso'])) {
                $total_enviados++;
                $marcarNotificados($fallback_alerta_ids);
                logAlertaEstoque("OK -> fallback ({$telefone_fallback}) com " . count($fallback_blocos) . ' depto(s)');
            } else {
                $total_falhas++;
                logAlertaEstoque('FALHA -> fallback (' . $telefone_fallback . '): ' . ($r['mensagem'] ?? 'erro'));
            }
        } else {
            logAlertaEstoque('Ha ' . count($fallback_blocos) . ' depto(s) sem responsavel mas sem telefone de fallback configurado - alertas registrados mas nao enviados');
        }
    }

    logAlertaEstoque("=== FIM === enviados=$total_enviados falhas=$total_falhas");
    exit(0);

} catch (Throwable $e) {
    logAlertaEstoque('ERRO FATAL: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    exit(1);
}
