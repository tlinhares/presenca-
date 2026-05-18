<?php
/**
 * Script para instalar o menu de gerenciamento de mensagens WhatsApp
 */
header('Content-Type: text/html; charset=utf-8');
session_start();

require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../conexao.php';

echo "<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <title>Instalar Menu - Mensagens WhatsApp</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🔧 Instalar Menu - Gerenciar Mensagens WhatsApp</h1>";

try {
    // Verificar se menu já existe
    $stmt = $conn->prepare("SELECT id FROM menus WHERE codigo = 'gerenciar_mensagens_whatsapp'");
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows > 0) {
        echo "<div class='info'>ℹ️ O menu 'gerenciar_mensagens_whatsapp' já existe.</div>";
        echo "<div class='success'>✅ Menu já está instalado. Nenhuma alteração necessária.</div>";
    } else {
        // Criar menu
        $codigo = 'gerenciar_mensagens_whatsapp';
        $nome = 'Gerenciar Mensagens WhatsApp';
        $url = '/painel/gerenciar_mensagens_whatsapp.php';
        $descricao = 'Configure as mensagens variadas enviadas via WhatsApp';
        $icone = 'bi-whatsapp';
        $categoria = 'gerenciamento';
        $ordem = 100;
        $acesso_padrao = 0; // Só admin
        $requer_culto = 0;
        $requer_admin = 1; // Exclusivo para admin
        
        $stmt = $conn->prepare("
            INSERT INTO menus (codigo, nome, descricao, url, icone, categoria, ordem, acesso_padrao, requer_culto, requer_admin) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssiiii", $codigo, $nome, $descricao, $url, $icone, $categoria, $ordem, $acesso_padrao, $requer_culto, $requer_admin);
        
        if ($stmt->execute()) {
            $menu_id = $conn->insert_id;
            echo "<div class='success'>✅ Menu criado com sucesso! (ID: $menu_id)</div>";
            
            // Limpar cache
            MenuPermissaoService::limparCache();
            echo "<div class='info'>ℹ️ Cache limpo automaticamente.</div>";
        } else {
            throw new Exception("Erro ao criar menu: " . $conn->error);
        }
        
        $stmt->close();
    }
    
    echo "<div class='success'><h3>✅ Instalação concluída!</h3></div>";
    echo "<div class='info'>";
    echo "<p><strong>Próximos passos:</strong></p>";
    echo "<ul>";
    echo "<li>Acesse: <a href='../../painel/gerenciar_mensagens_whatsapp.php'>Gerenciar Mensagens WhatsApp</a></li>";
    echo "<li>Ou através do menu: <strong>Gerenciamento → Gerenciar Mensagens WhatsApp</strong></li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'><h3>❌ Erro:</h3><p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
}

echo "</body></html>";
?>

