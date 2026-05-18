-- Triggers para sincronização automática de usuários com dispositivos faciais de culto
-- Executar após criar as tabelas principais

DELIMITER $$

-- Trigger para quando um usuário é inserido (cadastrado)
CREATE TRIGGER tr_usuario_inserido_culto
AFTER INSERT ON usuarios
FOR EACH ROW
BEGIN
    -- Só processar se o usuário está ativo
    IF NEW.ativo = 1 THEN
        -- Inserir registros de sincronização para todos os dispositivos de culto ativos
        INSERT INTO facial_sync_culto (id_usuario, id_dispositivo, status, data, origem, detalhes)
        SELECT 
            NEW.id,
            df.id,
            'pendente',
            CURDATE(),
            'trigger_cadastro',
            CONCAT('Usuário cadastrado automaticamente: ', NEW.nome)
        FROM dispositivos_faciais df
        WHERE df.tipo_dispositivo = 'culto' AND df.ativo = 1;
        
        -- Log da ação
        INSERT INTO logs_sistema (tipo, mensagem, dados, data_criacao)
        VALUES (
            'trigger_usuario_cadastrado',
            CONCAT('Usuário cadastrado: ', NEW.nome, ' (ID: ', NEW.id, ')'),
            JSON_OBJECT('usuario_id', NEW.id, 'nome', NEW.nome, 'email', NEW.email),
            NOW()
        );
    END IF;
END$$

-- Trigger para quando um usuário é atualizado (especialmente mudança de status ativo)
CREATE TRIGGER tr_usuario_atualizado_culto
AFTER UPDATE ON usuarios
FOR EACH ROW
BEGIN
    -- Se o usuário foi ativado (era inativo e agora está ativo)
    IF OLD.ativo = 0 AND NEW.ativo = 1 THEN
        -- Inserir registros de sincronização para todos os dispositivos de culto ativos
        INSERT INTO facial_sync_culto (id_usuario, id_dispositivo, status, data, origem, detalhes)
        SELECT 
            NEW.id,
            df.id,
            'pendente',
            CURDATE(),
            'trigger_ativacao',
            CONCAT('Usuário ativado automaticamente: ', NEW.nome)
        FROM dispositivos_faciais df
        WHERE df.tipo_dispositivo = 'culto' AND df.ativo = 1;
        
        -- Log da ação
        INSERT INTO logs_sistema (tipo, mensagem, dados, data_criacao)
        VALUES (
            'trigger_usuario_ativado',
            CONCAT('Usuário ativado: ', NEW.nome, ' (ID: ', NEW.id, ')'),
            JSON_OBJECT('usuario_id', NEW.id, 'nome', NEW.nome, 'email', NEW.email),
            NOW()
        );
    END IF;
    
    -- Se o usuário foi inativado (era ativo e agora está inativo)
    IF OLD.ativo = 1 AND NEW.ativo = 0 THEN
        -- Marcar registros de sincronização para remoção
        UPDATE facial_sync_culto 
        SET status = 'remover', 
            detalhes = CONCAT('Usuário inativado automaticamente: ', NEW.nome),
            ultima_tentativa = NOW()
        WHERE id_usuario = NEW.id AND status = 'sincronizado';
        
        -- Log da ação
        INSERT INTO logs_sistema (tipo, mensagem, dados, data_criacao)
        VALUES (
            'trigger_usuario_inativado',
            CONCAT('Usuário inativado: ', NEW.nome, ' (ID: ', NEW.id, ')'),
            JSON_OBJECT('usuario_id', NEW.id, 'nome', NEW.nome, 'email', NEW.email),
            NOW()
        );
    END IF;
END$$

-- Trigger para quando um dispositivo facial é inserido
CREATE TRIGGER tr_dispositivo_inserido_culto
AFTER INSERT ON dispositivos_faciais
FOR EACH ROW
BEGIN
    -- Se é um dispositivo de culto ativo
    IF NEW.tipo_dispositivo = 'culto' AND NEW.ativo = 1 THEN
        -- Inserir registros de sincronização para todos os usuários ativos
        INSERT INTO facial_sync_culto (id_usuario, id_dispositivo, status, data, origem, detalhes)
        SELECT 
            u.id,
            NEW.id,
            'pendente',
            CURDATE(),
            'trigger_dispositivo_cadastrado',
            CONCAT('Dispositivo cadastrado automaticamente: ', NEW.nome)
        FROM usuarios u
        WHERE u.ativo = 1;
        
        -- Log da ação
        INSERT INTO logs_sistema (tipo, mensagem, dados, data_criacao)
        VALUES (
            'trigger_dispositivo_cadastrado',
            CONCAT('Dispositivo de culto cadastrado: ', NEW.nome, ' (IP: ', NEW.ip, ')'),
            JSON_OBJECT('dispositivo_id', NEW.id, 'nome', NEW.nome, 'ip', NEW.ip),
            NOW()
        );
    END IF;
