-- Tabela para justificativas de presença
CREATE TABLE IF NOT EXISTS justificativas_culto (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario BIGINT UNSIGNED NOT NULL,
    data_falta DATE NOT NULL,
    motivo TEXT NOT NULL,
    observacoes TEXT,
    status ENUM('pendente', 'aprovada', 'rejeitada') DEFAULT 'pendente',
    id_admin_aprovador BIGINT UNSIGNED NULL,
    data_aprovacao TIMESTAMP NULL,
    observacoes_admin TEXT,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_justificativa_dia (id_usuario, data_falta),
    INDEX idx_status (status),
    INDEX idx_data_falta (data_falta),
    INDEX idx_usuario_data (id_usuario, data_falta)
);
