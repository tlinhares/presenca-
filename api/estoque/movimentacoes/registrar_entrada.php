<?php
/**
 * API - Registrar Entrada de Produtos no Estoque
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $usuario_id = $_SESSION['usuario_id'] ?? 0;
    
    // Receber dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id_departamento = intval($input['id_departamento'] ?? 0);
    $itens = $input['itens'] ?? [];
    $observacoes_geral = trim($input['observacoes'] ?? '');
    $id_nota_fiscal = isset($input['id_nota_fiscal']) ? intval($input['id_nota_fiscal']) : null;
    $origem = $input['origem'] ?? 'manual';
    
    // Validações
    if ($id_departamento <= 0) {
        throw new Exception('Departamento inválido');
    }
    
    if (empty($itens)) {
        throw new Exception('Nenhum item informado');
    }
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        $processados = 0;
        
        foreach ($itens as $item) {
            $id_produto = intval($item['id_produto'] ?? 0);
            $quantidade = floatval($item['quantidade'] ?? 0);
            $valor_unitario = floatval($item['valor_unitario'] ?? 0);
            $lote = trim($item['lote'] ?? '');
            $validade = !empty($item['validade']) ? $item['validade'] : null;
            $observacoes_item = trim($item['observacoes'] ?? '');
            
            if ($id_produto <= 0 || $quantidade <= 0) {
                continue;
            }
            
            // Buscar dados atuais do produto
            $sql = "SELECT quantidade_atual, valor_medio FROM estoque_produtos WHERE id = ? FOR UPDATE";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id_produto);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Produto ID $id_produto não encontrado");
            }
            
            $produto = $result->fetch_assoc();
            $quantidade_anterior = floatval($produto['quantidade_atual']);
            $valor_medio_anterior = floatval($produto['valor_medio']);
            
            // Calcular novo estoque
            $quantidade_posterior = $quantidade_anterior + $quantidade;
            
            // Calcular novo valor médio ponderado
            $valor_total_anterior = $quantidade_anterior * $valor_medio_anterior;
            $valor_total_entrada = $quantidade * $valor_unitario;
            $novo_valor_medio = $quantidade_posterior > 0 
                ? ($valor_total_anterior + $valor_total_entrada) / $quantidade_posterior 
                : $valor_unitario;
            
            // Atualizar estoque do produto
            $sql = "UPDATE estoque_produtos SET 
                        quantidade_atual = ?,
                        valor_medio = ?,
                        valor_unitario = CASE WHEN valor_unitario = 0 THEN ? ELSE valor_unitario END,
                        atualizado_em = NOW()
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dddi", $quantidade_posterior, $novo_valor_medio, $valor_unitario, $id_produto);
            $stmt->execute();
            
            // Registrar movimentação
            $tipo = 'entrada';
            $valor_total = $quantidade * $valor_unitario;
            $obs = $observacoes_item ?: $observacoes_geral;
            
            $sql = "INSERT INTO estoque_movimentacoes 
                    (id_produto, id_departamento, tipo, quantidade, quantidade_anterior, quantidade_posterior,
                     valor_unitario, valor_total, origem, id_nota_fiscal, lote, data_validade, observacoes, id_usuario)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisdddddsisisi", 
                $id_produto, $id_departamento, $tipo, $quantidade, 
                $quantidade_anterior, $quantidade_posterior,
                $valor_unitario, $valor_total, $origem, $id_nota_fiscal,
                $lote, $validade, $obs, $usuario_id
            );
            $stmt->execute();
            
            $processados++;
        }
        
        // Confirmar transação
        $conn->commit();
        
        echo json_encode([
            'status' => 'ok',
            'mensagem' => "$processados produto(s) adicionado(s) ao estoque com sucesso!",
            'processados' => $processados
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Erro em registrar_entrada.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();



