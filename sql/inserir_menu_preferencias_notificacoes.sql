-- ═══════════════════════════════════════════════════════════════════════════════
-- INSERIR MENU: Gerenciar Preferências de Notificações
-- ═══════════════════════════════════════════════════════════════════════════════
-- 
-- NOTA: Este script pode ser executado diretamente no MySQL, mas recomenda-se
-- usar o script PHP: api/notificacao/instalar_menu_preferencias.php
-- que segue o padrão do sistema e limpa o cache automaticamente
--

-- Inserir menu seguindo o padrão do sistema
INSERT INTO menus (codigo, nome, descricao, descricao_card, url, icone, categoria, ordem, acesso_padrao, requer_admin, requer_culto, ativo) 
VALUES ('gerenciar_preferencias_notificacoes', 'Preferências de Notificações', 'Gerenciar preferências de notificação dos usuários', 'Preferências Notificações', '/painel/gerenciar_preferencias_notificacoes.php', 'bi-bell', 'gerenciamento', 101, 0, 1, 0, 1)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    descricao_card = VALUES(descricao_card),
    url = VALUES(url),
    icone = VALUES(icone),
    categoria = VALUES(categoria),
    ordem = VALUES(ordem),
    acesso_padrao = VALUES(acesso_padrao),
    requer_admin = VALUES(requer_admin),
    requer_culto = VALUES(requer_culto),
    ativo = 1;
