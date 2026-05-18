-- Script para inserir o menu de Gestão de Reservas
-- Execute este script no MySQL para criar o menu

INSERT INTO menus (codigo, nome, descricao, descricao_card, url, icone, categoria, ordem, acesso_padrao, requer_admin, requer_culto, ativo) 
VALUES ('gestao_reservas', 'Gestão de Reservas', 'Gerenciar todas as reservas de almoço do sistema', 'Gestão de Reservas', '/painel/gestao_reservas.php', 'bi-calendar-check', 'refeicoes', 5, 0, 0, 0, 1)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    descricao_card = VALUES(descricao_card),
    url = VALUES(url),
    icone = VALUES(icone),
    categoria = VALUES(categoria),
    ordem = VALUES(ordem),
    ativo = 1,
    requer_admin = 0;



