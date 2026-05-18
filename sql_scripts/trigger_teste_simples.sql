-- Trigger de teste simples para verificar se triggers funcionam

DELIMITER $$

DROP TRIGGER IF EXISTS tr_teste_simples$$

CREATE TRIGGER tr_teste_simples
AFTER UPDATE ON usuarios
FOR EACH ROW
BEGIN
    -- Sempre inserir um registro quando qualquer campo for atualizado
    INSERT INTO facial_sync_culto (id_usuario, id_dispositivo, status, data, origem, detalhes)
    SELECT 
        NEW.id,
        df.id,
        'pendente',
        CURDATE(),
        'culto',
        CONCAT('TESTE - Usuário atualizado: ', NEW.nome)
    FROM dispositivos_faciais df
    WHERE df.tipo_dispositivo = 'culto' AND df.ativo = 1
    LIMIT 1;
END$$

DELIMITER ;
