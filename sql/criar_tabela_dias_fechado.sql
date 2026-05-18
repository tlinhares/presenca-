-- ╔════════════════════════════════════════════════════════════════════════════╗
-- ║  TABELA: dias_fechado                                                     ║
-- ║  Cadastro de datas onde o refeitório não funcionará                      ║
-- ╚══════════════════════════════════════════════════════════════════════════╝

CREATE TABLE IF NOT EXISTS dias_fechado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data DATE NOT NULL UNIQUE COMMENT 'Data em que o refeitório estará fechado',
    motivo VARCHAR(255) DEFAULT NULL COMMENT 'Motivo do fechamento (ex: feriado, manutenção)',
    observacoes TEXT DEFAULT NULL COMMENT 'Observações adicionais sobre o fechamento',
    ativo TINYINT(1) DEFAULT 1 COMMENT 'Se o registro está ativo (1) ou inativo (0)',
    criado_por BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID do usuário que criou o registro',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora de criação',
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data e hora da última atualização',
    INDEX idx_data (data),
    INDEX idx_ativo (ativo),
    INDEX idx_data_ativo (data, ativo),
    FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela para cadastrar datas em que o refeitório não funcionará';

