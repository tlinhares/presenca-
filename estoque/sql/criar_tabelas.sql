-- ╔════════════════════════════════════════════════════════════════════════════╗
-- ║  MÓDULO DE ESTOQUE - ESTRUTURA DO BANCO DE DADOS                          ║
-- ║  Sistema de Controle de Estoque Multi-Departamento                         ║
-- ╚════════════════════════════════════════════════════════════════════════════╝

-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: estoque_departamentos
-- Departamentos/setores que possuem estoque próprio
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS estoque_departamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    codigo VARCHAR(20) UNIQUE,
    cor VARCHAR(7) DEFAULT '#6c757d',
    icone VARCHAR(50) DEFAULT 'bi-box',
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ativo (ativo),
    INDEX idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: estoque_responsaveis
-- Responsáveis por cada departamento de estoque
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS estoque_responsaveis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_departamento INT NOT NULL,
    id_usuario BIGINT UNSIGNED NOT NULL,
    tipo ENUM('responsavel', 'auxiliar') DEFAULT 'auxiliar',
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_departamento) REFERENCES estoque_departamentos(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE RESTRICT,
    UNIQUE KEY uk_depto_usuario (id_departamento, id_usuario),
    INDEX idx_usuario (id_usuario),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: estoque_categorias
-- Categorias de produtos (hierárquica - suporta subcategorias)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS estoque_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    id_categoria_pai INT DEFAULT NULL,
    cor VARCHAR(7) DEFAULT '#6c757d',
    icone VARCHAR(50) DEFAULT 'bi-tag',
    ordem INT DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_categoria_pai) REFERENCES estoque_categorias(id) ON DELETE SET NULL,
    INDEX idx_ativo (ativo),
    INDEX idx_pai (id_categoria_pai),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: estoque_unidades
-- Unidades de medida (un, kg, m, L, cx, pct, etc)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS estoque_unidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    sigla VARCHAR(10) NOT NULL,
    descricao TEXT,
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_sigla (sigla),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir unidades padrão
INSERT INTO estoque_unidades (nome, sigla, descricao) VALUES
('Unidade', 'UN', 'Unidade individual'),
('Quilograma', 'KG', 'Medida de peso'),
('Grama', 'G', 'Medida de peso'),
('Litro', 'L', 'Medida de volume'),
('Mililitro', 'ML', 'Medida de volume'),
('Metro', 'M', 'Medida de comprimento'),
('Centímetro', 'CM', 'Medida de comprimento'),
('Caixa', 'CX', 'Caixa com múltiplas unidades'),
('Pacote', 'PCT', 'Pacote com múltiplas unidades'),
('Fardo', 'FD', 'Fardo com múltiplas unidades'),
('Rolo', 'RL', 'Rolo de material'),
('Folha', 'FL', 'Folha individual'),
('Resma', 'RSM', 'Resma de papel (500 folhas)'),
('Galão', 'GL', 'Galão de líquido'),
('Saco', 'SC', 'Saco com material'),
('Peça', 'PÇ', 'Peça individual'),
('Par', 'PR', 'Par de itens'),
('Jogo', 'JG', 'Jogo/conjunto de itens'),
('Kit', 'KIT', 'Kit com múltiplos itens'),
('Metro Quadrado', 'M²', 'Medida de área'),
('Metro Cúbico', 'M³', 'Medida de volume')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: estoque_localizacoes
-- Localizações físicas dentro dos departamentos (prateleiras, armários, etc)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS estoque_localizacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_departamento INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    codigo VARCHAR(20),
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_departamento) REFERENCES estoque_departamentos(id) ON DELETE RESTRICT,
    INDEX idx_departamento (id_departamento),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: estoque_fornecedores
