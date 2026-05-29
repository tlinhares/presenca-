-- Notificação de alertas de estoque (cron + WhatsApp)
-- Cria a config administrável e estende estoque_alertas para controle de repetição.

-- 1) Configuração (linha única, id = 1)
CREATE TABLE IF NOT EXISTS `estoque_config_alertas` (
  `id` tinyint NOT NULL DEFAULT 1,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `intervalo_horas` int NOT NULL DEFAULT 24,
  `telefone_fallback` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `atualizado_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `atualizado_por` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_intervalo_positivo` CHECK (`intervalo_horas` >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `estoque_config_alertas` (`id`, `ativo`, `intervalo_horas`, `telefone_fallback`)
VALUES (1, 1, 24, NULL);

-- 2) Controle de repetição/resolução em estoque_alertas
ALTER TABLE `estoque_alertas`
  ADD COLUMN `notificado_em` datetime DEFAULT NULL AFTER `data_leitura`,
  ADD COLUMN `resolvido` tinyint(1) NOT NULL DEFAULT 0 AFTER `notificado_em`,
  ADD COLUMN `resolvido_em` datetime DEFAULT NULL AFTER `resolvido`,
  ADD UNIQUE KEY `uk_produto_tipo` (`id_produto`, `tipo`);
