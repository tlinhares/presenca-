<?php
/**
 * Script para remover usuários do sistema facial que cancelaram suas reservas
 * Executa a cada minuto via crontab
 */

// Configurar timezone
date_default_timezone_set('America/Cuiaba');

// Log de execução
$logFile = __DIR__ . '/../logs/cron_remocoes.log';
$timestamp = date('Y-m-d H:i:s');

function logRemocoes($mensagem) {
    global $logFile, $timestamp;
    file_put_contents($logFile, "[$timestamp] $mensagem\n", FILE_APPEND);
}

logRemocoes("=== INICIANDO VERIFICAÇÃO DE REMOÇÕES ===");

try {
    // Incluir arquivos necessários
    require_once __DIR__ . '/../api/conexao.php';
    require_once __DIR__ . '/../utils/config.php';
    
    // Buscar configurações dos dispositivos faciais (inclui `modelo` para
    // roteamento SS / XPE pelo IntelbrasDriver — pode ser NULL = trata como SS).
    $sql_dispositivos = "SELECT id, nome, ip, porta, usuario, senha, modelo FROM dispositivos_faciais WHERE ativo = 1 AND tipo_dispositivo = 'restaurante'";
    $result_dispositivos = $conn->query($sql_dispositivos);
    
    if ($result_dispositivos->num_rows == 0) {
        logRemocoes("Nenhum dispositivo facial ativo encontrado");
        exit(0);
    }
    
    $dispositivos = [];
    while ($row = $result_dispositivos->fetch_assoc()) {
        $dispositivos[] = $row;
    }
    
    logRemocoes("Encontrados " . count($dispositivos) . " dispositivos ativos");
    
    // Buscar usuários que cancelaram reservas hoje e ainda estão no facial_sync
    // EXCLUIR usuários do culto (culto = 1)
    $data_hoje = date('Y-m-d');
    $sql_cancelados = "
        SELECT DISTINCT fs.id_usuario, fs.origem, fs.id_dispositivo, 
               COALESCE(u.nome, d.nome) as nome_usuario
        FROM facial_sync fs
        LEFT JOIN usuarios u ON fs.id_usuario = u.id AND fs.origem = 'usuario'
        LEFT JOIN dependentes d ON fs.id_usuario = d.id AND fs.origem = 'dependente'
        WHERE fs.data = ? 
        AND fs.status = 'sincronizado'
        AND (u.culto = 0 OR u.culto IS NULL)  -- EXCLUIR usuários do culto
        AND NOT EXISTS (
            SELECT 1 FROM reservas_almoco r 
            WHERE r.id_usuario = fs.id_usuario 
            AND r.data = ?
        )
        AND NOT EXISTS (
            SELECT 1 FROM reservas_adicionais ra 
            JOIN dependentes dep ON ra.id_dependente = dep.id
            WHERE dep.id = fs.id_usuario 
            AND ra.data = ?
            AND fs.origem = 'dependente'
        )
    ";
    
    $stmt = $conn->prepare($sql_cancelados);
    $stmt->bind_param("sss", $data_hoje, $data_hoje, $data_hoje);
    $stmt->execute();
    $result_cancelados = $stmt->get_result();
    
    $usuarios_para_remover = [];
    while ($row = $result_cancelados->fetch_assoc()) {
        $usuarios_para_remover[] = $row;
    }
    
    if (empty($usuarios_para_remover)) {
        logRemocoes("Nenhum usuário para remover encontrado");
        exit(0);
    }
    
    logRemocoes("Encontrados " . count($usuarios_para_remover) . " usuários para remover");
    
    $total_removidos = 0;
    $total_falhas = 0;
    
    // Processar cada dispositivo
    foreach ($dispositivos as $dispositivo) {
        logRemocoes("Processando dispositivo: " . $dispositivo['nome'] . " (" . $dispositivo['ip'] . ")");
        
        $removidos_dispositivo = 0;
        $falhas_dispositivo = 0;
        
        foreach ($usuarios_para_remover as $usuario) {
            // Só processar usuários deste dispositivo
            if ($usuario['id_dispositivo'] != $dispositivo['id']) {
                continue;
            }
            
            try {
                // Usar IntelbrasDriver — mesmo motor que o projeto `acesso`
                // (escola segura) usa em produção. Cobre SS/XPE com fallbacks
                // automáticos de URL/método/auth.
                require_once __DIR__ . '/../utils/intelbras_driver.php';

                $driver = new IntelbrasDriver(
                    (string) $dispositivo['ip'],
                    (int) ($dispositivo['porta'] ?? 80),
                    (string) ($dispositivo['usuario'] ?? ''),
                    (string) ($dispositivo['senha'] ?? ''),
                    (string) ($dispositivo['modelo'] ?? '')
                );

                $r = $driver->deleteUser((int) $usuario['id_usuario']);

                if (!empty($r['ok'])) {
                    // Atualizar status na tabela facial_sync
                    $update_sql = "UPDATE facial_sync
                                      SET status = 'removido',
                                          detalhes = ?,
                                          ultima_tentativa = NOW()
                                    WHERE id_usuario = ?
                                      AND id_dispositivo = ?
                                      AND data = ?";
                    $stmt_update = $conn->prepare($update_sql);
                    $detalhes = sprintf(
                        "Removido automaticamente — reserva cancelada (%s/%s HTTP %d)",
                        $r['mode']  ?? '?',
                        $r['auth']  ?? '?',
                        (int) ($r['http'] ?? 0)
                    );
                    $stmt_update->bind_param("siis", $detalhes, $usuario['id_usuario'], $usuario['id_dispositivo'], $data_hoje);
                    $stmt_update->execute();
                    $stmt_update->close();

                    $removidos_dispositivo++;
                    logRemocoes("✓ Removido: {$usuario['nome_usuario']} (ID: {$usuario['id_usuario']}) "
                              . "via {$r['path']} [{$r['mode']}/{$r['auth']}] no dispositivo {$dispositivo['nome']}");
                } else {
                    $falhas_dispositivo++;
                    $msg = sprintf(
                        "HTTP %d | path=%s | mode=%s | auth=%s | err=%s | raw=%s",
                        (int) ($r['http'] ?? 0),
                        (string) ($r['path'] ?? ''),
                        (string) ($r['mode'] ?? ''),
                        (string) ($r['auth'] ?? ''),
                        (string) ($r['err']  ?? ''),
                        mb_substr((string) ($r['raw'] ?? ''), 0, 120)
                    );
                    logRemocoes("✗ Falha ao remover: {$usuario['nome_usuario']} (ID: {$usuario['id_usuario']}) - $msg");

                    // Marca falha e incrementa tentativas — útil pra diagnóstico.
                    $upFail = $conn->prepare(
                        "UPDATE facial_sync
                            SET tentativas = COALESCE(tentativas, 0) + 1,
                                ultima_tentativa = NOW(),
                                detalhes = ?
                          WHERE id_usuario = ? AND id_dispositivo = ? AND data = ?"
                    );
                    $det = mb_substr($msg, 0, 250);
                    $upFail->bind_param("siis", $det, $usuario['id_usuario'], $usuario['id_dispositivo'], $data_hoje);
                    $upFail->execute();
                    $upFail->close();
                }
            } catch (Exception $e) {
                $falhas_dispositivo++;
                logRemocoes("✗ Erro ao remover: {$usuario['nome_usuario']} (ID: {$usuario['id_usuario']}) - " . $e->getMessage());
            }
        }
        
        $total_removidos += $removidos_dispositivo;
        $total_falhas += $falhas_dispositivo;
        
        logRemocoes("Dispositivo {$dispositivo['nome']}: $removidos_dispositivo removidos, $falhas_dispositivo falhas");
    }
    
    logRemocoes("=== RESUMO: $total_removidos removidos, $total_falhas falhas ===");
    
} catch (Exception $e) {
    logRemocoes("ERRO: " . $e->getMessage());
    exit(1);
}

logRemocoes("=== VERIFICAÇÃO DE REMOÇÕES CONCLUÍDA ===");
?>
