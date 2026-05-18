-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: notificacoes_enviadas
-- Armazena histórico de todas as notificações enviadas (WhatsApp e Email)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS notificacoes_enviadas (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NULL COMMENT 'ID do usuário destinatário (pode ser NULL se não houver usuário específico)',
    tipo_notificacao ENUM('whatsapp', 'email') NOT NULL COMMENT 'Tipo de notificação enviada',
    tipo_mensagem VARCHAR(50) NULL COMMENT 'Tipo da mensagem (ex: lembrete_reserva, confirmacao_reserva)',
    destinatario VARCHAR(255) NOT NULL COMMENT 'Telefone ou email do destinatário',
    nome_destinatario VARCHAR(255) NULL COMMENT 'Nome do destinatário',
    assunto VARCHAR(255) NULL COMMENT 'Assunto (para emails)',
    mensagem TEXT NULL COMMENT 'Conteúdo da mensagem enviada',
    status ENUM('sucesso', 'falha') NOT NULL COMMENT 'Status do envio',
    mensagem_erro TEXT NULL COMMENT 'Mensagem de erro se status = falha',
    resposta_api TEXT NULL COMMENT 'Resposta completa da API (JSON)',
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora do envio',
    INDEX idx_usuario (usuario_id),
    INDEX idx_tipo_notificacao (tipo_notificacao),
    INDEX idx_tipo_mensagem (tipo_mensagem),
    INDEX idx_status (status),
    INDEX idx_data_envio (data_envio),
    INDEX idx_destinatario (destinatario),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de todas as notificações enviadas pelo sistema';

