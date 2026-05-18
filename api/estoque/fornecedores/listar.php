<?php
/**
 * API - Listar Fornecedores de Estoque
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $apenas_ativos = !isset($_GET['todos']) || $_GET['todos'] !== 'true';
    
    $sql = "SELECT 
                id,
                razao_social,
                nome_fantasia,
                cnpj,
                inscricao_estadual,
                endereco,
                cidade,
                uf,
                cep,
                telefone,
                email,
                contato,
                observacoes,
                ativo,
                criado_em
            FROM estoque_fornecedores";
    
    if ($apenas_ativos) {
        $sql .= " WHERE ativo = 1";
    }
    
    $sql .= " ORDER BY COALESCE(nome_fantasia, razao_social) ASC";
    
    $result = $conn->query($sql);
    
    $fornecedores = [];
    while ($row = $result->fetch_assoc()) {
        $fornecedores[] = [
            'id' => intval($row['id']),
            'nome' => $row['nome_fantasia'] ?: $row['razao_social'],
            'razao_social' => $row['razao_social'],
            'nome_fantasia' => $row['nome_fantasia'],
            'cnpj' => $row['cnpj'],
            'inscricao_estadual' => $row['inscricao_estadual'],
            'endereco' => $row['endereco'],
            'cidade' => $row['cidade'],
            'uf' => $row['uf'],
            'cep' => $row['cep'],
            'telefone' => $row['telefone'],
            'email' => $row['email'],
            'contato' => $row['contato'],
            'observacoes' => $row['observacoes'],
            'ativo' => (bool)$row['ativo'],
            'criado_em' => $row['criado_em']
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'fornecedores' => $fornecedores,
        'total' => count($fornecedores)
    ]);

} catch (Exception $e) {
    error_log("Erro em fornecedores/listar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();



