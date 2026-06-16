<?php
/**
 * POST /api/painel/notificacoes_push/teste.php
 * Body JSON: { id_usuario | email, titulo, corpo, dados?: object }
 *
 * Dispara uma notificação push para todos os dispositivos ativos do usuário.
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/PushNotificationService.php';

MenuPermissaoService::exigirAdmin();

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $id_usuario = (int) ($input['id_usuario'] ?? 0);
    $email      = trim($input['email'] ?? '');
    $titulo     = trim($input['titulo'] ?? '');
    $corpo      = trim($input['corpo'] ?? '');
    $dados      = is_array($input['dados'] ?? null) ? $input['dados'] : [];

    if ($titulo === '' || $corpo === '') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Título e corpo são obrigatórios']);
        exit;
    }
    if ($id_usuario <= 0 && $email === '') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Informe id_usuario ou email do destinatário']);
        exit;
    }

    if ($id_usuario <= 0) {
        $stmt = $conn->prepare("SELECT id, nome FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$u) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não encontrado pelo e-mail informado']);
            exit;
        }
        $id_usuario = (int) $u['id'];
        $nome_dest = $u['nome'];
    } else {
        $stmt = $conn->prepare("SELECT nome FROM usuarios WHERE id = ? AND ativo = 1 LIMIT 1");
        $stmt->bind_param('i', $id_usuario);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$u) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não encontrado']);
            exit;
        }
        $nome_dest = $u['nome'];
    }

    if (!PushNotificationService::estaConfigurado($conn)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Push notifications não estão configuradas (ativo + Service Account + project_id)']);
        exit;
    }

    $r = PushNotificationService::enviarParaUsuario($conn, $id_usuario, $titulo, $corpo, $dados);

    echo json_encode([
        'status' => 'ok',
        'destinatario' => ['id' => $id_usuario, 'nome' => $nome_dest],
        'resultado' => $r,
        'mensagem' => $r['dispositivos'] === 0
            ? 'Usuário não tem dispositivos registrados — peça pra ele abrir o app autenticado.'
            : sprintf('Enviado para %d/%d dispositivo(s) do usuário', $r['enviados'], $r['dispositivos']),
    ]);
} catch (Throwable $e) {
    error_log('Erro em notificacoes_push/teste.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
