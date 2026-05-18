<?php
/**
 * Script de Inicialização do Sistema Multi-API WhatsApp
 * 
 * Verifica se as tabelas existem, cria se necessário e insere configurações padrão
 * Pode ser executado múltiplas vezes sem problemas (idempotente)
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

// Apenas admin pode executar
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../../api/conexao.php';

try {
    $conn->begin_transaction();
    
    // 1. Criar tabela whatsapp_apis se não existir
    $sql_apis = "CREATE TABLE IF NOT EXISTS whatsapp_apis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nome identificador da API (ex: numeroti, numeropresenca)',
        url_mensagem VARCHAR(500) NOT NULL COMMENT 'URL completa da API para envio de mensagens',
        url_arquivo VARCHAR(500) NOT NULL COMMENT 'URL completa da API para envio de arquivos',
        token TEXT NOT NULL COMMENT 'Token de autenticação Bearer',
        numero_whatsapp VARCHAR(20) NULL COMMENT 'Número do WhatsApp associado (opcional)',
        ativo TINYINT(1) DEFAULT 1 COMMENT '1=Ativa, 0=Inativa',
        prioridade INT DEFAULT 0 COMMENT 'Ordem de tentativa (menor = maior prioridade)',
        total_envios INT DEFAULT 0 COMMENT 'Contador de envios bem-sucedidos',
        total_falhas INT DEFAULT 0 COMMENT 'Contador de falhas',
        ultima_utilizacao DATETIME NULL COMMENT 'Última vez que foi utilizada',
        observacoes TEXT NULL COMMENT 'Observações sobre a API',
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ativo (ativo),
        INDEX idx_prioridade (prioridade),
        INDEX idx_nome (nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='APIs de WhatsApp cadastradas no sistema'";
    
    $conn->query($sql_apis);
    
    // 2. Criar tabela whatsapp_config_notificacoes se não existir
    $sql_config = "CREATE TABLE IF NOT EXISTS whatsapp_config_notificacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo_notificacao VARCHAR(50) NOT NULL UNIQUE COMMENT 'Tipo de notificação (ex: propria, lembrete_reserva)',
        modo_selecao ENUM('especifica', 'sorteio', 'desabilitado') DEFAULT 'sorteio' COMMENT 'Como selecionar a API',
        id_api_especifica INT NULL COMMENT 'Se modo=especifica, ID da API a usar',
        ids_apis_sorteio JSON NULL COMMENT 'Se modo=sorteio, array de IDs de APIs para sortear',
        tentativas_maximas INT DEFAULT 3 COMMENT 'Número máximo de tentativas antes de fallback para email',
        desabilitar_whatsapp TINYINT(1) DEFAULT 0 COMMENT 'Se 1, envia direto por email sem tentar WhatsApp',
        observacoes TEXT NULL,
        atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_api_especifica) REFERENCES whatsapp_apis(id) ON DELETE SET NULL,
        INDEX idx_tipo (tipo_notificacao),
        INDEX idx_modo (modo_selecao)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuração de APIs por tipo de notificação'";
    
    $conn->query($sql_config);
    
    // 3. Inserir configurações padrão para cada tipo de notificação (se não existirem)
    $tipos_notificacao = [
        'propria',
        'adicional',
        'multipla',
        'cancelada',
        'lembrete_reserva',
        'justificativa_culto',
        'cadastro_usuario',
        'relatorio_diario'
    ];
    
    foreach ($tipos_notificacao as $tipo) {
        $stmt = $conn->prepare("
            INSERT IGNORE INTO whatsapp_config_notificacoes 
            (tipo_notificacao, modo_selecao, tentativas_maximas, desabilitar_whatsapp) 
            VALUES (?, 'sorteio', 3, 0)
        ");
        $stmt->bind_param("s", $tipo);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Sistema inicializado com sucesso! Tabelas criadas e configurações padrão inseridas.'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Erro ao inicializar sistema multi-API WhatsApp: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao inicializar: ' . $e->getMessage()
    ]);
}

$conn->close();
