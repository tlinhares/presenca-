<?php
/**
 * API - Salvar/Atualizar API de WhatsApp
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

// Apenas admin pode acessar
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../../api/conexao.php';

try {
    // Verificar se tabela existe
    $result_check_table = $conn->query("SHOW TABLES LIKE 'whatsapp_apis'");
    if (!$result_check_table || $result_check_table->num_rows === 0) {
        throw new Exception('Tabela whatsapp_apis não existe. Execute o script de inicialização primeiro: api/whatsapp_apis/inicializar.php');
    }
    
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nome = trim($_POST['nome'] ?? '');
    $url_mensagem = trim($_POST['url_mensagem'] ?? '');
    $url_arquivo = trim($_POST['url_arquivo'] ?? '');
    $token = trim($_POST['token'] ?? '');
    $numero_whatsapp = trim($_POST['numero_whatsapp'] ?? '');
    $numero_whatsapp = empty($numero_whatsapp) ? null : $numero_whatsapp;
    $ativo = isset($_POST['ativo']) ? intval($_POST['ativo']) : 1;
    $prioridade = isset($_POST['prioridade']) ? intval($_POST['prioridade']) : 0;
    $observacoes = trim($_POST['observacoes'] ?? '');
    $observacoes = empty($observacoes) ? null : $observacoes;
    
    // Validações
    if (empty($nome)) {
        throw new Exception('Nome é obrigatório');
    }
    
    if (empty($url_mensagem)) {
        throw new Exception('URL de mensagens é obrigatória');
    }
    
    if (empty($url_arquivo)) {
        throw new Exception('URL de arquivos é obrigatória');
    }
    
    if (empty($token)) {
        throw new Exception('Token é obrigatório');
    }
    
    // Validar URLs
    if (!filter_var($url_mensagem, FILTER_VALIDATE_URL)) {
        throw new Exception('URL de mensagens inválida');
    }
    
    if (!filter_var($url_arquivo, FILTER_VALIDATE_URL)) {
        throw new Exception('URL de arquivos inválida');
    }
    
    // Verificar se nome já existe (exceto para o próprio registro)
    $sql_check = "SELECT id FROM whatsapp_apis WHERE nome = ? AND id != ?";
    $stmt_check = $conn->prepare($sql_check);
    if (!$stmt_check) {
        throw new Exception('Erro ao preparar query de verificação: ' . $conn->error);
    }
    
    $stmt_check->bind_param("si", $nome, $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        $stmt_check->close();
        throw new Exception('Já existe uma API com este nome');
    }
    $stmt_check->close();
    
    if ($id > 0) {
        // Atualizar
        // Se token não foi fornecido, manter o atual
        $sql = "UPDATE whatsapp_apis SET 
                    nome = ?,
                    url_mensagem = ?,
                    url_arquivo = ?,
                    token = ?,
                    numero_whatsapp = ?,
                    ativo = ?,
                    prioridade = ?,
                    observacoes = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Erro ao preparar query de atualização: ' . $conn->error);
        }
        
        $stmt->bind_param("sssssiisi", $nome, $url_mensagem, $url_arquivo, $token, $numero_whatsapp, $ativo, $prioridade, $observacoes, $id);
        
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Erro ao executar atualização: ' . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0 && $conn->error) {
            $stmt->close();
            throw new Exception('Erro ao atualizar: ' . $conn->error);
        }
        
        $mensagem = 'API atualizada com sucesso';
    } else {
        // Inserir
        $sql = "INSERT INTO whatsapp_apis 
                (nome, url_mensagem, url_arquivo, token, numero_whatsapp, ativo, prioridade, observacoes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Erro ao preparar query de inserção: ' . $conn->error);
        }
        
        $stmt->bind_param("sssssiii", $nome, $url_mensagem, $url_arquivo, $token, $numero_whatsapp, $ativo, $prioridade, $observacoes);
        
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Erro ao executar inserção: ' . $stmt->error);
        }
        
        $id = $conn->insert_id;
        $mensagem = 'API cadastrada com sucesso';
    }
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => $mensagem,
        'id' => $id
    ]);
    
    if (isset($stmt)) {
        $stmt->close();
    }
    
} catch (Exception $e) {
    error_log("Erro em whatsapp_apis/salvar.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}

$conn->close();
