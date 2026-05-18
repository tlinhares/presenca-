<?php
/**
 * API para instalar menus de configuração do estoque no banco de dados
 * Execute este arquivo uma vez para criar os menus dinamicamente
 */
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

// Apenas admin pode executar
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../../api/conexao.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $conn->begin_transaction();
    
    // Menus a serem criados
    $menus = [
        [
            'codigo' => 'estoque_config_departamentos',
            'nome' => 'Departamentos Estoque',
            'descricao' => 'Gerenciar departamentos do estoque',
            'descricao_card' => 'Setores',
            'url' => '/estoque/configuracoes/departamentos.php',
            'icone' => 'bi-building',
            'categoria' => 'estoque_config',
            'ordem' => 1
        ],
        [
            'codigo' => 'estoque_config_categorias',
            'nome' => 'Categorias Estoque',
            'descricao' => 'Gerenciar categorias de produtos',
            'descricao_card' => 'Tipos',
            'url' => '/estoque/configuracoes/categorias.php',
            'icone' => 'bi-tags',
            'categoria' => 'estoque_config',
            'ordem' => 2
        ],
        [
            'codigo' => 'estoque_config_unidades',
            'nome' => 'Unidades Estoque',
            'descricao' => 'Gerenciar unidades de medida',
            'descricao_card' => 'Medidas',
            'url' => '/estoque/configuracoes/unidades.php',
            'icone' => 'bi-rulers',
            'categoria' => 'estoque_config',
            'ordem' => 3
        ],
        [
            'codigo' => 'estoque_config_fornecedores',
            'nome' => 'Fornecedores',
            'descricao' => 'Gerenciar fornecedores',
            'descricao_card' => 'Parceiros',
            'url' => '/estoque/configuracoes/fornecedores.php',
            'icone' => 'bi-truck',
            'categoria' => 'estoque_config',
            'ordem' => 4
        ],
        [
            'codigo' => 'estoque_config_responsaveis',
            'nome' => 'Responsáveis Estoque',
            'descricao' => 'Gerenciar responsáveis pelo estoque',
            'descricao_card' => 'Gestores',
            'url' => '/estoque/configuracoes/responsaveis.php',
            'icone' => 'bi-people',
            'categoria' => 'estoque_config',
            'ordem' => 5
        ],
        [
            'codigo' => 'estoque_config_localizacoes',
            'nome' => 'Localizações Estoque',
            'descricao' => 'Gerenciar localizações e prateleiras',
            'descricao_card' => 'Prateleiras',
            'url' => '/estoque/configuracoes/localizacoes.php',
            'icone' => 'bi-geo-alt',
            'categoria' => 'estoque_config',
            'ordem' => 6
        ]
    ];
    
    $menuIds = [];
    $grupoId = 11; // ID do grupo "Estoque - Administrador"
    
    // Verificar se o grupo existe
    $stmt = $conn->prepare("SELECT id FROM grupos_acesso WHERE id = ?");
    $stmt->bind_param("i", $grupoId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Grupo 'Estoque - Administrador' (ID: $grupoId) não encontrado!");
    }
    $stmt->close();
    
    // Inserir ou atualizar menus
    $stmtInsert = $conn->prepare("
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
            requer_admin = 0,
            ativo = 1
    ");
    
    foreach ($menus as $menu) {
        $stmtInsert->bind_param("sssssssi", 
            $menu['codigo'],
            $menu['nome'],
            $menu['descricao'],
            $menu['descricao_card'],
            $menu['url'],
            $menu['icone'],
            $menu['categoria'],
            $menu['ordem']
        );
        
        if (!$stmtInsert->execute()) {
            throw new Exception("Erro ao inserir menu {$menu['codigo']}: " . $conn->error);
        }
        
        // Obter ID do menu (se já existia, busca; se foi inserido, pega insert_id)
        $menuId = $conn->insert_id;
        if ($menuId == 0) {
            $stmtGetId = $conn->prepare("SELECT id FROM menus WHERE codigo = ?");
            $stmtGetId->bind_param("s", $menu['codigo']);
            $stmtGetId->execute();
            $result = $stmtGetId->get_result();
            $row = $result->fetch_assoc();
            $menuId = $row['id'];
            $stmtGetId->close();
        }
        
        $menuIds[] = $menuId;
    }
    $stmtInsert->close();
    
    // Vincular menus ao grupo
    $stmtGrupo = $conn->prepare("INSERT IGNORE INTO grupo_menus (grupo_id, menu_id) VALUES (?, ?)");
    foreach ($menuIds as $menuId) {
        $stmtGrupo->bind_param("ii", $grupoId, $menuId);
        if (!$stmtGrupo->execute()) {
            throw new Exception("Erro ao vincular menu ID $menuId ao grupo: " . $conn->error);
        }
    }
    $stmtGrupo->close();
    
    // Limpar cache de permissões
    MenuPermissaoService::limparCache();
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Menus de configuração do estoque instalados com sucesso!',
        'menus_criados' => count($menuIds),
        'grupo_id' => $grupoId
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}

$conn->close();