-- Fornecedores de produtos
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS estoque_fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    razao_social VARCHAR(200) NOT NULL,
    nome_fantasia VARCHAR(200),
    cnpj VARCHAR(18) UNIQUE,
    inscricao_estadual VARCHAR(20),
    endereco VARCHAR(255),
    cidade VARCHAR(100),
    uf CHAR(2),
    cep VARCHAR(10),
    telefone VARCHAR(20),
    email VARCHAR(100),
    contato VARCHAR(100),
    observacoes TEXT,
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cnpj (cnpj),
    INDEX idx_ativo (ativo),
    INDEX idx_nome (nome_fantasia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: estoque_produtos
-- Cadastro de produtos
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS estoque_produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50),
    codigo_barras VARCHAR(50),
    nome VARCHAR(200) NOT NULL,
    descricao TEXT,
    id_categoria INT,
    id_unidade INT NOT NULL,
    id_departamento INT NOT NULL,
    id_localizacao INT,
    
    -- Controle de estoque
    quantidade_atual DECIMAL(15,4) DEFAULT 0,
    quantidade_minima DECIMAL(15,4) DEFAULT 0,
    quantidade_ideal DECIMAL(15,4) DEFAULT 0,
    quantidade_maxima DECIMAL(15,4) DEFAULT NULL,
    
    -- Valores
    valor_unitario DECIMAL(15,4) DEFAULT 0,
    valor_medio DECIMAL(15,4) DEFAULT 0,
    
    -- Dados adicionais
    marca VARCHAR(100),
    modelo VARCHAR(100),
    ncm VARCHAR(10),
    foto_base64 LONGTEXT,
    observacoes TEXT,
    
    -- Controle
    permite_fracionamento TINYINT(1) DEFAULT 0,
    controla_validade TINYINT(1) DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_categoria) REFERENCES estoque_categorias(id) ON DELETE SET NULL,
    FOREIGN KEY (id_unidade) REFERENCES estoque_unidades(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_departamento) REFERENCES estoque_departamentos(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_localizacao) REFERENCES estoque_localizacoes(id) ON DELETE SET NULL,
    
    INDEX idx_codigo (codigo),
    INDEX idx_codigo_barras (codigo_barras),
    INDEX idx_nome (nome),
    INDEX idx_categoria (id_categoria),
    INDEX idx_departamento (id_departamento),
    INDEX idx_ativo (ativo),
    INDEX idx_estoque_baixo (quantidade_atual, quantidade_minima)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: estoque_notas_fiscais
-- Notas fiscais importadas (XML)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS estoque_notas_fiscais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave_acesso VARCHAR(44) UNIQUE,
    numero VARCHAR(20) NOT NULL,
    serie VARCHAR(5),
    data_emissao DATE NOT NULL,
    data_entrada DATE,
    
    -- Fornecedor
    id_fornecedor INT,
    cnpj_emitente VARCHAR(18),
    nome_emitente VARCHAR(200),
    
    -- Valores
    valor_total DECIMAL(15,2),
    valor_produtos DECIMAL(15,2),
    valor_frete DECIMAL(15,2),
    valor_desconto DECIMAL(15,2),
    
    -- Dados da importação
    id_departamento INT NOT NULL,
    id_usuario_importacao BIGINT UNSIGNED NOT NULL,
    xml_conteudo LONGTEXT,
    
    -- Controle
    status ENUM('pendente', 'processada', 'cancelada') DEFAULT 'pendente',
    observacoes TEXT,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_fornecedor) REFERENCES estoque_fornecedores(id) ON DELETE SET NULL,
    FOREIGN KEY (id_departamento) REFERENCES estoque_departamentos(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_usuario_importacao) REFERENCES usuarios(id) ON DELETE RESTRICT,
    
    INDEX idx_chave (chave_acesso),
    INDEX idx_numero (numero),
    INDEX idx_data (data_emissao),
    INDEX idx_departamento (id_departamento),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: estoque_movimentacoes
