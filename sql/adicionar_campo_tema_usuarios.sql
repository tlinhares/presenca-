-- Adicionar campo tema na tabela usuarios
-- Este script adiciona o campo tema para armazenar a preferência de tema do usuário

ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS tema VARCHAR(10) DEFAULT 'light' COMMENT 'Tema preferido: light ou dark';

-- Atualizar todos os usuários existentes para tema padrão 'light' se o campo for NULL
UPDATE usuarios SET tema = 'light' WHERE tema IS NULL OR tema = '';
