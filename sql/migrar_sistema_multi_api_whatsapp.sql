-- ═══════════════════════════════════════════════════════════════════════════════
-- SCRIPT DE MIGRAÇÃO: Sistema Multi-API WhatsApp
-- Este script cria as tabelas e insere configurações padrão
-- ═══════════════════════════════════════════════════════════════════════════════

-- Criar tabelas
SOURCE criar_tabela_whatsapp_apis.sql;
SOURCE criar_tabela_whatsapp_config_notificacoes.sql;

-- Inserir configurações padrão para cada tipo de notificação
-- Modo padrão: 'sorteio' (usará todas APIs ativas), 3 tentativas máximas
INSERT IGNORE INTO whatsapp_config_notificacoes (tipo_notificacao, modo_selecao, tentativas_maximas, desabilitar_whatsapp) VALUES
('propria', 'sorteio', 3, 0),
('adicional', 'sorteio', 3, 0),
('multipla', 'sorteio', 3, 0),
('cancelada', 'sorteio', 3, 0),
('lembrete_reserva', 'sorteio', 3, 0),
('justificativa_culto', 'sorteio', 3, 0),
('cadastro_usuario', 'sorteio', 3, 0),
('relatorio_diario', 'sorteio', 3, 0);

-- Mensagem de sucesso
SELECT 'Migração concluída! Tabelas criadas e configurações padrão inseridas.' AS resultado;
