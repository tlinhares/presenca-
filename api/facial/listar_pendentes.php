<?php
// Arquivo: api/facial/listar_pendentes.php
// Objetivo: Listar registros pendentes de sincronização na biometria facial

header('Content-Type: application/json');
require_once('../conexao.php');
require_once('verificar_sessao_admin.php');

// Verificar se o usuário é administrador
verificarAdmin(basename(__FILE__));

try {
    // Verificar se a tabela existe
    $query = "SHOW TABLES LIKE 'sincronizacao_facial'";
    $stmt = $conexao->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // Criar tabela se não existir
        $query = "CREATE TABLE sincronizacao_facial (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT,
            data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
            dados_biometricos TEXT,
            dispositivo VARCHAR(100),
            status ENUM('pendente', 'sincronizado', 'erro') DEFAULT 'pendente',
            erro TEXT,
            data_sincronizacao DATETIME NULL
        )";
        $conexao->exec($query);
    }
    
    // Buscar registros pendentes
    $query = "SELECT sf.id, sf.id_usuario, sf.data_hora, sf.dispositivo, u.nome as nome_usuario 
              FROM sincronizacao_facial sf
              LEFT JOIN usuarios u ON sf.id_usuario = u.id
              WHERE sf.status = 'pendente' 
              ORDER BY sf.data_hora DESC 
              LIMIT 100";
    
    $stmt = $conexao->prepare($query);
    $stmt->execute();
    
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Registrar logs para debug
    $log_file = __DIR__ . '/../../logs/listar_pendentes_' . date('Y-m-d') . '.log';
    $time = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$time] Consulta realizada: " . count($registros) . " registros encontrados" . PHP_EOL, FILE_APPEND);
    
    // Retornar resultados
    echo json_encode([
        'status' => 'ok',
        'registros' => $registros,
        'total' => count($registros)
    ]);
    
} catch (PDOException $e) {
    // Registrar o erro em log
    $log_file = __DIR__ . '/../../logs/listar_pendentes_error_' . date('Y-m-d') . '.log';
    $time = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$time] ERRO: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao listar registros pendentes: ' . $e->getMessage()
    ]);
}
?> 