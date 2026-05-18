<?php
/**
 * API - Importar NF e dar entrada nos produtos
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $dados = json_decode($_POST['dados'] ?? '{}', true);
    $departamento = isset($_POST['departamento']) ? intval($_POST['departamento']) : 0;
    $usuarioId = $_SESSION['usuario_id'];
    
    if (empty($dados) || !isset($dados['nf']) || !isset($dados['produtos'])) {
        throw new Exception('Dados inválidos');
    }
    
    if ($departamento <= 0) {
        throw new Exception('Departamento é obrigatório');
    }
    
    $conn->begin_transaction();
    
    // Verificar/criar fornecedor
    $fornecedorId = null;
    if (!empty($dados['fornecedor']['cnpj'])) {
        $cnpj = preg_replace('/[^0-9]/', '', $dados['fornecedor']['cnpj']); // Remover formatação
        
        $stmt = $conn->prepare("SELECT id FROM estoque_fornecedores WHERE cnpj = ?");
        if (!$stmt) {
            throw new Exception('Erro ao preparar consulta de fornecedor: ' . $conn->error);
        }
        $stmt->bind_param("s", $cnpj);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $fornecedorId = $result->fetch_assoc()['id'];
        } else {
            // Criar novo fornecedor
            $razaoSocial = $dados['fornecedor']['nome'] ?? 'Fornecedor não identificado';
            $nomeFantasia = null;
            
            $stmt = $conn->prepare("INSERT INTO estoque_fornecedores (razao_social, nome_fantasia, cnpj, inscricao_estadual) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception('Erro ao preparar inserção de fornecedor: ' . $conn->error);
            }
            $ie = $dados['fornecedor']['ie'] ?? null;
            $stmt->bind_param("ssss", $razaoSocial, $nomeFantasia, $cnpj, $ie);
            $stmt->execute();
            $fornecedorId = $conn->insert_id;
        }
    }
    
    // Inserir NF
    $dataEmissao = date('Y-m-d', strtotime(str_replace('/', '-', $dados['nf']['data_emissao'])));
    if (!$dataEmissao || $dataEmissao === '1970-01-01') {
        $dataEmissao = date('Y-m-d');
    }
    
    $cnpjEmitente = !empty($dados['fornecedor']['cnpj']) ? preg_replace('/[^0-9]/', '', $dados['fornecedor']['cnpj']) : null;
    $nomeEmitente = $dados['fornecedor']['nome'] ?? null;
    
    // Limpar e validar chave de acesso (deve ter 44 caracteres)
    $chaveAcesso = $dados['nf']['chave_acesso'] ?? '';
    // Remover prefixos como "NFe" ou outros caracteres não numéricos
    $chaveAcesso = preg_replace('/[^0-9]/', '', $chaveAcesso);
    // Se ainda tiver mais de 44 caracteres, pegar apenas os últimos 44
    if (strlen($chaveAcesso) > 44) {
        $chaveAcesso = substr($chaveAcesso, -44);
    }
    // Se tiver menos de 44 caracteres, pode ser que não seja uma chave válida
    if (strlen($chaveAcesso) < 44 && !empty($chaveAcesso)) {
        // Tentar buscar a chave completa do XML original se disponível
        // Por enquanto, vamos aceitar mesmo que seja menor (pode ser NF antiga)
    }
    // Se estiver vazia, definir como NULL
    if (empty($chaveAcesso)) {
        $chaveAcesso = null;
    }
    
    // Limitar tamanho do nome do emitente
    if ($nomeEmitente && strlen($nomeEmitente) > 200) {
        $nomeEmitente = substr($nomeEmitente, 0, 200);
    }
    
    $stmt = $conn->prepare("INSERT INTO estoque_notas_fiscais (numero, serie, chave_acesso, data_emissao, data_entrada, valor_total, id_fornecedor, cnpj_emitente, nome_emitente, id_departamento, id_usuario_importacao, status) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, 'processada')");
    if (!$stmt) {
        throw new Exception('Erro ao preparar inserção de NF: ' . $conn->error);
    }
    $stmt->bind_param("ssssdsssii", 
        $dados['nf']['numero'], 
        $dados['nf']['serie'], 
        $chaveAcesso,
        $dataEmissao,
        $dados['nf']['valor_total'],
        $fornecedorId,
        $cnpjEmitente,
        $nomeEmitente,
        $departamento,
        $usuarioId
    );
    if (!$stmt->execute()) {
        throw new Exception('Erro ao inserir NF: ' . $stmt->error);
    }
    $nfId = $conn->insert_id;
    
    // Buscar unidade padrão
    $stmt = $conn->prepare("SELECT id FROM estoque_unidades WHERE sigla = 'UN' OR sigla = 'un' LIMIT 1");
    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta de unidade: ' . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $unidadePadrao = $result->num_rows > 0 ? $result->fetch_assoc()['id'] : 1;
    
    // Buscar categoria padrão
    $stmt = $conn->prepare("SELECT id FROM estoque_categorias WHERE ativo = 1 LIMIT 1");
    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta de categoria: ' . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $categoriaPadrao = $result->num_rows > 0 ? $result->fetch_assoc()['id'] : null;
    
    if (!$categoriaPadrao) {
        throw new Exception('Nenhuma categoria ativa encontrada. Cadastre uma categoria antes de importar.');
    }
    
    // Processar produtos
    $produtosImportados = 0;
    foreach ($dados['produtos'] as $prod) {
        // Verificar se produto existe
        $produtoId = null;
        if (!empty($prod['codigo'])) {
            $stmt = $conn->prepare("SELECT id FROM estoque_produtos WHERE codigo = ? OR codigo_barras = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("ss", $prod['codigo'], $prod['codigo']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $produtoId = $result->fetch_assoc()['id'];
                }
            }
        }
        
        // Se não existe, criar
        if (!$produtoId) {
            $codigo = $prod['codigo'] ?? null;
            $nome = $prod['nome'] ?? 'Produto sem nome';
            $valorUnitario = floatval($prod['valor_unitario'] ?? 0);
            
            $stmt = $conn->prepare("INSERT INTO estoque_produtos (codigo, nome, id_categoria, id_unidade, id_departamento, quantidade_atual, valor_unitario) VALUES (?, ?, ?, ?, ?, 0, ?)");
            if (!$stmt) {
                throw new Exception('Erro ao preparar inserção de produto: ' . $conn->error);
            }
            $stmt->bind_param("ssiiid", $codigo, $nome, $categoriaPadrao, $unidadePadrao, $departamento, $valorUnitario);
            if (!$stmt->execute()) {
                throw new Exception('Erro ao inserir produto: ' . $stmt->error);
            }
            $produtoId = $conn->insert_id;
        }
        
        // Registrar movimentação de entrada
        $quantidade = floatval($prod['quantidade'] ?? 0);
        $valorUnitario = floatval($prod['valor_unitario'] ?? 0);
        $valorTotal = $quantidade * $valorUnitario;
        
        // Buscar quantidade anterior do produto
        $stmt = $conn->prepare("SELECT quantidade_atual FROM estoque_produtos WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Erro ao preparar consulta de quantidade: ' . $conn->error);
        }
        $stmt->bind_param("i", $produtoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $quantidadeAnterior = $result->num_rows > 0 ? floatval($result->fetch_assoc()['quantidade_atual']) : 0;
        $quantidadePosterior = $quantidadeAnterior + $quantidade;
        
        // Inserir movimentação
        // SQL: id_produto, id_departamento, tipo(fixo), quantidade, quantidade_anterior, quantidade_posterior, valor_unitario, valor_total, origem(fixo), id_nota_fiscal, id_usuario
        // Placeholders: ?, ?, ?, ?, ?, ?, ?, ?, ?, ? = 9 placeholders
        $stmt = $conn->prepare("INSERT INTO estoque_movimentacoes (id_produto, id_departamento, tipo, quantidade, quantidade_anterior, quantidade_posterior, valor_unitario, valor_total, origem, id_nota_fiscal, id_usuario) VALUES (?, ?, 'entrada', ?, ?, ?, ?, ?, 'nf_xml', ?, ?)");
        if (!$stmt) {
            throw new Exception('Erro ao preparar inserção de movimentação: ' . $conn->error);
        }
        // Tipos: i (produto), i (departamento), d (quantidade), d (anterior), d (posterior), d (unitario), d (total), i (nf), i (usuario) = 9 parâmetros
        $stmt->bind_param("iiddddiii", $produtoId, $departamento, $quantidade, $quantidadeAnterior, $quantidadePosterior, $valorUnitario, $valorTotal, $nfId, $usuarioId);
        if (!$stmt->execute()) {
            throw new Exception('Erro ao inserir movimentação: ' . $stmt->error);
        }
        
        // Atualizar quantidade do produto
        $stmt = $conn->prepare("UPDATE estoque_produtos SET quantidade_atual = ?, valor_unitario = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Erro ao preparar atualização de produto: ' . $conn->error);
        }
        $stmt->bind_param("ddi", $quantidadePosterior, $valorUnitario, $produtoId);
        if (!$stmt->execute()) {
            throw new Exception('Erro ao atualizar produto: ' . $stmt->error);
        }
        
        $produtosImportados++;
    }
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => "NF importada com sucesso! $produtosImportados produto(s) processado(s).",
        'nf_id' => $nfId
    ]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollback();
    error_log("Erro em nf/importar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

if (isset($conn)) $conn->close();

