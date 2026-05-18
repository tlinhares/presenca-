-- ╔════════════════════════════════════════════════════════════════════════════╗
-- ║  INSERIR MENU: Dias Fechado do Refeitório                                  ║
-- ╚══════════════════════════════════════════════════════════════════════════╝

-- Inserir menu no módulo de gerenciamento
INSERT IGNORE INTO menus (codigo, nome, descricao, descricao_card, url, icone, categoria, ordem, acesso_padrao, requer_admin, requer_culto, ativo) VALUES
('gerenciamento_dias_fechado', 'Dias Fechado do Refeitório', 'Gerenciar datas em que o refeitório não funcionará', 'Dias Fechado', '/painel/dias_fechado.php', 'bi-calendar-x', 'gerenciamento', 10, 0, 1, 0, 1);

