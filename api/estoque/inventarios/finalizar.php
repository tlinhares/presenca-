<?php
/**
 * API - Finalizar Inventário
 * Ao finalizar, ajusta automaticamente as quantidades dos produtos com diferenças encontradas
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $usuario_id = $_SESSION['usuario_id'] ?? 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    if ($usuario_id <= 0) {
        throw new Exception('Usuário não identificado');
    }
    
    // Verificar se inventário existe e está em andamento, e buscar id_departamento
    $sql_check = "SELECT id_departamento, status FROM estoque_inventarios WHERE id = ?";
    $stmt_check = $conn->prepare($sql_check);
    if (!$stmt_check) {
        throw new Exception('Erro ao preparar query: ' . $conn->error);
    }
    
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        throw new Exception('Inventário não encontrado');
    }
    
    $inventario = $result_check->fetch_assoc();
    if ($inventario['status'] !== 'em_andamento') {
        throw new Exception('Inventário já foi finalizado ou cancelado');
    }
    
    $id_departamento = intval($inventario['id_departamento']);
    $stmt_check->close();
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // Buscar todos os itens do inventário que têm diferença (quantidade contada diferente do sistema)
        $sql_itens = "SELECT 
                        ii.id as id_item,
                        ii.id_produto,
                        ii.quantidade_sistema,
                        ii.quantidade_contada,
                        ii.diferenca,
                        p.quantidade_atual,
                        p.valor_unitario,
                        p.nome as produto_nome
                      FROM estoque_inventarios_itens ii
                      JOIN estoque_produtos p ON ii.id_produto = p.id
                      WHERE ii.id_inventario = ? 
                        AND ii.quantidade_contada IS NOT NULL
                        AND ii.diferenca != 0
                        AND ii.ajustado = 0";
        
        $stmt_itens = $conn->prepare($sql_itens);
        if (!$stmt_itens) {
            throw new Exception('Erro ao preparar query de itens: ' . $conn->error);
        }
        
        $stmt_itens->bind_param("i", $id);
        $stmt_itens->execute();
        $result_itens = $stmt_itens->get_result();
        
        $itens_ajustados = 0;
        $erros_ajuste = [];
        
        // Processar cada item com diferença
        while ($item = $result_itens->fetch_assoc()) {
            $id_item = $item['id_item'];
            $id_produto = $item['id_produto'];
            $quantidade_sistema = floatval($item['quantidade_sistema']);
            $quantidade_contada = floatval($item['quantidade_contada']);
            $quantidade_anterior = floatval($item['quantidade_atual']);
            $valor_unitario = floatval($item['valor_unitario']);
            $produto_nome = $item['produto_nome'];
            
            // A quantidade posterior será a quantidade contada no inventário
            $quantidade_posterior = $quantidade_contada;
            
            // Atualizar quantidade do produto
            $sql_update_produto = "UPDATE estoque_produtos 
                                  SET quantidade_atual = ?, atualizado_em = NOW() 
                                  WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update_produto);
            if (!$stmt_update) {
                $erros_ajuste[] = "$produto_nome: Erro ao preparar atualização";
                continue;
            }
            
            $stmt_update->bind_param("di", $quantidade_posterior, $id_produto);
            if (!$stmt_update->execute()) {
                $erros_ajuste[] = "$produto_nome: Erro ao atualizar estoque - " . $stmt_update->error;
                $stmt_update->close();
                continue;
            }
            $stmt_update->close();
            
            // Criar movimentação de ajuste
            $tipo_movimentacao = 'ajuste';
            $origem_movimentacao = 'ajuste_inventario';
            $quantidade_ajuste = abs($quantidade_contada - $quantidade_sistema);
            $valor_total = $quantidade_ajuste * $valor_unitario;
            $obs_mov = "Ajuste de inventário #$id. Sistema: $quantidade_sistema, Contado: $quantidade_contada";
            
            $sql_movimentacao = "INSERT INTO estoque_movimentacoes 
                                (id_produto, id_departamento, tipo, quantidade, 
                                 quantidade_anterior, quantidade_posterior,
                                 valor_unitario, valor_total, origem, 
                                 observacoes, id_usuario)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_mov = $conn->prepare($sql_movimentacao);
            if (!$stmt_mov) {
                $erros_ajuste[] = "$produto_nome: Erro ao preparar movimentação";
                continue;
            }
            
            $stmt_mov->bind_param("iisddddsssi", 
                $id_produto, $id_departamento, $tipo_movimentacao,
                $quantidade_ajuste, $quantidade_anterior, $quantidade_posterior,
                $valor_unitario, $valor_total, $origem_movimentacao,
                $obs_mov, $usuario_id
            );
            
            if (!$stmt_mov->execute()) {
                $erros_ajuste[] = "$produto_nome: Erro ao criar movimentação - " . $stmt_mov->error;
                $stmt_mov->close();
                continue;
            }
            
            $id_movimentacao = $conn->insert_id;
            $stmt_mov->close();
            
            // Atualizar item do inventário marcando como ajustado
            $sql_update_item = "UPDATE estoque_inventarios_itens 
                               SET ajustado = 1, id_movimentacao_ajuste = ?
                               WHERE id = ?";
            $stmt_update_item = $conn->prepare($sql_update_item);
            if (!$stmt_update_item) {
                $erros_ajuste[] = "$produto_nome: Erro ao preparar atualização de item";
                continue;
            }
            
            $stmt_update_item->bind_param("ii", $id_movimentacao, $id_item);
            if (!$stmt_update_item->execute()) {
                $erros_ajuste[] = "$produto_nome: Erro ao atualizar item - " . $stmt_update_item->error;
                $stmt_update_item->close();
                continue;
            }
            $stmt_update_item->close();
            
            $itens_ajustados++;
        }
        
        $stmt_itens->close();
        
        // Atualizar status do inventário
        $sql = "UPDATE estoque_inventarios SET
                    status = 'finalizado',
                    data_fim = NOW(),
                    id_usuario_fim = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Erro ao preparar query: ' . $conn->error);
        }
        
        $stmt->bind_param("ii", $usuario_id, $id);
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao executar query: ' . $stmt->error);
        }
        
        $stmt->close();
        
        // Commit da transação
        $conn->commit();
        
        // Mensagem de sucesso
        $mensagem = "Inventário finalizado com sucesso!";
        if ($itens_ajustados > 0) {
            $mensagem .= " $itens_ajustados produto(s) ajustado(s) no estoque.";
        } else {
            $mensagem .= " Nenhuma diferença encontrada para ajustar.";
        }
        
        if (!empty($erros_ajuste)) {
            $mensagem .= " Atenção: Alguns produtos não puderam ser ajustados: " . implode('; ', $erros_ajuste);
        }
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => $mensagem,
            'itens_ajustados' => $itens_ajustados
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Erro em inventarios/finalizar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();

