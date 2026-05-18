-- Trigger final para detectar atualização de foto de usuário
-- Usa INSERT ... ON DUPLICATE KEY UPDATE para lidar com constraint única

DELIMITER $$

DROP TRIGGER IF EXISTS tr_usuario_foto_atualizada_culto$$
DROP TRIGGER IF EXISTS tr_teste_simples$$

CREATE TRIGGER tr_usuario_foto_atualizada_culto
AFTER UPDATE ON usuarios
FOR EACH ROW
BEGIN
    -- Verificar se a foto foi alterada e o usuário está ativo
    IF (
        (OLD.foto_base64 IS NULL AND NEW.foto_base64 IS NOT NULL) OR
        (OLD.foto_base64 IS NOT NULL AND NEW.foto_base64 IS NULL) OR
        (OLD.foto_base64 IS NOT NULL AND NEW.foto_base64 IS NOT NULL AND OLD.foto_base64 != NEW.foto_base64)
    ) AND NEW.ativo = 1 THEN
        
        -- Inserir ou atualizar registro de sincronização para todos os dispositivos de culto ativos
        INSERT INTO facial_sync_culto (id_usuario, id_dispositivo, status, data, origem, detalhes)
        SELECT 
            NEW.id,
            df.id,
            'pendente',
            CURDATE(),
            'culto',
            CONCAT('Foto atualizada automaticamente: ', NEW.nome)
        FROM dispositivos_faciais df
        WHERE df.tipo_dispositivo = 'culto' AND df.ativo = 1
        ON DUPLICATE KEY UPDATE
            status = 'pendente',
            detalhes = CONCAT('Foto atualizada automaticamente: ', NEW.nome),
            ultima_tentativa = NULL;
    END IF;
END$$

DELIMITER ;
