-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: whatsapp_apis
-- Armazena informações de cada API de WhatsApp cadastrada no sistema
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS whatsapp_apis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nome identificador da API (ex: numeroti, numeropresenca)',
    url_mensagem VARCHAR(500) NOT NULL COMMENT 'URL completa da API para envio de mensagens',
    url_arquivo VARCHAR(500) NOT NULL COMMENT 'URL completa da API para envio de arquivos',
    token TEXT NOT NULL COMMENT 'Token de autenticação Bearer',
    numero_whatsapp VARCHAR(20) NULL COMMENT 'Número do WhatsApp associado (opcional)',
    ativo TINYINT(1) DEFAULT 1 COMMENT '1=Ativa, 0=Inativa',
    prioridade INT DEFAULT 0 COMMENT 'Ordem de tentativa (menor = maior prioridade)',
    total_envios INT DEFAULT 0 COMMENT 'Contador de envios bem-sucedidos',
    total_falhas INT DEFAULT 0 COMMENT 'Contador de falhas',
    ultima_utilizacao DATETIME NULL COMMENT 'Última vez que foi utilizada',
    observacoes TEXT NULL COMMENT 'Observações sobre a API',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_ativo (ativo),
    INDEX idx_prioridade (prioridade),
    INDEX idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='APIs de WhatsApp cadastradas no sistema';
