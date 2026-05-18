-- ═══════════════════════════════════════════════════════════════════════════════
-- Inserir menu para gerenciar mensagens WhatsApp
-- ═══════════════════════════════════════════════════════════════════════════════

-- Verificar se menu já existe e inserir se não existir
INSERT INTO menus (codigo, nome, descricao, url, icone, categoria, ordem, acesso_padrao, requer_culto, requer_admin, ativo)
SELECT 
    'gerenciar_mensagens_whatsapp',
    'Gerenciar Mensagens WhatsApp',
    'Configure as mensagens variadas enviadas via WhatsApp',
    '/painel/gerenciar_mensagens_whatsapp.php',
    'bi-whatsapp',
    'gerenciamento',
    100,
    0,  -- acesso_padrao = 0 (só via grupos ou admin)
    0,  -- requer_culto = 0
    1,  -- requer_admin = 1 (exclusivo para admin)
    1   -- ativo = 1
WHERE NOT EXISTS (
    SELECT 1 FROM menus WHERE codigo = 'gerenciar_mensagens_whatsapp'
);

