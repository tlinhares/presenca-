<?php
/**
 * Script para instalar o menu de gerenciamento de APIs WhatsApp
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
    <title>Instalar Menu - APIs WhatsApp</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🔧 Instalar Menu - APIs WhatsApp</h1>";

try {
    $conn->begin_transaction();
    
    // Verificar se menu já existe
    $stmt = $conn->prepare("SELECT id FROM menus WHERE codigo = 'gerenciar_whatsapp_apis'");
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
            WHERE codigo = 'gerenciar_whatsapp_apis'
        ");
        
        $codigo = 'gerenciar_whatsapp_apis';
        $nome = 'APIs WhatsApp';
        $descricao = 'Gerenciar APIs de WhatsApp e configurações de notificações';
        $descricao_card = 'APIs WhatsApp';
        $url = '/painel/whatsapp_apis.php';
        $icone = 'bi-whatsapp';
        $categoria = 'gerenciamento';
        $ordem = 100;
        $acesso_padrao = 0;
        $requer_admin = 1;
        $requer_culto = 0;
        
        $stmt->bind_param("ssssssiiii", $nome, $descricao, $descricao_card, $url, $icone, $categoria, $ordem, $acesso_padrao, $requer_admin, $requer_culto);
        
        if ($stmt->execute()) {
            echo "<div class='info'>ℹ️ Menu 'gerenciar_whatsapp_apis' já existia e foi atualizado.</div>";
        } else {
            throw new Exception("Erro ao atualizar menu: " . $conn->error);
        }
        $stmt->close();
        
        // Buscar ID do menu
        $stmt = $conn->prepare("SELECT id FROM menus WHERE codigo = 'gerenciar_whatsapp_apis'");
        $stmt->execute();
        $result = $stmt->get_result();
        $menu_row = $result->fetch_assoc();
        $menu_id = $menu_row['id'];
        $stmt->close();
    } else {
        // Criar menu novo
        $stmt = $conn->prepare("
            INSERT INTO menus (codigo, nome, descricao, descricao_card, url, icone, categoria, ordem, acesso_padrao, requer_admin, requer_culto, ativo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $codigo = 'gerenciar_whatsapp_apis';
        $nome = 'APIs WhatsApp';
        $descricao = 'Gerenciar APIs de WhatsApp e configurações de notificações';
        $descricao_card = 'APIs WhatsApp';
        $url = '/painel/whatsapp_apis.php';
        $icone = 'bi-whatsapp';
        $categoria = 'gerenciamento';
        $ordem = 100;
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
    echo "<li>Acesse: <a href='../../painel/whatsapp_apis.php'>Gerenciar APIs WhatsApp</a></li>";
    echo "<li>Ou através do menu: <strong>Gerenciamento → APIs WhatsApp</strong></li>";
    echo "<li>O menu pode ser gerenciado através do sistema de menus (gerenciar_menus.php)</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "<div class='error'><h3>❌ Erro:</h3><p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
}

echo "</body></html>";
?>
