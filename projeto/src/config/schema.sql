-- Script SQL para criação do banco de dados e tabelas
-- Sistema de Gestão de Construtora

-- Criação do banco de dados
CREATE DATABASE IF NOT EXISTS sistema_construtora CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE sistema_construtora;

-- Tabela de perfis de usuário
CREATE TABLE IF NOT EXISTS perfis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT
);

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    perfil_id INT NOT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (perfil_id) REFERENCES perfis(id)
);

-- Tabela de clientes
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cpf_cnpj VARCHAR(20) UNIQUE,
    telefone VARCHAR(20),
    email VARCHAR(100),
    endereco TEXT,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de fornecedores
CREATE TABLE IF NOT EXISTS fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_fantasia VARCHAR(100) NOT NULL,
    razao_social VARCHAR(100),
    cnpj VARCHAR(20) UNIQUE,
    telefone VARCHAR(20),
    email VARCHAR(100),
    endereco TEXT,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de status de obra
CREATE TABLE IF NOT EXISTS status_obra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT
);

-- Tabela de obras
CREATE TABLE IF NOT EXISTS obras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    cliente_id INT,
    endereco_obra TEXT,
    data_inicio_prevista DATE,
    data_fim_prevista DATE,
    data_inicio_real DATE,
    data_fim_real DATE,
    status_id INT NOT NULL,
    orcamento_total DECIMAL(15,2),
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (status_id) REFERENCES status_obra(id)
);

-- Tabela de etapas da obra
CREATE TABLE IF NOT EXISTS etapas_obra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    obra_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    ordem INT NOT NULL,
    FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE
);

-- Tabela de status de tarefa
CREATE TABLE IF NOT EXISTS status_tarefa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT
);

-- Tabela de tarefas do cronograma
CREATE TABLE IF NOT EXISTS tarefas_cronograma (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etapa_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    responsavel_id INT,
    data_inicio_prevista DATE,
    data_fim_prevista DATE,
    data_inicio_real DATE,
    data_fim_real DATE,
    duracao_estimada_dias INT,
    status_tarefa_id INT NOT NULL,
    percentual_concluido INT DEFAULT 0,
    FOREIGN KEY (etapa_id) REFERENCES etapas_obra(id) ON DELETE CASCADE,
    FOREIGN KEY (responsavel_id) REFERENCES usuarios(id),
    FOREIGN KEY (status_tarefa_id) REFERENCES status_tarefa(id),
    CHECK (percentual_concluido >= 0 AND percentual_concluido <= 100)
);

-- Tabela de categorias de custo
CREATE TABLE IF NOT EXISTS categorias_custo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT
);

-- Tabela de custos
CREATE TABLE IF NOT EXISTS custos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    obra_id INT NOT NULL,
    etapa_id INT,
    categoria_id INT NOT NULL,
    fornecedor_id INT,
    descricao VARCHAR(255) NOT NULL,
    data_custo DATE NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    comprovante_path VARCHAR(255),
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
    FOREIGN KEY (etapa_id) REFERENCES etapas_obra(id),
    FOREIGN KEY (categoria_id) REFERENCES categorias_custo(id),
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id)
);

-- Tabela de categorias de documento
CREATE TABLE IF NOT EXISTS categorias_documento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT
);

-- Tabela de documentos
CREATE TABLE IF NOT EXISTS documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    obra_id INT NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    path_arquivo VARCHAR(255) NOT NULL,
    tipo_arquivo VARCHAR(50),
    data_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
    categoria_doc_id INT,
    usuario_id INT,
    FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_doc_id) REFERENCES categorias_documento(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Inserção de dados iniciais

-- Perfis de usuário
INSERT INTO perfis (nome, descricao) VALUES 
('Administrador', 'Acesso total ao sistema'),
('Gerente', 'Acesso a todas as obras e relatórios'),
('Engenheiro', 'Acesso às obras designadas'),
('Financeiro', 'Acesso ao módulo financeiro'),
('Funcionário', 'Acesso básico ao sistema');

-- Status de obra
INSERT INTO status_obra (nome, descricao) VALUES 
('Planejada', 'Obra em fase de planejamento'),
('Em Andamento', 'Obra em execução'),
('Concluída', 'Obra finalizada'),
('Pausada', 'Obra temporariamente interrompida'),
('Cancelada', 'Obra cancelada');

-- Status de tarefa
INSERT INTO status_tarefa (nome, descricao) VALUES 
('A Fazer', 'Tarefa ainda não iniciada'),
('Em Andamento', 'Tarefa em execução'),
('Concluída', 'Tarefa finalizada'),
('Atrasada', 'Tarefa com prazo vencido'),
('Bloqueada', 'Tarefa impedida de prosseguir');

-- Categorias de custo
INSERT INTO categorias_custo (nome, descricao) VALUES 
('Material de Construção', 'Materiais utilizados diretamente na obra'),
('Mão de Obra Direta', 'Custos com trabalhadores da obra'),
('Mão de Obra Indireta', 'Custos com supervisão e gerenciamento'),
('Equipamentos', 'Aluguel ou compra de equipamentos'),
('Taxas e Impostos', 'Custos com licenças, alvarás e impostos'),
('Administrativo', 'Custos administrativos relacionados à obra');

-- Categorias de documento
INSERT INTO categorias_documento (nome, descricao) VALUES 
('Projetos', 'Plantas e projetos técnicos'),
('Legalização', 'Documentos legais, alvarás e licenças'),
('Financeiro', 'Notas fiscais e comprovantes de pagamento'),
('Fotos', 'Registros fotográficos da obra'),
('Contratos', 'Contratos com clientes e fornecedores');

-- Usuário administrador inicial (senha: admin123)
INSERT INTO usuarios (nome, email, senha_hash, perfil_id) VALUES 
('Administrador', 'admin@construtora.com', '$2y$10$8tDjXvMmvAlRRgYV8Ywzwe2nVUa0XdBZKNVVqjYVsVuTnJGWUa9Uy', 1);
