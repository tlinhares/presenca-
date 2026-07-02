<?php
/**
 * GET /api/painel/comunicados/listar.php?status=<todos|rascunho|publicado|arquivado>
 * Lista comunicados para o painel admin, com contagem de leituras.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../auth/verifica_sessao_ajax.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAdmin();

try {
    $status = $_GET['status'] ?? 'todos';
    $where = '';
    if (in_array($status, ['rascunho', 'publicado', 'arquivado'], true)) {
        $where = "WHERE c.status = '" . $conn->real_escape_string($status) . "'";
    }

    $rs = $conn->query(
        "SELECT c.*, u.nome AS criado_por_nome,
                (SELECT COUNT(*) FROM comunicados_leituras WHERE id_comunicado = c.id) AS leituras
           FROM comunicados c
      LEFT JOIN usuarios u ON u.id = c.criado_por
         $where
       ORDER BY FIELD(c.status,'publicado','rascunho','arquivado'), c.criado_em DESC
          LIMIT 200"
    );

    $itens = [];
    while ($c = $rs->fetch_assoc()) {
        $itens[] = [
            'id'              => (int) $c['id'],
            'titulo'          => $c['titulo'],
            'corpo'           => $c['corpo'],
            'imagem'          => $c['imagem'],
            'link'            => $c['link'],
            'destaque'        => (int) $c['destaque'],
            'status'          => $c['status'],
            'publicado_em'    => $c['publicado_em'],
            'expira_em'       => $c['expira_em'],
            'criado_por_nome' => $c['criado_por_nome'],
            'criado_em'       => $c['criado_em'],
            'leituras'        => (int) $c['leituras'],
        ];
    }

    echo json_encode(['status' => 'ok', 'comunicados' => $itens]);
} catch (Throwable $e) {
    error_log('Erro em painel/comunicados/listar.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
