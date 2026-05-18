<?php
/**
 * Script para instalar o menu de Gestão de Reservas
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../conexao.php';

try {
    $resultado = [];
    
    // Inserir menu
    $codigo = 'gestao_reservas';
    $nome = 'Gestão de Reservas';
    $descricao = 'Gerenciar todas as reservas de almoço do sistema';
    $descricao_card = 'Gestão de Reservas';
    $url = '/painel/gestao_reservas.php';
    $icone = 'bi-calendar-check';
    $categoria = 'refeicoes';
    $ordem = 5;
    
    $stmt = $conn->prepare("
        INSERT INTO menus (codigo, nome, descricao, descricao_card, url, icone, categoria, ordem, acesso_padrao, requer_admin, requer_culto, ativo) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 1)
        ON DUPLICATE KEY UPDATE
            nome = VALUES(nome),
            descricao = VALUES(descricao),
            descricao_card = VALUES(descricao_card),
            url = VALUES(url),
            icone = VALUES(icone),
            categoria = VALUES(categoria),
            ordem = VALUES(ordem),
            ativo = 1,
            requer_admin = 0
    ");
    $stmt->bind_param("sssssssi", $codigo, $nome, $descricao, $descricao_card, $url, $icone, $categoria, $ordem);
    
    if ($stmt->execute()) {
        $resultado[] = "Menu 'Gestão de Reservas' criado/atualizado com sucesso";
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



