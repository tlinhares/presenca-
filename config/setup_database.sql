-- Criar o banco de dados se não existir
CREATE DATABASE IF NOT EXISTS presenca_aom;

-- Criar o usuário se não existir
CREATE USER IF NOT EXISTS 'presenca_user'@'localhost' IDENTIFIED BY 'Presenca@2024';

-- Conceder privilégios ao usuário
GRANT ALL PRIVILEGES ON presenca_aom.* TO 'presenca_user'@'localhost';

-- Aplicar as alterações
FLUSH PRIVILEGES;

-- Usar o banco de dados
USE presenca_aom;

-- Criar tabela de usuários se não existir
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    categoria ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Criar tabela de presenças se não existir
CREATE TABLE IF NOT EXISTS presencas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    data_hora DATETIME NOT NULL,
    tipo ENUM('entrada', 'saida') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
); 