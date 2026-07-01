<?php
/**
 * GET /api/painel/push_envios/buscar_usuarios.php?q=<termo>
 * Autocomplete de usuários pra escolher destinatário.
 * Retorna somente usuários ativos que têm ao menos 1 dispositivo push ativo
 * (fora disso, o envio sairia silencioso).
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAdmin();

try {
    $q = trim($_GET['q'] ?? '');
    $limite = min(30, max(1, (int) ($_GET['limite'] ?? 20)));

    // Busca TODOS os usuários ativos do sistema (não filtra por dispositivo push),
    // e devolve dispositivos_ativos como informação. Assim o admin consegue
    // mandar push para qualquer usuário — se dispositivos_ativos=0, o envio sai
    // silencioso mas o admin fica sabendo que precisa pedir pra pessoa abrir o
    // app (token rotacionou e não foi re-registrado).
    $sql = "SELECT u.id, u.nome, u.email,
                   (SELECT COUNT(*) FROM notificacoes_push_dispositivos
                     WHERE id_usuario = u.id AND ativo = 1) AS dispositivos_ativos,
                   (SELECT COUNT(*) FROM notificacoes_push_dispositivos
                     WHERE id_usuario = u.id) AS dispositivos_historicos
              FROM usuarios u
             WHERE u.ativo = 1";
    if ($q !== '') {
        $sql .= " AND (u.nome LIKE ? OR u.email LIKE ? OR u.cpf LIKE ?)";
        // Prioriza quem tem dispositivo ativo no topo, depois quem já teve algum.
        $sql .= " ORDER BY dispositivos_ativos DESC, dispositivos_historicos DESC, u.nome ASC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $like = '%' . $q . '%';
        $stmt->bind_param('sssi', $like, $like, $like, $limite);
    } else {
        $sql .= " ORDER BY dispositivos_ativos DESC, u.nome ASC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $limite);
    }
    $stmt->execute();
    $r = $stmt->get_result();
    $usuarios = [];
    while ($u = $r->fetch_assoc()) {
        $usuarios[] = [
            'id'                    => (int) $u['id'],
            'nome'                  => $u['nome'],
            'email'                 => $u['email'],
            'dispositivos_ativos'   => (int) $u['dispositivos_ativos'],
            'dispositivos_historicos' => (int) $u['dispositivos_historicos'],
        ];
    }
    $stmt->close();

    // Contagem total de alcançáveis pra facilitar o botão "todos"
    $tot = (int) $conn->query("SELECT COUNT(DISTINCT id_usuario) t FROM notificacoes_push_dispositivos WHERE ativo = 1")->fetch_assoc()['t'];

    echo json_encode([
        'status'   => 'ok',
        'usuarios' => $usuarios,
        'total_alcancaveis' => $tot,
    ]);
} catch (Throwable $e) {
    error_log('Erro em push_envios/buscar_usuarios.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
