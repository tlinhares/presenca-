-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: whatsapp_config_notificacoes
-- Configuração de qual API usar para cada tipo de notificação
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS whatsapp_config_notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_notificacao VARCHAR(50) NOT NULL UNIQUE COMMENT 'Tipo de notificação (ex: propria, lembrete_reserva)',
    modo_selecao ENUM('especifica', 'sorteio', 'desabilitado') DEFAULT 'sorteio' COMMENT 'Como selecionar a API',
    id_api_especifica INT NULL COMMENT 'Se modo=especifica, ID da API a usar',
    ids_apis_sorteio JSON NULL COMMENT 'Se modo=sorteio, array de IDs de APIs para sortear',
    tentativas_maximas INT DEFAULT 3 COMMENT 'Número máximo de tentativas antes de fallback para email',
    desabilitar_whatsapp TINYINT(1) DEFAULT 0 COMMENT 'Se 1, envia direto por email sem tentar WhatsApp',
    observacoes TEXT NULL,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_api_especifica) REFERENCES whatsapp_apis(id) ON DELETE SET NULL,
    INDEX idx_tipo (tipo_notificacao),
    INDEX idx_modo (modo_selecao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuração de APIs por tipo de notificação';
