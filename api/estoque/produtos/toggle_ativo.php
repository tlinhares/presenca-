<?php
/**
 * POST /api/estoque/produtos/toggle_ativo.php
 * Body JSON: { id: <id_produto>, ativo: 0|1 }
 *
 * Marca um produto como ativo ou inativo. Produtos inativos somem das
 * listagens padrão e param de gerar alertas de estoque baixo (o
 * alertas.php filtra WHERE ativo = 1).
 *
 * Permissão: almoxarife (estoque_responsaveis OU grupo com
 * estoque_autorizar_requisicoes) ou admin do sistema.
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../almoxarife_helper.php';

try {
    $usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
    $is_admin   = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';

    if (!eh_almoxarife($conn, $usuario_id, $is_admin)) {
        http_response_code(403);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Sem permissão para alterar produtos']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $id    = (int)($input['id'] ?? $_POST['id'] ?? 0);
    // aceita 'ativo' explícito; se ausente, faz toggle do valor atual
    $ativoInformado = array_key_exists('ativo', $input) || array_key_exists('ativo', $_POST);
    $ativo = (int)($input['ativo'] ?? $_POST['ativo'] ?? 0);

    if ($id <= 0) {
        throw new Exception('ID do produto inválido');
    }

    // Carregar estado atual
    $stmt = $conn->prepare("SELECT id, nome, ativo FROM estoque_produtos WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $prod = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$prod) {
        throw new Exception('Produto não encontrado');
    }

    // Se não informou 'ativo', alterna
    $novoAtivo = $ativoInformado ? ($ativo ? 1 : 0) : ((int)$prod['ativo'] === 1 ? 0 : 1);

    $stmt = $conn->prepare("UPDATE estoque_produtos SET ativo = ? WHERE id = ?");
    $stmt->bind_param('ii', $novoAtivo, $id);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Erro ao atualizar produto: ' . $conn->error);
    }
    $stmt->close();

    echo json_encode([
        'status'   => 'ok',
        'id'       => $id,
        'ativo'    => $novoAtivo,
        'mensagem' => 'Produto "' . $prod['nome'] . '" ' . ($novoAtivo ? 'reativado' : 'inativado') . ' com sucesso',
    ]);

} catch (Exception $e) {
    error_log('Erro em produtos/toggle_ativo.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
