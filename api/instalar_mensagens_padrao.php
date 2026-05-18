<?php
/**
 * Script para criar a tabela mensagens_padrao e inserir mensagens iniciais
 * Executa de forma segura, verificando se a tabela já existe
 */

require_once __DIR__ . '/conexao.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <title>Instalação - Mensagens Padrão</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🔧 Instalação - Tabela Mensagens Padrão</h1>";

try {
    // Verificar se a tabela já existe
    $check_table = $conn->query("SHOW TABLES LIKE 'mensagens_padrao'");
    
    if ($check_table->num_rows > 0) {
        echo "<div class='info'>ℹ️ A tabela 'mensagens_padrao' já existe.</div>";
        
        // Verificar se já tem mensagens
        $check_mensagens = $conn->query("SELECT COUNT(*) as total FROM mensagens_padrao WHERE tipo = 'lembrete_reserva'");
        $row = $check_mensagens->fetch_assoc();
        
        if ($row['total'] > 0) {
            echo "<div class='info'>ℹ️ A tabela já possui {$row['total']} mensagens do tipo 'lembrete_reserva'.</div>";
            echo "<div class='success'>✅ Sistema já está configurado. Nenhuma alteração necessária.</div>";
        } else {
            // Inserir apenas as mensagens
            echo "<div class='info'>ℹ️ Inserindo mensagens padrão...</div>";
            
            $mensagens = [
                "('lembrete_reserva', 'Olá {nome}, você ainda não fez sua reserva de almoço para hoje. Horário limite: {horario_limite}')",
                "('lembrete_reserva', 'Olá {nome}, você esqueceu de fazer sua reserva de almoço para hoje. Horário limite: {horario_limite}')",
                "('lembrete_reserva', 'Olá {nome}, faça sua reserva de almoço para hoje até as {horario_limite}')",
                "('lembrete_reserva', 'Olá {nome}, não esqueça de reservar seu almoço para hoje. Prazo: {horario_limite}')",
                "('lembrete_reserva', 'Olá {nome}, sua reserva de almoço para hoje ainda não foi feita. Limite: {horario_limite}')",
                "('lembrete_reserva', 'Olá {nome}, lembre-se de fazer sua reserva de almoço até {horario_limite}')",
                "('lembrete_reserva', 'Olá {nome}, você precisa fazer sua reserva de almoço para hoje. Horário limite: {horario_limite}')",
                "('lembrete_reserva', 'Olá {nome}, não deixe de reservar seu almoço para hoje até {horario_limite}')"
            ];
            
            $sql_insert = "INSERT INTO mensagens_padrao (tipo, mensagem) VALUES " . implode(',', $mensagens);
            
            if ($conn->query($sql_insert)) {
                echo "<div class='success'>✅ " . count($mensagens) . " mensagens inseridas com sucesso!</div>";
            } else {
                throw new Exception("Erro ao inserir mensagens: " . $conn->error);
            }
        }
    } else {
        // Criar a tabela
        echo "<div class='info'>ℹ️ Criando tabela 'mensagens_padrao'...</div>";
        
        $sql_create = "CREATE TABLE IF NOT EXISTS mensagens_padrao (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo VARCHAR(50) NOT NULL COMMENT 'Tipo da mensagem: lembrete_reserva, confirmacao_reserva, etc',
            mensagem TEXT NOT NULL COMMENT 'Template com placeholders: {nome}, {horario_limite}',
            ativo TINYINT(1) DEFAULT 1 COMMENT 'Se a mensagem está ativa para uso',
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tipo_ativo (tipo, ativo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela para armazenar mensagens variadas do sistema'";
        
        if ($conn->query($sql_create)) {
            echo "<div class='success'>✅ Tabela criada com sucesso!</div>";
            
            // Inserir mensagens
            echo "<div class='info'>ℹ️ Inserindo mensagens padrão...</div>";
            
            $mensagens = [
                "('lembrete_reserva', 'Olá {nome}, você ainda não fez sua reserva de almoço para hoje. Horário limite: {horario_limite}')",
                "('lembrete_reserva', 'Olá {nome}, você esqueceu de fazer sua reserva de almoço para hoje. Horário limite: {horario_limite}')",
                "('lembrete_reserva', 'Olá {nome}, faça sua reserva de almoço para hoje até as {horario_limite}')",
                "('lembrete_reserva', 'Olá {nome}, não esqueça de reservar seu almoço para hoje. Prazo: {horario_limite}')",
                "('lembrete_reserva', 'Olá {nome}, sua reserva de almoço para hoje ainda não foi feita. Limite: {horario_limite}')",
                "('lembrete_reserva', 'Olá {nome}, lembre-se de fazer sua reserva de almoço até {horario_limite}')",
                "('lembrete_reserva', 'Olá {nome}, você precisa fazer sua reserva de almoço para hoje. Horário limite: {horario_limite}')",
                "('lembrete_reserva', 'Olá {nome}, não deixe de reservar seu almoço para hoje até {horario_limite}')"
            ];
            
            $sql_insert = "INSERT INTO mensagens_padrao (tipo, mensagem) VALUES " . implode(',', $mensagens);
            
            if ($conn->query($sql_insert)) {
                echo "<div class='success'>✅ " . count($mensagens) . " mensagens inseridas com sucesso!</div>";
            } else {
                throw new Exception("Erro ao inserir mensagens: " . $conn->error);
            }
        } else {
            throw new Exception("Erro ao criar tabela: " . $conn->error);
        }
    }
    
    // Mostrar estatísticas
    $stats = $conn->query("SELECT tipo, COUNT(*) as total FROM mensagens_padrao WHERE ativo = 1 GROUP BY tipo");
    echo "<div class='info'><h3>📊 Estatísticas:</h3><ul>";
    while ($row = $stats->fetch_assoc()) {
        echo "<li><strong>{$row['tipo']}:</strong> {$row['total']} mensagens</li>";
    }
    echo "</ul></div>";
    
    echo "<div class='success'><h3>✅ Instalação concluída com sucesso!</h3></div>";
    
} catch (Exception $e) {
    echo "<div class='error'><h3>❌ Erro:</h3><p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
}

echo "</body></html>";
?>

