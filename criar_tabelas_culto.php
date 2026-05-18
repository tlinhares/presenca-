<?php
// Script para criar as tabelas do culto
require_once 'api/conexao.php';

echo "<h1>Criando Tabelas do Culto</h1>";

try {
    // Tabela principal de presenças de culto
    $sql1 = "CREATE TABLE IF NOT EXISTS presencas_culto (
        id INT PRIMARY KEY AUTO_INCREMENT,
        id_usuario INT NOT NULL,
        data DATE NOT NULL,
        horario_confirmacao TIME NOT NULL,
        tipo_confirmacao ENUM('facial', 'manual', 'atrasado') DEFAULT 'facial',
        status ENUM('presente', 'ausente', 'justificado') DEFAULT 'presente',
        observacoes TEXT,
        id_admin_manual INT NULL,
        data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_presenca_dia (id_usuario, data),
        INDEX idx_data (data),
        INDEX idx_usuario_data (id_usuario, data)
    )";
    
    if ($conn->query($sql1)) {
        echo "<p>✅ Tabela 'presencas_culto' criada com sucesso!</p>";
    } else {
        echo "<p>❌ Erro ao criar tabela 'presencas_culto': " . $conn->error . "</p>";
    }

    // Tabela de configurações específicas do culto
    $sql2 = "CREATE TABLE IF NOT EXISTS configuracoes_culto (
        id INT PRIMARY KEY AUTO_INCREMENT,
        chave VARCHAR(100) UNIQUE NOT NULL,
        valor TEXT,
        descricao TEXT,
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_chave (chave)
    )";
    
    if ($conn->query($sql2)) {
        echo "<p>✅ Tabela 'configuracoes_culto' criada com sucesso!</p>";
    } else {
        echo "<p>❌ Erro ao criar tabela 'configuracoes_culto': " . $conn->error . "</p>";
    }

    // Inserir configurações padrão
    $configuracoes = [
        ['horario_inicio', '07:00:00', 'Horário que o sistema começa a aceitar presenças'],
        ['horario_culto', '07:30:00', 'Horário oficial do culto'],
        ['horario_fim', '08:00:00', 'Horário limite para confirmação de presença'],
        ['permitir_atraso', '1', 'Permitir confirmação após horário do culto'],
        ['horario_atraso_limite', '08:30:00', 'Horário limite para presença atrasada'],
        ['dias_semana', '1,2,3,4,5', 'Dias da semana que há culto (1=segunda, 7=domingo)'],
        ['mensagem_inicio_culto', 'Bem-vindo ao sistema de presença de culto!', 'Mensagem de boas-vindas do culto'],
        ['notificacao_ausencia', '1', 'Enviar notificação para ausências'],
        ['culto_habilitado', '1', 'Sistema de culto habilitado']
    ];

    $stmt = $conn->prepare("INSERT INTO configuracoes_culto (chave, valor, descricao) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor), descricao = VALUES(descricao)");
    
    foreach ($configuracoes as $config) {
        $stmt->bind_param("sss", $config[0], $config[1], $config[2]);
        if ($stmt->execute()) {
            echo "<p>✅ Configuração '{$config[0]}' inserida/atualizada!</p>";
        } else {
            echo "<p>❌ Erro ao inserir configuração '{$config[0]}': " . $stmt->error . "</p>";
        }
    }

    echo "<h2>✅ Tabelas do culto criadas com sucesso!</h2>";
    echo "<p><a href='dashboard.php'>Ir para o Dashboard</a></p>";

} catch (Exception $e) {
    echo "<p>❌ Erro geral: " . $e->getMessage() . "</p>";
}

$conn->close();
?>

