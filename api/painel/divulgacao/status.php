<?php
/**
 * GET /api/painel/divulgacao/status.php[?id_lote=...]
 * Progresso do disparo: contagens por status/canal do lote informado
 * (ou do lote mais recente) + últimas falhas.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../auth/verifica_sessao_ajax.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAdmin();

try {
    $id_lote = trim((string) ($_GET['id_lote'] ?? ''));
    if ($id_lote === '') {
        $r = $conn->query("SELECT id_lote FROM divulgacao_fila ORDER BY id DESC LIMIT 1")->fetch_assoc();
        $id_lote = $r['id_lote'] ?? '';
    }
    if ($id_lote === '') {
        echo json_encode(['status' => 'ok', 'lote' => null]);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT canal, status, COUNT(*) n
           FROM divulgacao_fila WHERE id_lote = ?
          GROUP BY canal, status"
    );
    $stmt->bind_param('s', $id_lote);
    $stmt->execute();
    $rs = $stmt->get_result();
    $contagem = [];
    $total = 0; $finalizados = 0;
    while ($x = $rs->fetch_assoc()) {
        $contagem[$x['canal']][$x['status']] = (int) $x['n'];
        $total += (int) $x['n'];
        if (in_array($x['status'], ['enviado', 'falha', 'cancelado'], true)) $finalizados += (int) $x['n'];
    }
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT nome, canal, destino, erro FROM divulgacao_fila
          WHERE id_lote = ? AND status = 'falha' ORDER BY id DESC LIMIT 10"
    );
    $stmt->bind_param('s', $id_lote);
    $stmt->execute();
    $rs = $stmt->get_result();
    $falhas = [];
    while ($x = $rs->fetch_assoc()) $falhas[] = $x;
    $stmt->close();

    echo json_encode([
        'status' => 'ok',
        'lote' => [
            'id_lote'     => $id_lote,
            'total'       => $total,
            'finalizados' => $finalizados,
            'em_andamento'=> $total - $finalizados,
            'percentual'  => $total > 0 ? round($finalizados * 100 / $total) : 0,
            'contagem'    => $contagem,
            'ultimas_falhas' => $falhas,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Erro em divulgacao/status.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
