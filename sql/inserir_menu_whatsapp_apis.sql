-- ═══════════════════════════════════════════════════════════════════════════════
-- INSERIR MENU: Gerenciar APIs WhatsApp
-- ═══════════════════════════════════════════════════════════════════════════════
-- 
-- NOTA: Este script pode ser executado diretamente no MySQL, mas recomenda-se
-- usar o script PHP: api/whatsapp_apis/instalar_menu.php
-- que segue o padrão do sistema e limpa o cache automaticamente
--

-- Inserir menu seguindo o padrão do sistema
INSERT INTO menus (codigo, nome, descricao, descricao_card, url, icone, categoria, ordem, acesso_padrao, requer_admin, requer_culto, ativo) 
VALUES ('gerenciar_whatsapp_apis', 'APIs WhatsApp', 'Gerenciar APIs de WhatsApp e configurações de notificações', 'APIs WhatsApp', '/painel/whatsapp_apis.php', 'bi-whatsapp', 'gerenciamento', 100, 0, 1, 0, 1)
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
