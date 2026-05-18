<?php
/**
 * Script para instalar a tabela dias_fechado e o menu
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../conexao.php';

try {
    $resultado = [];
    
    // Criar tabela dias_fechado
    $sql_tabela = "
        CREATE TABLE IF NOT EXISTS dias_fechado (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data DATE NOT NULL UNIQUE COMMENT 'Data em que o refeitório estará fechado',
            motivo VARCHAR(255) DEFAULT NULL COMMENT 'Motivo do fechamento (ex: feriado, manutenção)',
            observacoes TEXT DEFAULT NULL COMMENT 'Observações adicionais sobre o fechamento',
            ativo TINYINT(1) DEFAULT 1 COMMENT 'Se o registro está ativo (1) ou inativo (0)',
            criado_por BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID do usuário que criou o registro',
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora de criação',
            atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data e hora da última atualização',
            INDEX idx_data (data),
            INDEX idx_ativo (ativo),
            INDEX idx_data_ativo (data, ativo),
            FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela para cadastrar datas em que o refeitório não funcionará'
    ";
    
    if ($conn->query($sql_tabela)) {
        $resultado[] = "Tabela dias_fechado criada/verificada com sucesso";
    } else {
        throw new Exception("Erro ao criar tabela: " . $conn->error);
    }
    
    // Inserir menu
    $codigo = 'gerenciamento_dias_fechado';
    $nome = 'Dias Fechado do Refeitório';
    $descricao = 'Gerenciar datas em que o refeitório não funcionará';
    $descricao_card = 'Dias Fechado';
    $url = '/painel/dias_fechado.php';
    $icone = 'bi-calendar-x';
    $categoria = 'gerenciamento';
    $ordem = 10;
    
    $stmt = $conn->prepare("
        INSERT INTO menus (codigo, nome, descricao, descricao_card, url, icone, categoria, ordem, acesso_padrao, requer_admin, requer_culto, ativo) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 1, 0, 1)
        ON DUPLICATE KEY UPDATE
            nome = VALUES(nome),
            descricao = VALUES(descricao),
            descricao_card = VALUES(descricao_card),
            url = VALUES(url),
            icone = VALUES(icone),
            categoria = VALUES(categoria),
            ordem = VALUES(ordem),
            ativo = 1,
            requer_admin = 1
    ");
    $stmt->bind_param("sssssssi", $codigo, $nome, $descricao, $descricao_card, $url, $icone, $categoria, $ordem);
    
    if ($stmt->execute()) {
        $resultado[] = "Menu 'Dias Fechado do Refeitório' criado/atualizado com sucesso";
    } else {
        throw new Exception("Erro ao criar menu: " . $conn->error);
    }
    $stmt->close();
    
    // Limpar cache de permissões
    MenuPermissaoService::limparCache();
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Instalação concluída com sucesso!',
        'detalhes' => $resultado
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro na instalação: ' . $e->getMessage()
    ]);
}
?>

