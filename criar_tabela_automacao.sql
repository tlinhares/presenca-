-- Tabela para automações de relatórios
CREATE TABLE IF NOT EXISTS automacoes_relatorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    tipo_relatorio ENUM('diario', 'diario_completo', 'csv', 'csv_diario') NOT NULL,
    numero_whatsapp VARCHAR(20) NOT NULL,
    horario_envio TIME NOT NULL,
    dias_semana JSON NOT NULL,
    mensagem_personalizada TEXT,
    ativo TINYINT(1) DEFAULT 1,
    ultimo_envio DATETIME NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela para logs de envio
CREATE TABLE IF NOT EXISTS logs_automacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    automacao_id INT NOT NULL,
    data_envio DATETIME NOT NULL,
    status ENUM('sucesso', 'erro') NOT NULL,
    mensagem TEXT,
    arquivo_gerado VARCHAR(255),
    FOREIGN KEY (automacao_id) REFERENCES automacoes_relatorios(id) ON DELETE CASCADE
);
