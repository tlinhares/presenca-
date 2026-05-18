-- ╔════════════════════════════════════════════════════════════════╗
-- ║  MÓDULO DE FROTA - TABELAS DO BANCO DE DADOS                  ║
-- ║  Sistema de Controle de Veículos                              ║
-- ╚════════════════════════════════════════════════════════════════╝

-- Tabela de veículos
CREATE TABLE IF NOT EXISTS frota_veiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    placa VARCHAR(10) NOT NULL UNIQUE,
    modelo VARCHAR(100) NOT NULL,
    marca VARCHAR(50) NOT NULL,
    ano INT,
    cor VARCHAR(30),
    km_atual INT DEFAULT 0,
    status ENUM('disponivel', 'em_uso', 'manutencao', 'inativo') DEFAULT 'disponivel',
    foto_veiculo VARCHAR(255),
    observacoes TEXT,
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de utilização (registro de saída/entrada)
CREATE TABLE IF NOT EXISTS frota_utilizacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_veiculo INT NOT NULL,
    id_usuario BIGINT UNSIGNED NOT NULL,
    
    -- Dados de SAÍDA
    data_saida DATETIME NOT NULL,
    km_saida INT NOT NULL,
    destino VARCHAR(255),
    motivo VARCHAR(255),
    foto_km_saida VARCHAR(255),
    foto_selfie_saida VARCHAR(255),
    foto_veiculo_saida_1 VARCHAR(255),
    foto_veiculo_saida_2 VARCHAR(255),
    foto_veiculo_saida_3 VARCHAR(255),
    observacoes_saida TEXT,
    
    -- Dados de ENTRADA (preenchidos no retorno)
    data_entrada DATETIME,
    km_entrada INT,
    foto_km_entrada VARCHAR(255),
    foto_selfie_entrada VARCHAR(255),
    foto_veiculo_entrada_1 VARCHAR(255),
    foto_veiculo_entrada_2 VARCHAR(255),
    foto_veiculo_entrada_3 VARCHAR(255),
    observacoes_entrada TEXT,
    
    -- Cálculos
    km_percorrido INT DEFAULT NULL,
    tempo_utilizacao INT DEFAULT NULL, -- em minutos
    
    -- Status
    status ENUM('em_andamento', 'finalizado', 'cancelado') DEFAULT 'em_andamento',
    
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_veiculo (id_veiculo),
    INDEX idx_usuario (id_usuario),
    INDEX idx_status (status),
    INDEX idx_data_saida (data_saida),
    
    FOREIGN KEY (id_veiculo) REFERENCES frota_veiculos(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de manutenções
CREATE TABLE IF NOT EXISTS frota_manutencoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_veiculo INT NOT NULL,
    id_usuario_registro BIGINT UNSIGNED NOT NULL,
    tipo ENUM('preventiva', 'corretiva', 'revisao', 'troca_oleo', 'pneus', 'outro') NOT NULL,
    descricao TEXT NOT NULL,
    km_manutencao INT,
    valor DECIMAL(10,2),
    data_manutencao DATE NOT NULL,
    data_proxima_revisao DATE,
    km_proxima_revisao INT,
    comprovante VARCHAR(255),
    status ENUM('pendente', 'em_andamento', 'concluida') DEFAULT 'pendente',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_veiculo (id_veiculo),
    INDEX idx_status (status),
    
    FOREIGN KEY (id_veiculo) REFERENCES frota_veiculos(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_usuario_registro) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de abastecimentos
CREATE TABLE IF NOT EXISTS frota_abastecimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_veiculo INT NOT NULL,
    id_usuario BIGINT UNSIGNED NOT NULL,
    id_utilizacao INT DEFAULT NULL, -- Pode estar vinculado a uma utilização
    data_abastecimento DATETIME NOT NULL,
    km_abastecimento INT NOT NULL,
    litros DECIMAL(10,2) NOT NULL,
    valor_litro DECIMAL(10,3) NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    tipo_combustivel ENUM('gasolina', 'etanol', 'diesel', 'gnv') NOT NULL,
    posto VARCHAR(100),
    foto_comprovante VARCHAR(255),
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_veiculo (id_veiculo),
    INDEX idx_utilizacao (id_utilizacao),
    
    FOREIGN KEY (id_veiculo) REFERENCES frota_veiculos(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_utilizacao) REFERENCES frota_utilizacoes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de checklist de inspeção
CREATE TABLE IF NOT EXISTS frota_checklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utilizacao INT NOT NULL,
    tipo ENUM('saida', 'entrada') NOT NULL,
    
    -- Itens do checklist
    pneus_ok TINYINT(1) DEFAULT NULL,
    farois_ok TINYINT(1) DEFAULT NULL,
    lanternas_ok TINYINT(1) DEFAULT NULL,
    retrovisores_ok TINYINT(1) DEFAULT NULL,
    limpador_ok TINYINT(1) DEFAULT NULL,
    freios_ok TINYINT(1) DEFAULT NULL,
    documentos_ok TINYINT(1) DEFAULT NULL,
    extintor_ok TINYINT(1) DEFAULT NULL,
    triangulo_ok TINYINT(1) DEFAULT NULL,
    estepe_ok TINYINT(1) DEFAULT NULL,
    nivel_combustivel VARCHAR(20),
    limpeza_ok TINYINT(1) DEFAULT NULL,
    ar_condicionado_ok TINYINT(1) DEFAULT NULL,
    avarias_encontradas TEXT,
    
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_utilizacao (id_utilizacao),
    
    FOREIGN KEY (id_utilizacao) REFERENCES frota_utilizacoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ╔════════════════════════════════════════════════════════════════╗
-- ║  INSERIR MENUS DO MÓDULO NO SISTEMA DE PERMISSÕES             ║
-- ╚════════════════════════════════════════════════════════════════╝

-- Inserir menus da frota (acesso_padrao=0 para admin gerenciar permissões)
INSERT INTO menus (codigo, nome, descricao, descricao_card, url, icone, cor, categoria, ordem, requer_admin, acesso_padrao, ativo) VALUES
('frota_dashboard', 'Dashboard Frota', 'Painel principal do módulo de frota', 'Controle de veículos', '/frota/dashboard.php', 'bi-truck', 'info', 'frota', 1, 0, 0, 1),
('frota_retirar', 'Retirar Veículo', 'Retirar veículo para uso', 'Registrar saída de veículo', '/frota/retirar.php', 'bi-box-arrow-right', 'success', 'frota', 2, 0, 0, 1),
('frota_devolver', 'Devolver Veículo', 'Devolver veículo após uso', 'Registrar entrada de veículo', '/frota/devolver.php', 'bi-box-arrow-in-left', 'warning', 'frota', 3, 0, 0, 1),
('frota_historico', 'Histórico Próprio', 'Ver histórico de utilizações', 'Minhas utilizações de veículos', '/frota/historico.php', 'bi-clock-history', 'secondary', 'frota', 4, 0, 0, 1),
('frota_admin_veiculos', 'Admin Veículos', 'Gerenciar veículos da frota', 'Cadastrar e editar veículos', '/frota/admin/veiculos.php', 'bi-car-front', 'primary', 'frota', 5, 0, 0, 1),
('frota_admin_manutencoes', 'Admin Manutenções', 'Gerenciar manutenções', 'Registrar manutenções', '/frota/admin/manutencoes.php', 'bi-tools', 'danger', 'frota', 6, 0, 0, 1),
('frota_relatorios', 'Relatórios Frota', 'Relatórios do módulo', 'Relatórios de utilização', '/frota/relatorios.php', 'bi-graph-up', 'purple', 'frota', 7, 0, 0, 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- ╔════════════════════════════════════════════════════════════════╗
-- ║  INSERIR VEÍCULOS DE EXEMPLO (OPCIONAL)                       ║
-- ╚════════════════════════════════════════════════════════════════╝

-- Descomente as linhas abaixo para inserir veículos de exemplo
-- INSERT INTO frota_veiculos (placa, modelo, marca, ano, cor, km_atual, status) VALUES
-- ('ABC-1234', 'HR Caminhão', 'Hyundai', 2020, 'Branco', 141818, 'disponivel'),
-- ('DEF-5678', 'ATEGO 2430', 'Mercedes Benz', 2019, 'Branco', 534923, 'disponivel');

