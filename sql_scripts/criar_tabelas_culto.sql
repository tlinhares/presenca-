-- Script para criar as tabelas do módulo de presença de culto
-- Execute este script para adicionar as funcionalidades de culto ao sistema existente

-- Tabela principal de presenças de culto
CREATE TABLE IF NOT EXISTS presencas_culto (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    data DATE NOT NULL,
    horario_confirmacao TIME NOT NULL,
    tipo_confirmacao ENUM('facial', 'manual', 'atrasado') DEFAULT 'facial',
    status ENUM('presente', 'atrasado', 'falta', 'ausente', 'justificado') DEFAULT 'presente',
    observacoes TEXT,
    id_admin_manual INT NULL,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (id_admin_manual) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY unique_presenca_dia (id_usuario, data),
    INDEX idx_data (data),
    INDEX idx_usuario_data (id_usuario, data)
);

-- Tabela de configurações específicas do culto
CREATE TABLE IF NOT EXISTS configuracoes_culto (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descricao TEXT,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chave (chave)
);

-- Inserir configurações padrão do culto
INSERT INTO configuracoes_culto (chave, valor, descricao) VALUES
('horario_inicio', '07:00:00', 'Horário que o sistema começa a aceitar presenças'),
('horario_culto', '07:30:00', 'Horário oficial do culto'),
('horario_fim', '08:00:00', 'Horário limite para confirmação de presença'),
('permitir_atraso', '1', 'Permitir confirmação após horário do culto'),
('horario_atraso_limite', '08:30:00', 'Horário limite para presença atrasada'),
('dias_semana', '1,2,3,4,5', 'Dias da semana que há culto (1=segunda, 7=domingo)'),
('mensagem_inicio_culto', 'Bem-vindo ao sistema de presença de culto!', 'Mensagem de boas-vindas do culto'),
('notificacao_ausencia', '1', 'Enviar notificação para ausências'),
('culto_habilitado', '1', 'Sistema de culto habilitado'),
('dispositivo_culto_ip', '10.144.129.70', 'IP do dispositivo facial do culto (SS3530)'),
('dispositivo_culto_porta', '80', 'Porta do dispositivo facial do culto'),
('dispositivo_culto_usuario', 'admin', 'Usuário do dispositivo facial do culto'),
('dispositivo_culto_senha', 'Arcs2901', 'Senha do dispositivo facial do culto')
ON DUPLICATE KEY UPDATE 
    valor = VALUES(valor),
    descricao = VALUES(descricao),
    data_atualizacao = CURRENT_TIMESTAMP;

-- Tabela para logs de sincronização facial do culto
CREATE TABLE IF NOT EXISTS facial_sync_culto (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    data DATE NOT NULL,
    status ENUM('pendente', 'sincronizado', 'erro') DEFAULT 'pendente',
    tentativas INT DEFAULT 0,
    ultima_tentativa TIMESTAMP NULL,
    erro_detalhes TEXT,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_sync_culto (id_usuario, data),
    INDEX idx_data_status (data, status)
);

-- Adicionar coluna para indicar se o usuário tem acesso ao culto (opcional)
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS acesso_culto TINYINT(1) DEFAULT 1 COMMENT 'Se o usuário tem acesso ao sistema de culto';

-- Criar índices para melhor performance
CREATE INDEX IF NOT EXISTS idx_presencas_culto_data ON presencas_culto(data);
CREATE INDEX IF NOT EXISTS idx_presencas_culto_usuario ON presencas_culto(id_usuario);
CREATE INDEX IF NOT EXISTS idx_presencas_culto_status ON presencas_culto(status);

-- Comentários das tabelas
ALTER TABLE presencas_culto COMMENT = 'Registra as presenças dos usuários no culto';
ALTER TABLE configuracoes_culto COMMENT = 'Configurações específicas do módulo de culto';
ALTER TABLE facial_sync_culto COMMENT = 'Controle de sincronização facial para o culto';