-- Registro de todas as movimentações (entradas e saídas)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS estoque_movimentacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_produto INT NOT NULL,
    id_departamento INT NOT NULL,
    
    tipo ENUM('entrada', 'saida', 'ajuste', 'transferencia') NOT NULL,
    quantidade DECIMAL(15,4) NOT NULL,
    quantidade_anterior DECIMAL(15,4) NOT NULL,
    quantidade_posterior DECIMAL(15,4) NOT NULL,
    
    -- Valores
    valor_unitario DECIMAL(15,4) DEFAULT 0,
    valor_total DECIMAL(15,4) DEFAULT 0,
    
    -- Origem da movimentação
    origem ENUM('manual', 'nf_xml', 'requisicao', 'ajuste_inventario', 'transferencia') NOT NULL,
    id_nota_fiscal INT DEFAULT NULL,
    id_requisicao INT DEFAULT NULL,
    
    -- Dados adicionais
    lote VARCHAR(50),
    data_validade DATE,
    observacoes TEXT,
    
    -- Rastreabilidade
    id_usuario BIGINT UNSIGNED NOT NULL,
    data_movimentacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_produto) REFERENCES estoque_produtos(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_departamento) REFERENCES estoque_departamentos(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_nota_fiscal) REFERENCES estoque_notas_fiscais(id) ON DELETE SET NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE RESTRICT,
    
    INDEX idx_produto (id_produto),
    INDEX idx_departamento (id_departamento),
    INDEX idx_tipo (tipo),
    INDEX idx_data (data_movimentacao),
    INDEX idx_origem (origem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: estoque_requisicoes
-- Requisições de materiais
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS estoque_requisicoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) UNIQUE,
    
    -- Departamento de origem (quem está requisitando)
    id_departamento_origem INT,
    -- Departamento destino (de onde vai sair o material)
    id_departamento_destino INT NOT NULL,
    
    -- Solicitante e responsáveis
    id_solicitante BIGINT UNSIGNED NOT NULL,
    id_aprovador BIGINT UNSIGNED DEFAULT NULL,
    id_entregador BIGINT UNSIGNED DEFAULT NULL,
    
    -- Dados da requisição
    motivo TEXT,
    finalidade ENUM('consumo_interno', 'evento', 'construcao', 'reforma', 'manutencao', 'outro') DEFAULT 'consumo_interno',
    prioridade ENUM('baixa', 'normal', 'alta', 'urgente') DEFAULT 'normal',
    
    -- Status e datas
    status ENUM('rascunho', 'pendente', 'aprovada', 'parcial', 'entregue', 'cancelada', 'rejeitada') DEFAULT 'rascunho',
    data_solicitacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_necessidade DATE,
    data_aprovacao DATETIME,
    data_entrega DATETIME,
    
    -- Observações
    observacoes_solicitante TEXT,
    observacoes_aprovador TEXT,
    observacoes_entrega TEXT,
    
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_departamento_origem) REFERENCES estoque_departamentos(id) ON DELETE SET NULL,
    FOREIGN KEY (id_departamento_destino) REFERENCES estoque_departamentos(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_solicitante) REFERENCES usuarios(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_aprovador) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (id_entregador) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    INDEX idx_numero (numero),
    INDEX idx_status (status),
    INDEX idx_solicitante (id_solicitante),
    INDEX idx_departamento_destino (id_departamento_destino),
    INDEX idx_data (data_solicitacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: estoque_requisicoes_itens
-- Itens de cada requisição
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS estoque_requisicoes_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_requisicao INT NOT NULL,
    id_produto INT NOT NULL,
    
    quantidade_solicitada DECIMAL(15,4) NOT NULL,
    quantidade_aprovada DECIMAL(15,4) DEFAULT NULL,
    quantidade_entregue DECIMAL(15,4) DEFAULT 0,
    
    observacoes TEXT,
    status ENUM('pendente', 'aprovado', 'parcial', 'entregue', 'cancelado') DEFAULT 'pendente',
    
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_requisicao) REFERENCES estoque_requisicoes(id) ON DELETE CASCADE,
    FOREIGN KEY (id_produto) REFERENCES estoque_produtos(id) ON DELETE RESTRICT,
    
    INDEX idx_requisicao (id_requisicao),
    INDEX idx_produto (id_produto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: estoque_alertas
-- Alertas de estoque (mínimo, vencimento, etc)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS estoque_alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_produto INT NOT NULL,
    id_departamento INT NOT NULL,
    
    tipo ENUM('estoque_minimo', 'estoque_zerado', 'vencimento_proximo', 'vencido') NOT NULL,
    mensagem TEXT,
    
    lido TINYINT(1) DEFAULT 0,
    id_usuario_leitura BIGINT UNSIGNED DEFAULT NULL,
    data_leitura DATETIME DEFAULT NULL,
    
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_produto) REFERENCES estoque_produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_departamento) REFERENCES estoque_departamentos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario_leitura) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    INDEX idx_produto (id_produto),
    INDEX idx_departamento (id_departamento),
    INDEX idx_tipo (tipo),
    INDEX idx_lido (lido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: estoque_inventarios
-- Registro de inventários/contagens físicas
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS estoque_inventarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_departamento INT NOT NULL,
    
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME DEFAULT NULL,
    
    status ENUM('em_andamento', 'finalizado', 'cancelado') DEFAULT 'em_andamento',
    
    id_usuario_inicio BIGINT UNSIGNED NOT NULL,
    id_usuario_fim BIGINT UNSIGNED DEFAULT NULL,
    
    observacoes TEXT,
    
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_departamento) REFERENCES estoque_departamentos(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_usuario_inicio) REFERENCES usuarios(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_usuario_fim) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    INDEX idx_departamento (id_departamento),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- TABELA: estoque_inventarios_itens
-- Itens contados em cada inventário
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS estoque_inventarios_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_inventario INT NOT NULL,
    id_produto INT NOT NULL,
    
    quantidade_sistema DECIMAL(15,4) NOT NULL,
    quantidade_contada DECIMAL(15,4) DEFAULT NULL,
    diferenca DECIMAL(15,4) DEFAULT NULL,
    
    ajustado TINYINT(1) DEFAULT 0,
    id_movimentacao_ajuste INT DEFAULT NULL,
    
    observacoes TEXT,
    
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_inventario) REFERENCES estoque_inventarios(id) ON DELETE CASCADE,
    FOREIGN KEY (id_produto) REFERENCES estoque_produtos(id) ON DELETE RESTRICT,
    
    INDEX idx_inventario (id_inventario),
    INDEX idx_produto (id_produto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- TRIGGER: Gerar número sequencial para requisições
-- ═══════════════════════════════════════════════════════════════════════════════
DELIMITER //
CREATE TRIGGER IF NOT EXISTS trg_requisicao_numero
BEFORE INSERT ON estoque_requisicoes
FOR EACH ROW
BEGIN
    DECLARE novo_numero INT;
    DECLARE ano_atual VARCHAR(4);
    
    SET ano_atual = YEAR(NOW());
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(numero, 5) AS UNSIGNED)), 0) + 1 
    INTO novo_numero
    FROM estoque_requisicoes 
    WHERE numero LIKE CONCAT(ano_atual, '%');
    
    SET NEW.numero = CONCAT(ano_atual, LPAD(novo_numero, 6, '0'));
