<?php
/**
 * API - Buscar Requisição por ID
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Buscar requisição
    $sql = "SELECT
                r.id,
                r.numero,
                r.status,
                r.prioridade,
                r.motivo,
                r.finalidade,
                r.solicitacao_texto,
                r.resposta_almoxarife,
                r.observacoes_solicitante,
                r.observacoes_aprovador,
                r.data_solicitacao,
                r.data_necessidade,
                r.data_aprovacao,
                r.data_entrega,
                r.data_lancamento_itens,
                r.id_departamento_destino,
                DATE_FORMAT(r.data_solicitacao, '%d/%m/%Y %H:%i') as data_formatada,
                DATE_FORMAT(r.data_lancamento_itens, '%d/%m/%Y %H:%i') as data_lancamento_formatada,
                do.nome as departamento_origem,
                dd.nome as departamento_destino,
                us.nome as solicitante,
                ua.nome as aprovador,
                ual.nome as almoxarife_lancamento
            FROM estoque_requisicoes r
            LEFT JOIN estoque_departamentos do ON r.id_departamento_origem = do.id
            LEFT JOIN estoque_departamentos dd ON r.id_departamento_destino = dd.id
            JOIN usuarios us ON r.id_solicitante = us.id
            LEFT JOIN usuarios ua ON r.id_aprovador = ua.id
            LEFT JOIN usuarios ual ON r.id_almoxarife_lancamento = ual.id
            WHERE r.id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Requisição não encontrada');
    }
    
    $requisicao = $result->fetch_assoc();
    
    // Buscar itens
    $sql_itens = "SELECT 
                    ri.id,
                    ri.quantidade_solicitada,
                    ri.quantidade_aprovada,
                    ri.quantidade_entregue,
                    ri.observacoes,
                    ri.status as status_item,
                    p.nome as produto_nome,
                    p.codigo as produto_codigo,
                    p.id as produto_id,
                    u.sigla as unidade_sigla,
                    u.nome as unidade_nome,
                    c.nome as categoria_nome
                FROM estoque_requisicoes_itens ri
                JOIN estoque_produtos p ON ri.id_produto = p.id
                JOIN estoque_unidades u ON p.id_unidade = u.id
                LEFT JOIN estoque_categorias c ON p.id_categoria = c.id
                WHERE ri.id_requisicao = ?
                ORDER BY p.nome";
    
    $stmt = $conn->prepare($sql_itens);
    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta de itens: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result_itens = $stmt->get_result();
    
    $itens = [];
    while ($item = $result_itens->fetch_assoc()) {
        $itens[] = $item;
    }
    
    // Formatar número da requisição
    if (empty($requisicao['numero'])) {
        $requisicao['numero'] = 'REQ-' . str_pad($requisicao['id'], 6, '0', STR_PAD_LEFT);
    }
    
    $requisicao['itens'] = $itens;
    $requisicao['total_itens'] = count($itens);
    
    echo json_encode([
        'status' => 'ok',
        'requisicao' => $requisicao
    ]);

} catch (Exception $e) {
    error_log("Erro em requisicoes/buscar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();

