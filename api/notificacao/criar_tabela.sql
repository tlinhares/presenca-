-- Tabela para configurações de notificações dos usuários
CREATE TABLE IF NOT EXISTS notificacoes_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario BIGINT UNSIGNED NOT NULL,
    notificar_reserva_propria TINYINT(1) DEFAULT 0 COMMENT '1=Ativo, 0=Inativo',
    notificar_reserva_adicional TINYINT(1) DEFAULT 0 COMMENT '1=Ativo, 0=Inativo',
    notificar_reserva_multipla TINYINT(1) DEFAULT 0 COMMENT '1=Ativo, 0=Inativo',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_usuario (id_usuario),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

