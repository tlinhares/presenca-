-- ═══════════════════════════════════════════════════════════════════════════════
-- Inserir menu para gerenciar notificações enviadas
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO menus (codigo, nome, descricao, url, icone, categoria, ordem, acesso_padrao, requer_culto, requer_admin, ativo)
SELECT 
    'gerenciar_notificacoes_enviadas',
    'Gerenciar Notificações Enviadas',
    'Visualize o histórico completo de todas as notificações enviadas (WhatsApp e Email)',
    '/painel/gerenciar_notificacoes_enviadas.php',
    'bi-bell',
    'gerenciamento',
    101,
    0,  -- acesso_padrao = 0 (só via grupos ou admin)
    0,  -- requer_culto = 0
    1,  -- requer_admin = 1 (exclusivo para admin)
    1   -- ativo = 1
WHERE NOT EXISTS (
    SELECT 1 FROM menus WHERE codigo = 'gerenciar_notificacoes_enviadas'
);


