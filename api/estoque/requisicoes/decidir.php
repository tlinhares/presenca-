<?php
/**
 * API - Aprovar/Rejeitar Requisição
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';

// Verifica se é admin OU tem acesso ao menu de autorização
$isAdmin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
$temAcessoAutorizacao = MenuPermissaoService::podeAcessar('estoque_autorizar_requisicoes');

if (!$isAdmin && !$temAcessoAutorizacao) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado. Você não tem permissão para autorizar requisições.']);
    exit;
}

try {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $decisao = $_POST['decisao'] ?? '';
    $observacoes = trim($_POST['observacoes'] ?? '');
    $usuarioId = $_SESSION['usuario_id'];
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    if (!in_array($decisao, ['aprovar', 'rejeitar'])) {
        throw new Exception('Decisão inválida');
    }
    
    // Verificar se requisição está pendente
    $sql = "SELECT status FROM estoque_requisicoes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Requisição não encontrada');
    }
    
    $req = $result->fetch_assoc();
    if ($req['status'] !== 'pendente') {
        throw new Exception('Requisição já foi processada');
    }
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        if ($decisao === 'aprovar') {
            // Buscar itens da requisição com dados do produto e departamento
            $sql_itens = "SELECT 
                            ri.id as id_item,
                            ri.id_produto,
                            ri.quantidade_solicitada,
                            p.quantidade_atual,
                            p.valor_unitario,
                            p.nome as produto_nome,
                            r.id_departamento_destino
                          FROM estoque_requisicoes_itens ri
                          JOIN estoque_produtos p ON ri.id_produto = p.id
                          JOIN estoque_requisicoes r ON ri.id_requisicao = r.id
                          WHERE ri.id_requisicao = ? AND ri.status = 'pendente'";
            $stmt_itens = $conn->prepare($sql_itens);
            if (!$stmt_itens) {
                throw new Exception('Erro ao preparar consulta de itens: ' . $conn->error);
            }
            $stmt_itens->bind_param("i", $id);
            $stmt_itens->execute();
            $result_itens = $stmt_itens->get_result();
            
            if ($result_itens->num_rows === 0) {
                throw new Exception('Nenhum item pendente encontrado na requisição');
            }
            
            $itens_processados = 0;
            $erros_estoque = [];
            
            while ($item = $result_itens->fetch_assoc()) {
                $id_item = $item['id_item'];
                $id_produto = $item['id_produto'];
                $quantidade_solicitada = floatval($item['quantidade_solicitada']);
                $quantidade_atual = floatval($item['quantidade_atual']);
                $valor_unitario = floatval($item['valor_unitario']);
                $id_departamento = $item['id_departamento_destino'];
                $produto_nome = $item['produto_nome'];
                
                // Quantidade aprovada = quantidade solicitada (pode ser ajustada no futuro)
                $quantidade_aprovada = $quantidade_solicitada;
                
                // Verificar se há estoque suficiente
                if ($quantidade_atual < $quantidade_aprovada) {
                    $erros_estoque[] = "$produto_nome: Disponível: $quantidade_atual, Solicitado: $quantidade_aprovada";
                    continue; // Pula este item mas continua processando os outros
                }
                
                // Calcular novas quantidades
                $quantidade_anterior = $quantidade_atual;
                $quantidade_posterior = $quantidade_atual - $quantidade_aprovada;
                
                // Atualizar estoque do produto (usar FOR UPDATE para lock)
                $sql_update_produto = "UPDATE estoque_produtos 
                                        SET quantidade_atual = ?, atualizado_em = NOW() 
                                        WHERE id = ? AND quantidade_atual >= ?";
                $stmt_update = $conn->prepare($sql_update_produto);
                if (!$stmt_update) {
                    throw new Exception('Erro ao preparar atualização de produto: ' . $conn->error);
                }
                $stmt_update->bind_param("did", $quantidade_posterior, $id_produto, $quantidade_aprovada);
                
                if (!$stmt_update->execute()) {
                    throw new Exception('Erro ao atualizar estoque do produto ID ' . $id_produto . ': ' . $stmt_update->error);
                }
                
                // Verificar se realmente atualizou (pode ter falhado por condição WHERE)
                if ($stmt_update->affected_rows === 0) {
                    $erros_estoque[] = "$produto_nome: Estoque insuficiente ou produto não encontrado";
                    continue;
                }
                
                // Atualizar item da requisição
                $sql_update_item = "UPDATE estoque_requisicoes_itens 
                                    SET quantidade_aprovada = ?, status = 'aprovado' 
                                    WHERE id = ?";
                $stmt_item = $conn->prepare($sql_update_item);
                if (!$stmt_item) {
                    throw new Exception('Erro ao preparar atualização de item: ' . $conn->error);
                }
                $stmt_item->bind_param("di", $quantidade_aprovada, $id_item);
                
                if (!$stmt_item->execute()) {
                    throw new Exception('Erro ao atualizar item da requisição: ' . $stmt_item->error);
                }
                
                // Criar movimentação de saída
                $tipo_movimentacao = 'saida';
                $origem_movimentacao = 'requisicao';
                $valor_total = $quantidade_aprovada * $valor_unitario;
                $obs_mov = "Saída por requisição #$id";
                
                $sql_movimentacao = "INSERT INTO estoque_movimentacoes 
                                    (id_produto, id_departamento, tipo, quantidade, 
                                     quantidade_anterior, quantidade_posterior,
                                     valor_unitario, valor_total, origem, id_requisicao, 
                                     observacoes, id_usuario)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_mov = $conn->prepare($sql_movimentacao);
                if (!$stmt_mov) {
                    throw new Exception('Erro ao preparar inserção de movimentação: ' . $conn->error);
                }
                $stmt_mov->bind_param("iisdddddsisi", 
                    $id_produto, $id_departamento, $tipo_movimentacao,
                    $quantidade_aprovada, $quantidade_anterior, $quantidade_posterior,
                    $valor_unitario, $valor_total, $origem_movimentacao, $id,
                    $obs_mov, $usuarioId
                );
                
                if (!$stmt_mov->execute()) {
                    throw new Exception('Erro ao criar movimentação: ' . $stmt_mov->error);
                }
                
                $itens_processados++;
            }
            
            if ($itens_processados === 0) {
                $conn->rollback();
                $mensagem_erro = 'Nenhum item pôde ser processado. ';
                if (!empty($erros_estoque)) {
                    $mensagem_erro .= 'Erros: ' . implode('; ', $erros_estoque);
                }
                throw new Exception($mensagem_erro);
            }
            
            // Determinar status final da requisição
            // Se todos os itens foram aprovados, status = 'aprovada'
            // Se alguns itens falharam, pode ser 'parcial' (mas por enquanto vamos manter 'aprovada')
            $novoStatus = 'aprovada';
            
            // Atualizar status da requisição
            $sql = "UPDATE estoque_requisicoes SET 
                        status = ?, 
                        id_aprovador = ?, 
                        data_aprovacao = NOW(),
                        observacoes_aprovador = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Erro ao preparar atualização de requisição: ' . $conn->error);
            }
            $stmt->bind_param("sisi", $novoStatus, $usuarioId, $observacoes, $id);
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao atualizar requisição: ' . $stmt->error);
            }
            
            // Commit da transação
            $conn->commit();
            
            $mensagem = "Requisição aprovada com sucesso! $itens_processados item(ns) processado(s).";
            if (!empty($erros_estoque)) {
                $mensagem .= " Atenção: Alguns itens não puderam ser processados: " . implode('; ', $erros_estoque);
            }
            
        } else {
            // Rejeitar requisição
            $novoStatus = 'rejeitada';
            
            $sql = "UPDATE estoque_requisicoes SET 
                        status = ?, 
                        id_aprovador = ?, 
                        data_aprovacao = NOW(),
                        observacoes_aprovador = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Erro ao preparar atualização de requisição: ' . $conn->error);
            }
            $stmt->bind_param("sisi", $novoStatus, $usuarioId, $observacoes, $id);
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao atualizar requisição: ' . $stmt->error);
            }
            
            // Atualizar status dos itens para 'cancelado'
            $sql_itens_cancelar = "UPDATE estoque_requisicoes_itens 
                                   SET status = 'cancelado' 
                                   WHERE id_requisicao = ? AND status = 'pendente'";
            $stmt_cancelar = $conn->prepare($sql_itens_cancelar);
            if ($stmt_cancelar) {
                $stmt_cancelar->bind_param("i", $id);
                $stmt_cancelar->execute();
            }
            
            // Commit da transação
            $conn->commit();
            
            $mensagem = 'Requisição rejeitada com sucesso';
        }
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => $mensagem
        ]);
        
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Erro em requisicoes/decidir.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();



