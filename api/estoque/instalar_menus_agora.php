<?php
/**
 * Script de instalação rápida dos menus de configuração do estoque
 * Execute via navegador ou linha de comando
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Se executado via linha de comando, não precisa de sessão
if (php_sapi_name() !== 'cli') {
    session_start();
    require_once __DIR__ . '/../../auth/verifica_sessao.php';
    require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
    
    // Apenas admin pode executar via navegador
    if (!MenuPermissaoService::isAdmin()) {
        die("Acesso negado. Apenas administradores podem executar este script.");
    }
}

require_once __DIR__ . '/../../api/conexao.php';

echo "<h1>Instalação dos Menus de Configuração do Estoque</h1>\n";
echo "<pre>\n";

try {
    $conn->begin_transaction();
    
    // 1. Criar/atualizar menus
    $menus = [
        ['codigo' => 'estoque_config_departamentos', 'nome' => 'Departamentos Estoque', 'descricao' => 'Gerenciar departamentos do estoque', 'descricao_card' => 'Setores', 'url' => '/estoque/configuracoes/departamentos.php', 'icone' => 'bi-building', 'ordem' => 1],
        ['codigo' => 'estoque_config_categorias', 'nome' => 'Categorias Estoque', 'descricao' => 'Gerenciar categorias de produtos', 'descricao_card' => 'Tipos', 'url' => '/estoque/configuracoes/categorias.php', 'icone' => 'bi-tags', 'ordem' => 2],
        ['codigo' => 'estoque_config_unidades', 'nome' => 'Unidades Estoque', 'descricao' => 'Gerenciar unidades de medida', 'descricao_card' => 'Medidas', 'url' => '/estoque/configuracoes/unidades.php', 'icone' => 'bi-rulers', 'ordem' => 3],
        ['codigo' => 'estoque_config_fornecedores', 'nome' => 'Fornecedores', 'descricao' => 'Gerenciar fornecedores', 'descricao_card' => 'Parceiros', 'url' => '/estoque/configuracoes/fornecedores.php', 'icone' => 'bi-truck', 'ordem' => 4],
        ['codigo' => 'estoque_config_responsaveis', 'nome' => 'Responsáveis Estoque', 'descricao' => 'Gerenciar responsáveis pelo estoque', 'descricao_card' => 'Gestores', 'url' => '/estoque/configuracoes/responsaveis.php', 'icone' => 'bi-people', 'ordem' => 5],
        ['codigo' => 'estoque_config_localizacoes', 'nome' => 'Localizações Estoque', 'descricao' => 'Gerenciar localizações e prateleiras', 'descricao_card' => 'Prateleiras', 'url' => '/estoque/configuracoes/localizacoes.php', 'icone' => 'bi-geo-alt', 'ordem' => 6]
    ];
    
    $menuIds = [];
    $grupoId = 11; // Estoque - Administrador
    
    echo "1. Criando/atualizando menus...\n";
    foreach ($menus as $menu) {
        // Verificar se existe
        $stmt = $conn->prepare("SELECT id FROM menus WHERE codigo = ?");
        $stmt->bind_param("s", $menu['codigo']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Atualizar
            $row = $result->fetch_assoc();
            $menuId = $row['id'];
            $stmt->close();
            
            $stmt = $conn->prepare("UPDATE menus SET nome = ?, descricao = ?, descricao_card = ?, url = ?, icone = ?, categoria = 'estoque_config', ordem = ?, ativo = 1, requer_admin = 0 WHERE id = ?");
            $stmt->bind_param("sssssii", $menu['nome'], $menu['descricao'], $menu['descricao_card'], $menu['url'], $menu['icone'], $menu['ordem'], $menuId);
            $stmt->execute();
            $stmt->close();
            echo "   ✓ Menu '{$menu['codigo']}' atualizado (ID: $menuId)\n";
        } else {
            // Criar
            $stmt = $conn->prepare("INSERT INTO menus (codigo, nome, descricao, descricao_card, url, icone, categoria, ordem, acesso_padrao, requer_admin, requer_culto, ativo) VALUES (?, ?, ?, ?, ?, ?, 'estoque_config', ?, 0, 0, 0, 1)");
            $stmt->bind_param("ssssssi", $menu['codigo'], $menu['nome'], $menu['descricao'], $menu['descricao_card'], $menu['url'], $menu['icone'], $menu['ordem']);
            $stmt->execute();
            $menuId = $conn->insert_id;
            $stmt->close();
            echo "   ✓ Menu '{$menu['codigo']}' criado (ID: $menuId)\n";
        }
        
        $menuIds[$menu['codigo']] = $menuId;
    }
    
    // 2. Vincular ao grupo
    echo "\n2. Vinculando menus ao grupo 'Estoque - Administrador' (ID: $grupoId)...\n";
    $stmtInsert = $conn->prepare("INSERT IGNORE INTO grupo_menus (grupo_id, menu_id) VALUES (?, ?)");
    foreach ($menuIds as $codigo => $menuId) {
        $stmtInsert->bind_param("ii", $grupoId, $menuId);
        if ($stmtInsert->execute()) {
            if ($conn->affected_rows > 0) {
                echo "   ✓ Vínculo criado: grupo $grupoId <-> menu '{$codigo}' (ID: $menuId)\n";
            } else {
                echo "   - Vínculo já existe: grupo $grupoId <-> menu '{$codigo}' (ID: $menuId)\n";
            }
        }
    }
    $stmtInsert->close();
    
    // 3. Verificar usuário teste (ID: 22236)
    echo "\n3. Verificando usuário 'Usuário teste' (ID: 22236)...\n";
    $usuarioId = 22236;
    $stmt = $conn->prepare("SELECT id, nome FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();
    
    if ($usuario) {
        echo "   ✓ Usuário encontrado: {$usuario['nome']}\n";
        
        // Verificar se está no grupo
        $stmt = $conn->prepare("SELECT grupo_id FROM usuario_grupos WHERE usuario_id = ? AND grupo_id = ?");
        $stmt->bind_param("ii", $usuarioId, $grupoId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "   ✓ Usuário já está no grupo\n";
        } else {
            // Adicionar ao grupo
            $stmt = $conn->prepare("INSERT INTO usuario_grupos (usuario_id, grupo_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $usuarioId, $grupoId);
            if ($stmt->execute()) {
                echo "   ✓ Usuário adicionado ao grupo\n";
            } else {
                throw new Exception("Erro ao adicionar usuário ao grupo: " . $conn->error);
            }
            $stmt->close();
        }
    } else {
        echo "   ⚠ Usuário ID 22236 não encontrado (pode ser outro ID)\n";
    }
    
    // 4. Limpar cache
    if (php_sapi_name() !== 'cli') {
        MenuPermissaoService::limparCache();
        echo "\n4. Cache de permissões limpo\n";
    }
    
    $conn->commit();
    
    echo "\n✅ INSTALAÇÃO CONCLUÍDA COM SUCESSO!\n";
    echo "\nPróximos passos:\n";
    echo "1. Faça logout e login novamente com o 'Usuário teste'\n";
    echo "2. Os menus de configuração devem aparecer no dashboard\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
echo "</pre>\n";

