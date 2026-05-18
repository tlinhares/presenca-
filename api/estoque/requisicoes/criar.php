<?php
/**
 * API - Criar Requisição de Materiais
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $usuario_id = $_SESSION['usuario_id'] ?? 0;

    $input = json_decode(file_get_contents('php://input'), true);

    $id_departamento_origem = !empty($input['id_departamento_origem']) ? intval($input['id_departamento_origem']) : null;
    $id_departamento_destino = !empty($input['id_departamento_destino']) ? intval($input['id_departamento_destino']) : null;
    $finalidade = $input['finalidade'] ?? 'consumo_interno';
    $prioridade = $input['prioridade'] ?? 'normal';
    $data_necessidade = !empty($input['data_necessidade']) ? $input['data_necessidade'] : null;
    $motivo = trim($input['motivo'] ?? '');
    $observacoes = trim($input['observacoes'] ?? $motivo);
    $solicitacao_texto = trim($input['solicitacao_texto'] ?? '');
    $itens = $input['itens'] ?? [];
    $status = $input['status'] ?? null;

    // Modo texto livre (solicitante): só texto, sem itens; departamento_destino é
    // definido depois pelo almoxarife no momento do lançamento.
    // Modo com itens (almoxarife criando direto): itens preenchidos; nesse caso
    // o departamento_destino é obrigatório.
    $modo_texto_livre = empty($itens) && $solicitacao_texto !== '';

    if (!$modo_texto_livre && empty($itens)) {
        throw new Exception('Descreva o que precisa no campo de solicitação ou adicione pelo menos um item');
    }
    if ($modo_texto_livre && mb_strlen($solicitacao_texto) < 5) {
        throw new Exception('Descreva sua solicitação com um pouco mais de detalhe');
    }
    if (!$modo_texto_livre && (!$id_departamento_destino || $id_departamento_destino <= 0)) {
        throw new Exception('Departamento de destino é obrigatório');
    }

    if (!in_array($finalidade, ['consumo_interno', 'evento', 'construcao', 'reforma', 'manutencao', 'outro'])) {
        $finalidade = 'consumo_interno';
    }
    if (!in_array($prioridade, ['baixa', 'normal', 'alta', 'urgente'])) {
        $prioridade = 'normal';
    }

    if ($modo_texto_livre) {
        $status = 'aguardando_lancamento';
    } else {
        if (!in_array($status, ['rascunho', 'pendente'])) {
            $status = 'pendente';
        }
    }

    $conn->begin_transaction();

    try {
        // Inserir requisição (número é gerado pelo trigger)
        $sql = "INSERT INTO estoque_requisicoes
                (id_departamento_origem, id_departamento_destino, id_solicitante,
                 finalidade, prioridade, data_necessidade, motivo, solicitacao_texto,
                 status, observacoes_solicitante)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Erro ao preparar inserção: ' . $conn->error);
        }
        $solicitacao_texto_db = $modo_texto_livre ? $solicitacao_texto : null;
        // bind_param não aceita null direto em "i" — usar variável temporária
        $dest_param = $id_departamento_destino;
        $stmt->bind_param(
            "iiisssssss",
            $id_departamento_origem, $dest_param, $usuario_id,
            $finalidade, $prioridade, $data_necessidade, $motivo,
            $solicitacao_texto_db, $status, $observacoes
        );
        if (!$stmt->execute()) {
            throw new Exception('Erro ao inserir requisição: ' . $stmt->error);
        }
        $id_requisicao = $conn->insert_id;

        // Inserir itens apenas no modo com itens
        $itensInseridos = 0;
        if (!$modo_texto_livre) {
            foreach ($itens as $item) {
                $id_produto = intval($item['id_produto'] ?? 0);
                $quantidade_solicitada = floatval($item['quantidade_solicitada'] ?? 0);
                $observacoes_item = trim($item['observacoes'] ?? '');

                if ($id_produto <= 0 || $quantidade_solicitada <= 0) {
                    continue;
                }

                $sql = "INSERT INTO estoque_requisicoes_itens
                        (id_requisicao, id_produto, quantidade_solicitada, observacoes, status)
                        VALUES (?, ?, ?, ?, 'pendente')";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception('Erro ao preparar inserção de item: ' . $conn->error);
                }
                $stmt->bind_param("iids", $id_requisicao, $id_produto, $quantidade_solicitada, $observacoes_item);
                if (!$stmt->execute()) {
                    throw new Exception('Erro ao inserir item: ' . $stmt->error);
                }
                $itensInseridos++;
            }

            if ($itensInseridos === 0) {
                throw new Exception('Nenhum item válido foi inserido');
            }
        }
        
        // Buscar número gerado
        $sql = "SELECT numero FROM estoque_requisicoes WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_requisicao);
        $stmt->execute();
        $numero = $stmt->get_result()->fetch_assoc()['numero'];
        
        $conn->commit();
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => "Requisição #$numero criada com sucesso!",
            'id' => $id_requisicao,
            'numero' => $numero
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Erro em requisicoes/criar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();

