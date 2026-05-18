-- Adição de tabelas para o módulo financeiro
-- Sistema de Gestão de Construtora

USE sistema_construtora;

-- Tabela de notas fiscais
CREATE TABLE IF NOT EXISTS notas_fiscais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(50) NOT NULL,
    fornecedor_id INT,
    obra_id INT,
    data_emissao DATE NOT NULL,
    valor_total DECIMAL(15,2) NOT NULL,
    descricao TEXT,
    arquivo_path VARCHAR(255),
    forma_pagamento VARCHAR(50) NOT NULL,
    status_pagamento VARCHAR(50) NOT NULL,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id),
    FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE
);

-- Tabela de parcelas
CREATE TABLE IF NOT EXISTS parcelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nota_fiscal_id INT NOT NULL,
    numero_parcela INT NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE,
    status VARCHAR(50) NOT NULL DEFAULT 'Pendente',
    FOREIGN KEY (nota_fiscal_id) REFERENCES notas_fiscais(id) ON DELETE CASCADE
);

-- Tabela de permissões por perfil
CREATE TABLE IF NOT EXISTS permissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    perfil_id INT NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    acao VARCHAR(50) NOT NULL,
    permitido BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (perfil_id) REFERENCES perfis(id) ON DELETE CASCADE,
    UNIQUE KEY (perfil_id, modulo, acao)
);

-- Inserção de permissões padrão
INSERT INTO permissoes (perfil_id, modulo, acao, permitido) VALUES
-- Administrador (acesso total)
(1, 'obras', 'visualizar', TRUE),
(1, 'obras', 'criar', TRUE),
(1, 'obras', 'editar', TRUE),
(1, 'obras', 'excluir', TRUE),
(1, 'financeiro', 'visualizar', TRUE),
(1, 'financeiro', 'criar', TRUE),
(1, 'financeiro', 'editar', TRUE),
(1, 'financeiro', 'excluir', TRUE),
(1, 'cronograma', 'visualizar', TRUE),
(1, 'cronograma', 'criar', TRUE),
(1, 'cronograma', 'editar', TRUE),
(1, 'cronograma', 'excluir', TRUE),
(1, 'documentos', 'visualizar', TRUE),
(1, 'documentos', 'criar', TRUE),
(1, 'documentos', 'editar', TRUE),
(1, 'documentos', 'excluir', TRUE),
(1, 'usuarios', 'visualizar', TRUE),
(1, 'usuarios', 'criar', TRUE),
(1, 'usuarios', 'editar', TRUE),
(1, 'usuarios', 'excluir', TRUE),

-- Gerente (sem acesso a usuários)
(2, 'obras', 'visualizar', TRUE),
(2, 'obras', 'criar', TRUE),
(2, 'obras', 'editar', TRUE),
(2, 'obras', 'excluir', FALSE),
(2, 'financeiro', 'visualizar', TRUE),
(2, 'financeiro', 'criar', TRUE),
(2, 'financeiro', 'editar', TRUE),
(2, 'financeiro', 'excluir', FALSE),
(2, 'cronograma', 'visualizar', TRUE),
(2, 'cronograma', 'criar', TRUE),
(2, 'cronograma', 'editar', TRUE),
(2, 'cronograma', 'excluir', FALSE),
(2, 'documentos', 'visualizar', TRUE),
(2, 'documentos', 'criar', TRUE),
(2, 'documentos', 'editar', TRUE),
(2, 'documentos', 'excluir', FALSE),
(2, 'usuarios', 'visualizar', FALSE),
(2, 'usuarios', 'criar', FALSE),
(2, 'usuarios', 'editar', FALSE),
(2, 'usuarios', 'excluir', FALSE),

-- Financeiro (acesso apenas ao financeiro e visualização de obras)
(4, 'obras', 'visualizar', TRUE),
(4, 'obras', 'criar', FALSE),
(4, 'obras', 'editar', FALSE),
(4, 'obras', 'excluir', FALSE),
(4, 'financeiro', 'visualizar', TRUE),
(4, 'financeiro', 'criar', TRUE),
(4, 'financeiro', 'editar', TRUE),
(4, 'financeiro', 'excluir', FALSE),
(4, 'cronograma', 'visualizar', TRUE),
(4, 'cronograma', 'criar', FALSE),
(4, 'cronograma', 'editar', FALSE),
(4, 'cronograma', 'excluir', FALSE),
(4, 'documentos', 'visualizar', TRUE),
(4, 'documentos', 'criar', FALSE),
(4, 'documentos', 'editar', FALSE),
(4, 'documentos', 'excluir', FALSE),
(4, 'usuarios', 'visualizar', FALSE),
(4, 'usuarios', 'criar', FALSE),
(4, 'usuarios', 'editar', FALSE),
(4, 'usuarios', 'excluir', FALSE);
