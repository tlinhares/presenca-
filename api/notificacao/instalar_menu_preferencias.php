<?php
/**
 * Script para instalar o menu de gerenciamento de preferências de notificações
 */
header('Content-Type: text/html; charset=utf-8');
session_start();

require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../../api/conexao.php';

echo "<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <title>Instalar Menu - Preferências de Notificações</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🔧 Instalar Menu - Preferências de Notificações</h1>";

try {
    $conn->begin_transaction();
    
    // Verificar se menu já existe
    $stmt = $conn->prepare("SELECT id FROM menus WHERE codigo = 'gerenciar_preferencias_notificacoes'");
    $stmt->execute();
    $result = $stmt->get_result();
    $menu_existe = $result->num_rows > 0;
    $stmt->close();
    
    if ($menu_existe) {
        // Atualizar menu existente
        $stmt = $conn->prepare("
            UPDATE menus SET 
                nome = ?,
                descricao = ?,
                descricao_card = ?,
                url = ?,
                icone = ?,
                categoria = ?,
                ordem = ?,
                acesso_padrao = ?,
                requer_admin = ?,
                requer_culto = ?,
                ativo = 1
            WHERE codigo = 'gerenciar_preferencias_notificacoes'
        ");
        
        $nome = 'Preferências de Notificações';
        $descricao = 'Gerenciar preferências de notificação dos usuários';
        $descricao_card = 'Preferências Notificações';
        $url = '/painel/gerenciar_preferencias_notificacoes.php';
        $icone = 'bi-bell';
        $categoria = 'gerenciamento';
        $ordem = 101;
        $acesso_padrao = 0;
        $requer_admin = 1;
        $requer_culto = 0;
        
        $stmt->bind_param("ssssssiiii", $nome, $descricao, $descricao_card, $url, $icone, $categoria, $ordem, $acesso_padrao, $requer_admin, $requer_culto);
        
        if ($stmt->execute()) {
            $menu_row = $conn->query("SELECT id FROM menus WHERE codigo = 'gerenciar_preferencias_notificacoes'")->fetch_assoc();
            $menu_id = $menu_row['id'];
            echo "<div class='success'>✅ Menu atualizado com sucesso! (ID: $menu_id)</div>";
        } else {
            throw new Exception("Erro ao atualizar menu: " . $conn->error);
        }
        $stmt->close();
    } else {
        // Criar menu novo
        $stmt = $conn->prepare("
            INSERT INTO menus (codigo, nome, descricao, descricao_card, url, icone, categoria, ordem, acesso_padrao, requer_admin, requer_culto, ativo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $codigo = 'gerenciar_preferencias_notificacoes';
        $nome = 'Preferências de Notificações';
        $descricao = 'Gerenciar preferências de notificação dos usuários';
        $descricao_card = 'Preferências Notificações';
        $url = '/painel/gerenciar_preferencias_notificacoes.php';
        $icone = 'bi-bell';
        $categoria = 'gerenciamento';
        $ordem = 101;
        $acesso_padrao = 0;
        $requer_admin = 1;
        $requer_culto = 0;
        
        $stmt->bind_param("sssssssiiii", $codigo, $nome, $descricao, $descricao_card, $url, $icone, $categoria, $ordem, $acesso_padrao, $requer_admin, $requer_culto);
        
        if ($stmt->execute()) {
            $menu_id = $conn->insert_id;
            echo "<div class='success'>✅ Menu criado com sucesso! (ID: $menu_id)</div>";
        } else {
            throw new Exception("Erro ao criar menu: " . $conn->error);
        }
        $stmt->close();
    }
    
    // Limpar cache
    MenuPermissaoService::limparCache();
    echo "<div class='info'>ℹ️ Cache de permissões limpo automaticamente.</div>";
    
    $conn->commit();
    
    echo "<div class='success'><h3>✅ Instalação concluída!</h3></div>";
    echo "<div class='info'>";
    echo "<p><strong>Próximos passos:</strong></p>";
    echo "<ul>";
    echo "<li>Acesse: <a href='../../painel/gerenciar_preferencias_notificacoes.php'>Gerenciar Preferências de Notificações</a></li>";
    echo "<li>Ou através do menu: <strong>Gerenciamento → Preferências de Notificações</strong></li>";
    echo "<li>O menu pode ser gerenciado através do sistema de menus (gerenciar_menus.php)</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "<div class='error'><strong>❌ Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
