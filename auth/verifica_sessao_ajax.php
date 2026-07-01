<?php
/**
 * Igual ao verifica_sessao.php, mas em vez de fazer redirect (302 → HTML)
 * quando a sessão expira, responde 401 com JSON. Use este include em qualquer
 * endpoint que é chamado via AJAX pelo painel — assim o frontend consegue
 * mostrar "sessão expirada, faça login" em vez de "erro de comunicação"
 * (o browser seguiria o redirect e o AJAX receberia HTML do login).
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(401);
    echo json_encode([
        'status'  => 'erro',
        'success' => false,
        'code'    => 'sessao_expirada',
        'mensagem' => 'Sessão expirada. Recarregue a página e faça login novamente.',
        'message' => 'Sessão expirada. Recarregue a página e faça login novamente.',
    ]);
    exit;
}
