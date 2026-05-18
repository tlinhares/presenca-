<?php
/**
 * API - Salvar/Atualizar Produto
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $codigo = trim($_POST['codigo'] ?? '');
    $codigo_barras = trim($_POST['codigo_barras'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $id_categoria = !empty($_POST['id_categoria']) ? intval($_POST['id_categoria']) : null;
    $id_unidade = intval($_POST['id_unidade'] ?? 0);
    $id_departamento = intval($_POST['id_departamento'] ?? 0);
    $id_localizacao = !empty($_POST['id_localizacao']) ? intval($_POST['id_localizacao']) : null;
    $quantidade_minima = floatval($_POST['quantidade_minima'] ?? 0);
    $quantidade_ideal = floatval($_POST['quantidade_ideal'] ?? 0);
    $quantidade_maxima = !empty($_POST['quantidade_maxima']) ? floatval($_POST['quantidade_maxima']) : null;
    $valor_unitario = floatval($_POST['valor_unitario'] ?? 0);
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $ncm = trim($_POST['ncm'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    $permite_fracionamento = isset($_POST['permite_fracionamento']) ? 1 : 0;
    $controla_validade = isset($_POST['controla_validade']) ? 1 : 0;
    
    // Validações
    if (empty($nome)) {
        throw new Exception('Nome do produto é obrigatório');
    }
    
    if ($id_unidade <= 0) {
        throw new Exception('Unidade de medida é obrigatória');
    }
    
    if ($id_departamento <= 0) {
        throw new Exception('Departamento é obrigatório');
    }
    
    // Verificar código único (se informado)
    if (!empty($codigo)) {
        $sql = "SELECT id FROM estoque_produtos WHERE codigo = ? AND id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $codigo, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('Já existe um produto com este código');
        }
    }
    
    if ($id > 0) {
        // Atualizar
        $sql = "UPDATE estoque_produtos SET 
                    codigo = ?, codigo_barras = ?, nome = ?, descricao = ?,
                    id_categoria = ?, id_unidade = ?, id_departamento = ?, id_localizacao = ?,
                    quantidade_minima = ?, quantidade_ideal = ?, quantidade_maxima = ?,
                    valor_unitario = ?, marca = ?, modelo = ?, ncm = ?, observacoes = ?,
                    permite_fracionamento = ?, controla_validade = ?,
                    atualizado_em = NOW()
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiiiidddsssssiii", 
            $codigo, $codigo_barras, $nome, $descricao,
            $id_categoria, $id_unidade, $id_departamento, $id_localizacao,
            $quantidade_minima, $quantidade_ideal, $quantidade_maxima,
            $valor_unitario, $marca, $modelo, $ncm, $observacoes,
            $permite_fracionamento, $controla_validade, $id
        );
        $stmt->execute();
        $mensagem = 'Produto atualizado com sucesso';
    } else {
        // Inserir
        $sql = "INSERT INTO estoque_produtos 
                (codigo, codigo_barras, nome, descricao, id_categoria, id_unidade, id_departamento, 
                id_localizacao, quantidade_minima, quantidade_ideal, quantidade_maxima,
                valor_unitario, marca, modelo, ncm, observacoes, permite_fracionamento, controla_validade)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiiiidddsssssii",
            $codigo, $codigo_barras, $nome, $descricao,
            $id_categoria, $id_unidade, $id_departamento, $id_localizacao,
            $quantidade_minima, $quantidade_ideal, $quantidade_maxima,
            $valor_unitario, $marca, $modelo, $ncm, $observacoes,
            $permite_fracionamento, $controla_validade
        );
        $stmt->execute();
        $id = $conn->insert_id;
        $mensagem = 'Produto cadastrado com sucesso';
    }
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => $mensagem,
        'id' => $id
    ]);

} catch (Exception $e) {
    error_log("Erro em produtos/salvar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();