END//
DELIMITER ;

-- ═══════════════════════════════════════════════════════════════════════════════
-- VIEWS úteis
-- ═══════════════════════════════════════════════════════════════════════════════

-- View de produtos com estoque baixo
CREATE OR REPLACE VIEW vw_estoque_baixo AS
SELECT 
    p.id,
    p.codigo,
    p.nome,
    p.quantidade_atual,
    p.quantidade_minima,
    p.quantidade_ideal,
    d.nome as departamento,
    c.nome as categoria,
    u.sigla as unidade,
    CASE 
        WHEN p.quantidade_atual <= 0 THEN 'zerado'
        WHEN p.quantidade_atual <= p.quantidade_minima THEN 'critico'
        ELSE 'baixo'
    END as nivel_alerta
FROM estoque_produtos p
JOIN estoque_departamentos d ON p.id_departamento = d.id
LEFT JOIN estoque_categorias c ON p.id_categoria = c.id
JOIN estoque_unidades u ON p.id_unidade = u.id
WHERE p.ativo = 1 
  AND p.quantidade_atual <= p.quantidade_minima
ORDER BY p.quantidade_atual ASC;

-- View de movimentações recentes
CREATE OR REPLACE VIEW vw_movimentacoes_recentes AS
SELECT 
    m.id,
    m.tipo,
    m.quantidade,
    m.origem,
    m.data_movimentacao,
    p.nome as produto,
    p.codigo as codigo_produto,
    d.nome as departamento,
    u.nome as usuario
FROM estoque_movimentacoes m
JOIN estoque_produtos p ON m.id_produto = p.id
JOIN estoque_departamentos d ON m.id_departamento = d.id
JOIN usuarios u ON m.id_usuario = u.id
ORDER BY m.data_movimentacao DESC
LIMIT 100;

-- View de requisições pendentes
CREATE OR REPLACE VIEW vw_requisicoes_pendentes AS
SELECT 
    r.id,
    r.numero,
    r.status,
    r.prioridade,
    r.data_solicitacao,
    r.data_necessidade,
    s.nome as solicitante,
    do.nome as departamento_origem,
    dd.nome as departamento_destino,
    (SELECT COUNT(*) FROM estoque_requisicoes_itens WHERE id_requisicao = r.id) as total_itens
FROM estoque_requisicoes r
JOIN usuarios s ON r.id_solicitante = s.id
LEFT JOIN estoque_departamentos do ON r.id_departamento_origem = do.id
JOIN estoque_departamentos dd ON r.id_departamento_destino = dd.id
WHERE r.status IN ('pendente', 'aprovada', 'parcial')
ORDER BY 
    FIELD(r.prioridade, 'urgente', 'alta', 'normal', 'baixa'),
    r.data_necessidade ASC;


