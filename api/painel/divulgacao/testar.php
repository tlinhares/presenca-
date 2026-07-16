<?php
/**
 * POST /api/painel/divulgacao/testar.php
 * Body JSON: { canais: ["whatsapp","email"] }
 *
 * Envia a divulgação de TESTE para o próprio admin logado, usando a config
 * salva. Não usa a fila — envia na hora, pra validar antes do disparo em massa.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../auth/verifica_sessao_ajax.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/DivulgacaoService.php';

MenuPermissaoService::exigirAdmin();

try {
    $input  = json_decode(file_get_contents('php://input'), true) ?: [];
    $canais = array_values(array_intersect((array) ($input['canais'] ?? []), ['whatsapp', 'email']));
    if (empty($canais)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Selecione ao menos um canal (WhatsApp ou E-mail)']);
        exit;
    }

    $cfg = $conn->query("SELECT link_android, link_ios, assunto, mensagem FROM divulgacao_config WHERE id = 1")->fetch_assoc();
    if (!$cfg || trim($cfg['mensagem']) === '') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Salve a configuração (mensagem) antes de testar']);
        exit;
    }

    $uid = (int) $_SESSION['usuario_id'];
    $stmt = $conn->prepare("SELECT nome, email, telefone FROM usuarios WHERE id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $mensagem = DivulgacaoService::render($cfg['mensagem'], $u['nome'], $cfg['link_android'], $cfg['link_ios']);

    $resultados = [];
    if (in_array('whatsapp', $canais, true)) {
        $tel = trim((string) $u['telefone']);
        if (strlen(preg_replace('/\D/', '', $tel)) < 10) {
            $resultados['whatsapp'] = ['sucesso' => false, 'mensagem' => 'Seu usuário não tem telefone válido cadastrado'];
        } else {
            $resultados['whatsapp'] = DivulgacaoService::enviarWhatsapp($conn, $tel, $mensagem, $uid, $u['nome']);
        }
    }
    if (in_array('email', $canais, true)) {
        $resultados['email'] = DivulgacaoService::enviarEmail($conn, $u['email'], $cfg['assunto'], $mensagem, $uid, $u['nome']);
    }

    $tudo_ok = true;
    foreach ($resultados as $r) if (empty($r['sucesso'])) $tudo_ok = false;

    echo json_encode([
        'status' => $tudo_ok ? 'ok' : 'parcial',
        'mensagem' => $tudo_ok
            ? 'Teste enviado para você (' . implode(' + ', array_keys($resultados)) . '). Confira seu celular/caixa de entrada.'
            : 'Teste concluído com falhas — veja os detalhes.',
        'resultados' => $resultados,
        'preview' => $mensagem,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Erro em divulgacao/testar.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
