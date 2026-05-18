<?php
/**
 * Script de Instalação: Adicionar campo tema na tabela usuarios
 * Execute este script uma vez para criar o campo tema
 * Endpoint: GET /api/usuarios/instalar_campo_tema.php
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../conexao.php';

try {
    // Verificar se o campo já existe
    $check_column = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'tema'");
    
    if ($check_column->num_rows == 0) {
        // Criar o campo
        $sql = "ALTER TABLE usuarios ADD COLUMN tema VARCHAR(10) DEFAULT 'light' COMMENT 'Tema preferido: light ou dark'";
        
        if ($conn->query($sql)) {
            // Atualizar todos os usuários existentes para tema padrão
            $conn->query("UPDATE usuarios SET tema = 'light' WHERE tema IS NULL OR tema = ''");
            
            echo json_encode([
                'status' => 'ok',
                'mensagem' => 'Campo tema criado com sucesso!'
            ]);
        } else {
            throw new Exception('Erro ao criar campo: ' . $conn->error);
        }
    } else {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Campo tema já existe na tabela usuarios'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erro ao instalar campo tema: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao instalar campo tema: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
