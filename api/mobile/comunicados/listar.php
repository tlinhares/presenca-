<?php
/**
 * API Mobile — Avisos / Comunicados internos
 *
 * Endpoint: GET /api/mobile/comunicados/listar.php
 * Header:   Authorization: Bearer <access_token>
 *
 * Query (opcional):
 *   limite  — máx. de itens (default 50, teto 100)
 *
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "nao_lidos": 2,                       // pro badge da aba Avisos
 *     "destaque": { ...comunicado... },     // banner da Home (null se não houver)
 *     "comunicados": [
 *       {
 *         "id": 5,
 *         "titulo": "...",
 *         "corpo": "...",
 *         "imagem_url": "https://presenca.aom.org.br/uploads/comunicados/....jpg" | null,
 *         "link": "https://..." | null,
 *         "destaque": true,
 *         "publicado_em": "2026-07-02 09:00:00",
 *         "lido": false
 *       }, ...
 *     ]
 *   }
 * }
 *
 * Só devolve comunicados com status='publicado' e não expirados,
 * do mais recente pro mais antigo. `destaque` = o publicado mais
 * recente com flag destaque=1 (mesmo critério da lista).
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/middleware/mobile_auth.php';
require_once __DIR__ . '/../utils/response.php';

if (!isset($_SESSION['usuario_id'])) {
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode(MobileResponse::unauthorized('Sua sessão expirou. Faça logoff e login novamente para continuar.'));
        exit;
    }
}

date_default_timezone_set('America/Cuiaba');

try {
    $id_usuario = (int) $_SESSION['usuario_id'];
    $limite = min(100, max(1, (int) ($_GET['limite'] ?? 50)));

    $base_url = 'https://presenca.aom.org.br';
    $agora = date('Y-m-d H:i:s');

    $stmt = $conn->prepare(
        "SELECT c.id, c.titulo, c.corpo, c.imagem, c.link, c.destaque, c.publicado_em,
                (l.id IS NOT NULL) AS lido
           FROM comunicados c
      LEFT JOIN comunicados_leituras l
             ON l.id_comunicado = c.id AND l.id_usuario = ?
          WHERE c.status = 'publicado'
            AND (c.expira_em IS NULL OR c.expira_em > ?)
       ORDER BY c.publicado_em DESC
          LIMIT ?"
    );
    $stmt->bind_param('isi', $id_usuario, $agora, $limite);
    $stmt->execute();
    $rs = $stmt->get_result();

    $comunicados = [];
    $nao_lidos = 0;
    $destaque = null;
    while ($c = $rs->fetch_assoc()) {
        $item = [
            'id'           => (int) $c['id'],
            'titulo'       => $c['titulo'],
            'corpo'        => $c['corpo'],
            'imagem_url'   => $c['imagem'] ? $base_url . $c['imagem'] : null,
            'link'         => $c['link'],
            'destaque'     => (bool) $c['destaque'],
            'publicado_em' => $c['publicado_em'],
            'lido'         => (bool) $c['lido'],
        ];
        $comunicados[] = $item;
        if (!$item['lido']) $nao_lidos++;
        if ($destaque === null && $item['destaque']) $destaque = $item;
    }
    $stmt->close();

    echo json_encode(MobileResponse::success([
        'nao_lidos'   => $nao_lidos,
        'destaque'    => $destaque,
        'comunicados' => $comunicados,
    ], 'Comunicados recuperados com sucesso'));

} catch (Throwable $e) {
    error_log('Erro em mobile/comunicados/listar.php: ' . $e->getMessage());
    echo json_encode(MobileResponse::serverError('Erro ao buscar comunicados'));
}

$conn->close();
