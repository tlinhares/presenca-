-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: mensagens_padrao
-- Armazena templates de mensagens variadas para evitar detecção como bot
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS mensagens_padrao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL COMMENT 'Tipo da mensagem: lembrete_reserva, confirmacao_reserva, etc',
    mensagem TEXT NOT NULL COMMENT 'Template com placeholders: {nome}, {horario_limite}',
    ativo TINYINT(1) DEFAULT 1 COMMENT 'Se a mensagem está ativa para uso',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tipo_ativo (tipo, ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela para armazenar mensagens variadas do sistema';

-- ═══════════════════════════════════════════════════════════════════════════════
-- INSERIR MENSAGENS PADRÃO INICIAIS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO mensagens_padrao (tipo, mensagem) VALUES
('lembrete_reserva', 'Olá {nome}, você ainda não fez sua reserva de almoço para hoje. Horário limite: {horario_limite}'),
('lembrete_reserva', 'Olá {nome}, você esqueceu de fazer sua reserva de almoço para hoje. Horário limite: {horario_limite}'),
('lembrete_reserva', 'Olá {nome}, faça sua reserva de almoço para hoje até as {horario_limite}'),
('lembrete_reserva', 'Olá {nome}, não esqueça de reservar seu almoço para hoje. Prazo: {horario_limite}'),
('lembrete_reserva', 'Olá {nome}, sua reserva de almoço para hoje ainda não foi feita. Limite: {horario_limite}'),
('lembrete_reserva', 'Olá {nome}, lembre-se de fazer sua reserva de almoço até {horario_limite}'),
('lembrete_reserva', 'Olá {nome}, você precisa fazer sua reserva de almoço para hoje. Horário limite: {horario_limite}'),
('lembrete_reserva', 'Olá {nome}, não deixe de reservar seu almoço para hoje até {horario_limite}');

