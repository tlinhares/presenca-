-- Script para inserir menus de configuração do estoque no banco de dados
-- Execute este script no MySQL para criar os menus dinamicamente

-- Inserir menus de configuração do estoque
-- Usa INSERT IGNORE para evitar erros se os menus já existirem

INSERT IGNORE INTO menus (codigo, nome, descricao, descricao_card, url, icone, categoria, ordem, acesso_padrao, requer_admin, requer_culto, ativo) VALUES
('estoque_config_departamentos', 'Departamentos Estoque', 'Gerenciar departamentos do estoque', 'Setores', '/estoque/configuracoes/departamentos.php', 'bi-building', 'estoque_config', 1, 0, 0, 0, 1),
('estoque_config_categorias', 'Categorias Estoque', 'Gerenciar categorias de produtos', 'Tipos', '/estoque/configuracoes/categorias.php', 'bi-tags', 'estoque_config', 2, 0, 0, 0, 1),
('estoque_config_unidades', 'Unidades Estoque', 'Gerenciar unidades de medida', 'Medidas', '/estoque/configuracoes/unidades.php', 'bi-rulers', 'estoque_config', 3, 0, 0, 0, 1),
('estoque_config_fornecedores', 'Fornecedores', 'Gerenciar fornecedores', 'Parceiros', '/estoque/configuracoes/fornecedores.php', 'bi-truck', 'estoque_config', 4, 0, 0, 0, 1),
('estoque_config_responsaveis', 'Responsáveis Estoque', 'Gerenciar responsáveis pelo estoque', 'Gestores', '/estoque/configuracoes/responsaveis.php', 'bi-people', 'estoque_config', 5, 0, 0, 0, 1),
('estoque_config_localizacoes', 'Localizações Estoque', 'Gerenciar localizações e prateleiras', 'Prateleiras', '/estoque/configuracoes/localizacoes.php', 'bi-geo-alt', 'estoque_config', 6, 0, 0, 0, 1);

-- Vincular menus ao grupo "Estoque - Administrador" (ID: 11)
-- Primeiro, obtém os IDs dos menus recém-criados
SET @menu_departamentos = (SELECT id FROM menus WHERE codigo = 'estoque_config_departamentos' LIMIT 1);
SET @menu_categorias = (SELECT id FROM menus WHERE codigo = 'estoque_config_categorias' LIMIT 1);
SET @menu_unidades = (SELECT id FROM menus WHERE codigo = 'estoque_config_unidades' LIMIT 1);
SET @menu_fornecedores = (SELECT id FROM menus WHERE codigo = 'estoque_config_fornecedores' LIMIT 1);
SET @menu_responsaveis = (SELECT id FROM menus WHERE codigo = 'estoque_config_responsaveis' LIMIT 1);
SET @menu_localizacoes = (SELECT id FROM menus WHERE codigo = 'estoque_config_localizacoes' LIMIT 1);
SET @grupo_admin_estoque = 11; -- ID do grupo "Estoque - Administrador"

-- Inserir vínculos na tabela grupo_menus (usando INSERT IGNORE para evitar duplicatas)
INSERT IGNORE INTO grupo_menus (grupo_id, menu_id) VALUES
(@grupo_admin_estoque, @menu_departamentos),
(@grupo_admin_estoque, @menu_categorias),
(@grupo_admin_estoque, @menu_unidades),
(@grupo_admin_estoque, @menu_fornecedores),
(@grupo_admin_estoque, @menu_responsaveis),
(@grupo_admin_estoque, @menu_localizacoes);

-- Verificar se os menus foram criados corretamente
SELECT 
    m.id,
    m.codigo,
    m.nome,
    m.categoria,
    m.ativo,
    CASE WHEN gm.menu_id IS NOT NULL THEN 'SIM' ELSE 'NÃO' END as vinculado_ao_grupo
FROM menus m
LEFT JOIN grupo_menus gm ON gm.menu_id = m.id AND gm.grupo_id = @grupo_admin_estoque
WHERE m.categoria = 'estoque_config'
ORDER BY m.ordem;




