<?php
/**
 * API - Lançar Itens em Requisição (almoxarife)
 *
 * Converte uma requisição em modo "aguardando_lancamento" (texto livre) em
 * uma requisição com itens formais vinculados ao catálogo de produtos.
 * Transição: aguardando_lancamento -> pendente
 *
 * Body (JSON):
 *   id: int (id da requisição)
 *   itens: [ { id_produto, quantidade_solicitada, observacoes? }, ... ]
 *   resposta_almoxarife: string (opcional, resposta visível ao solicitante)
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../almoxarife_helper.php';

try {
    $usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
    $eh_admin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';

    if (!eh_almoxarife($conn, $usuario_id, $eh_admin)) {
        http_response_code(403);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Apenas almoxarifes podem lançar itens em uma requisição']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $id_requisicao = (int)($input['id'] ?? 0);
    $itens = $input['itens'] ?? [];
    $resposta_almoxarife = trim($input['resposta_almoxarife'] ?? '');
    $id_departamento_destino = !empty($input['id_departamento_destino']) ? intval($input['id_departamento_destino']) : null;

    if ($id_requisicao <= 0) {
        throw new Exception('ID da requisição é obrigatório');
    }
    if (empty($itens) && $resposta_almoxarife === '') {
        throw new Exception('Adicione pelo menos um item ou registre uma resposta ao solicitante');
    }
    // Se o almoxarife está lançando itens, precisa indicar de qual almoxarifado sairão
    if (!empty($itens) && (!$id_departamento_destino || $id_departamento_destino <= 0)) {
        throw new Exception('Selecione o almoxarifado de onde os produtos sairão');
    }

    // Carregar requisição e validar estado
    $stmt = $conn->prepare("SELECT status FROM estoque_requisicoes WHERE id = ?");
    $stmt->bind_param("i", $id_requisicao);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        throw new Exception('Requisição não encontrada');
    }
    if ($r['status'] !== 'aguardando_lancamento') {
        throw new Exception("Esta requisição não está aguardando lançamento (status atual: {$r['status']})");
    }

    $conn->begin_transaction();
    try {
        // Inserir itens (pode ser zero se o almoxarife está apenas respondendo
        // que vai pedir o produto / não atende no momento; nesse caso a requisição
        // segue para 'pendente' sem itens — quem aprovar decide o destino).
        $itensInseridos = 0;
        foreach ($itens as $item) {
            $id_produto = (int)($item['id_produto'] ?? 0);
            $quantidade_solicitada = (float)($item['quantidade_solicitada'] ?? 0);
            $observacoes_item = trim($item['observacoes'] ?? '');

            if ($id_produto <= 0 || $quantidade_solicitada <= 0) {
                continue;
            }

            $sql = "INSERT INTO estoque_requisicoes_itens
                    (id_requisicao, id_produto, quantidade_solicitada, observacoes, status)
                    VALUES (?, ?, ?, ?, 'pendente')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iids", $id_requisicao, $id_produto, $quantidade_solicitada, $observacoes_item);
            if (!$stmt->execute()) {
                throw new Exception('Erro ao inserir item: ' . $stmt->error);
            }
            $stmt->close();
            $itensInseridos++;
        }

        // Atualizar requisição: marcar quem lançou, quando, resposta, departamento destino e novo status
        $sql = "UPDATE estoque_requisicoes
                   SET id_almoxarife_lancamento = ?,
                       data_lancamento_itens = NOW(),
                       resposta_almoxarife = ?,
                       id_departamento_destino = COALESCE(?, id_departamento_destino),
                       status = 'pendente'
                 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $resposta_db = $resposta_almoxarife !== '' ? $resposta_almoxarife : null;
        $dest_param = $id_departamento_destino;
        $stmt->bind_param("isii", $usuario_id, $resposta_db, $dest_param, $id_requisicao);
        if (!$stmt->execute()) {
            throw new Exception('Erro ao atualizar requisição: ' . $stmt->error);
        }
        $stmt->close();

        $conn->commit();

        echo json_encode([
            'status' => 'ok',
            'mensagem' => $itensInseridos > 0
                ? "$itensInseridos item(ns) lançado(s). Requisição enviada para aprovação."
                : 'Resposta registrada e requisição enviada para aprovação.',
            'itens_inseridos' => $itensInseridos
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Erro em requisicoes/lancar_itens.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