END$$

-- Trigger para quando um dispositivo facial é atualizado
CREATE TRIGGER tr_dispositivo_atualizado_culto
AFTER UPDATE ON dispositivos_faciais
FOR EACH ROW
BEGIN
    -- Se o dispositivo foi ativado (era inativo e agora está ativo)
    IF OLD.ativo = 0 AND NEW.ativo = 1 AND NEW.tipo_dispositivo = 'culto' THEN
        -- Inserir registros de sincronização para todos os usuários ativos
        INSERT INTO facial_sync_culto (id_usuario, id_dispositivo, status, data, origem, detalhes)
        SELECT 
            u.id,
            NEW.id,
            'pendente',
            CURDATE(),
            'trigger_dispositivo_ativado',
            CONCAT('Dispositivo ativado automaticamente: ', NEW.nome)
        FROM usuarios u
        WHERE u.ativo = 1;
        
        -- Log da ação
        INSERT INTO logs_sistema (tipo, mensagem, dados, data_criacao)
        VALUES (
            'trigger_dispositivo_ativado',
            CONCAT('Dispositivo de culto ativado: ', NEW.nome, ' (IP: ', NEW.ip, ')'),
            JSON_OBJECT('dispositivo_id', NEW.id, 'nome', NEW.nome, 'ip', NEW.ip),
            NOW()
        );
    END IF;
    
    -- Se o dispositivo foi inativado (era ativo e agora está inativo)
    IF OLD.ativo = 1 AND NEW.ativo = 0 AND NEW.tipo_dispositivo = 'culto' THEN
        -- Marcar registros de sincronização para remoção
        UPDATE facial_sync_culto 
        SET status = 'dispositivo_inativo', 
            detalhes = CONCAT('Dispositivo inativado: ', NEW.nome),
            ultima_tentativa = NOW()
        WHERE id_dispositivo = NEW.id AND status = 'sincronizado';
        
        -- Log da ação
        INSERT INTO logs_sistema (tipo, mensagem, dados, data_criacao)
        VALUES (
            'trigger_dispositivo_inativado',
            CONCAT('Dispositivo de culto inativado: ', NEW.nome, ' (IP: ', NEW.ip, ')'),
            JSON_OBJECT('dispositivo_id', NEW.id, 'nome', NEW.nome, 'ip', NEW.ip),
            NOW()
        );
    END IF;
END$$

DELIMITER ;

-- Criar tabela de logs do sistema se não existir
CREATE TABLE IF NOT EXISTS logs_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    mensagem TEXT NOT NULL,
    dados JSON,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_data (data_criacao)
);

-- Adicionar coluna 'remover' ao status se não existir
ALTER TABLE facial_sync_culto 
MODIFY COLUMN status ENUM('pendente', 'sincronizado', 'falha', 'remover', 'dispositivo_inativo') 
DEFAULT 'pendente';

-- Comentários sobre os triggers
/*
TRIGGERS IMPLEMENTADOS:

1. tr_usuario_inserido_culto:
   - Dispara quando um novo usuário é cadastrado
   - Cria registros de sincronização para todos os dispositivos de culto ativos
   - Só processa se o usuário está ativo

2. tr_usuario_atualizado_culto:
   - Dispara quando um usuário é atualizado
   - Se ativado: cria registros de sincronização
   - Se inativado: marca registros para remoção

3. tr_dispositivo_inserido_culto:
   - Dispara quando um novo dispositivo de culto é cadastrado
   - Cria registros de sincronização para todos os usuários ativos

4. tr_dispositivo_atualizado_culto:
   - Dispara quando um dispositivo é atualizado
   - Se ativado: cria registros de sincronização
   - Se inativado: marca registros como dispositivo_inativo

BENEFÍCIOS:
- Sincronização automática sem intervenção manual
- Todos os usuários ativos sempre ficam no facial
- Usuários inativos são removidos automaticamente
- Logs completos de todas as ações
- Sistema mais eficiente e confiável
*/
