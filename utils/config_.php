<?php
// utils/config.php

/**
 * Obtém uma configuração do banco de dados
 * @param string $chave Chave da configuração
 * @param mixed $valor_padrao Valor padrão se não encontrar
 * @return mixed
 */
function get_config($chave, $valor_padrao = null) {
    global $conn;
    
    // Se não há conexão, retorna valor padrão
    if (!isset($conn)) {
        return $valor_padrao;
    }
    
    try {
        $stmt = $conn->prepare("SELECT valor FROM configuracoes_culto WHERE chave = ?");
        $stmt->bind_param("s", $chave);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['valor'];
        }
        
        return $valor_padrao;
    } catch (Exception $e) {
        // Em caso de erro, retorna valor padrão
        return $valor_padrao;
    }
}

/**
 * Define uma configuração no banco de dados
 * @param string $chave Chave da configuração
 * @param mixed $valor Valor da configuração
 * @return bool
 */
function set_config($chave, $valor) {
    global $conn;
    
    // Se não há conexão, retorna false
    if (!isset($conn)) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO configuracoes_culto (chave, valor) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE valor = VALUES(valor)
        ");
        $stmt->bind_param("ss", $chave, $valor);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Obtém todas as configurações do banco de dados
 * @return array
 */
function get_all_config() {
    global $conn;
    
    $configuracoes = [];
    
    // Se não há conexão, retorna array vazio
    if (!isset($conn)) {
        return $configuracoes;
    }
    
    try {
        $result = $conn->query("SELECT chave, valor FROM configuracoes_culto");
        while ($row = $result->fetch_assoc()) {
            $configuracoes[$row['chave']] = $row['valor'];
        }
    } catch (Exception $e) {
        // Em caso de erro, retorna array vazio
    }
    
    return $configuracoes;
}
?>

