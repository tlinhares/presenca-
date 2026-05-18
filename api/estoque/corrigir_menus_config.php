<?php
/**
 * Script para corrigir e instalar menus de configuração do estoque
 * Verifica e corrige automaticamente problemas de permissão
 */
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

// Apenas admin pode executar
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../../api/conexao.php';

header('Content-Type: application/json; charset=UTF-8');

$resultado = [
    'status' => 'ok',
    'mensagens' => [],
    'correcoes' => []
];

try {
    $conn->begin_transaction();
    
    // 1. Verificar se os menus existem
    $menusEsperados = [
        'estoque_config_departamentos',
        'estoque_config_categorias',
        'estoque_config_unidades',
        'estoque_config_fornecedores',
        'estoque_config_responsaveis',
        'estoque_config_localizacoes'
    ];
    
    $menusExistentes = [];
    $stmt = $conn->prepare("SELECT id, codigo, nome, ativo FROM menus WHERE codigo IN (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", 
        $menusEsperados[0],
        $menusEsperados[1],
        $menusEsperados[2],
        $menusEsperados[3],
        $menusEsperados[4],
        $menusEsperados[5]
    );
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $menusExistentes[$row['codigo']] = $row;
    }
    $stmt->close();
    
    $resultado['mensagens'][] = "Menus existentes: " . count($menusExistentes) . " de " . count($menusEsperados);
    
    // 2. Criar menus que não existem
    $menusParaCriar = [
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
    
    // Verificar se precisa criar menus
    foreach ($menusParaCriar as $menu) {
        if (!isset($menusExistentes[$menu['codigo']])) {
            // Criar menu
            $stmtInsert = $conn->prepare("
                INSERT INTO menus (codigo, nome, descricao, descricao_card, url, icone, categoria, ordem, acesso_padrao, requer_admin, requer_culto, ativo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 1)
            ");
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
            
            if ($stmtInsert->execute()) {
                $menuId = $conn->insert_id;
                $menuIds[$menu['codigo']] = $menuId;
                $resultado['correcoes'][] = "Menu '{$menu['codigo']}' criado (ID: $menuId)";
            } else {
                throw new Exception("Erro ao criar menu {$menu['codigo']}: " . $conn->error);
            }
            $stmtInsert->close();
        } else {
            // Menu já existe, pegar ID
            $menuIds[$menu['codigo']] = $menusExistentes[$menu['codigo']]['id'];
            
            // Garantir que está ativo e não requer admin
            $menuAtual = $menusExistentes[$menu['codigo']];
            if (!$menuAtual['ativo'] || (isset($menuAtual['requer_admin']) && $menuAtual['requer_admin'] == 1)) {
                $stmtAtivar = $conn->prepare("UPDATE menus SET ativo = 1, requer_admin = 0 WHERE id = ?");
                $stmtAtivar->bind_param("i", $menuIds[$menu['codigo']]);
                $stmtAtivar->execute();
                $stmtAtivar->close();
                if (!$menuAtual['ativo']) {
                    $resultado['correcoes'][] = "Menu '{$menu['codigo']}' ativado";
                }
                if (isset($menuAtual['requer_admin']) && $menuAtual['requer_admin'] == 1) {
                    $resultado['correcoes'][] = "Menu '{$menu['codigo']}' configurado para não requerer admin";
                }
            }
        }
    }
    
    // 3. Verificar grupo "Estoque - Administrador"
    $grupoId = 11;
    $stmt = $conn->prepare("SELECT id, nome FROM grupos_acesso WHERE id = ?");
    $stmt->bind_param("i", $grupoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $grupo = $result->fetch_assoc();
    $stmt->close();
    
    if (!$grupo) {
        throw new Exception("Grupo 'Estoque - Administrador' (ID: $grupoId) não encontrado!");
    }
    
    $resultado['mensagens'][] = "Grupo encontrado: {$grupo['nome']} (ID: $grupoId)";
    
    // 4. Verificar e criar vínculos grupo_menus
    $stmtCheck = $conn->prepare("SELECT menu_id FROM grupo_menus WHERE grupo_id = ? AND menu_id = ?");
    $stmtInsert = $conn->prepare("INSERT INTO grupo_menus (grupo_id, menu_id) VALUES (?, ?)");
    
    foreach ($menuIds as $codigo => $menuId) {
        $stmtCheck->bind_param("ii", $grupoId, $menuId);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        
        if ($resultCheck->num_rows === 0) {
            // Não existe vínculo, criar
            $stmtInsert->bind_param("ii", $grupoId, $menuId);
            if ($stmtInsert->execute()) {
                $resultado['correcoes'][] = "Vínculo criado: grupo $grupoId <-> menu '{$codigo}' (ID: $menuId)";
            } else {
                throw new Exception("Erro ao criar vínculo para menu {$codigo}: " . $conn->error);
            }
        }
    }
    $stmtCheck->close();
    $stmtInsert->close();
    
    // 5. Verificar usuário "Usuário teste" (ID: 22236)
    $usuarioId = 22236;
    $stmt = $conn->prepare("SELECT id, nome FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();
    
    if ($usuario) {
        $resultado['mensagens'][] = "Usuário encontrado: {$usuario['nome']} (ID: $usuarioId)";
        
        // Verificar se está no grupo
        $stmt = $conn->prepare("SELECT grupo_id FROM usuario_grupos WHERE usuario_id = ? AND grupo_id = ?");
        $stmt->bind_param("ii", $usuarioId, $grupoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $estaNoGrupo = $result->num_rows > 0;
        $stmt->close();
        
        if (!$estaNoGrupo) {
            // Adicionar ao grupo
            $stmt = $conn->prepare("INSERT INTO usuario_grupos (usuario_id, grupo_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $usuarioId, $grupoId);
            if ($stmt->execute()) {
                $resultado['correcoes'][] = "Usuário '{$usuario['nome']}' adicionado ao grupo '{$grupo['nome']}'";
            } else {
                throw new Exception("Erro ao adicionar usuário ao grupo: " . $conn->error);
            }
            $stmt->close();
        } else {
            $resultado['mensagens'][] = "Usuário já está no grupo";
        }
    }
    
    // 6. Limpar cache
    MenuPermissaoService::limparCache();
    $resultado['correcoes'][] = "Cache de permissões limpo";
    
    $conn->commit();
    
    $resultado['status'] = 'sucesso';
    $resultado['mensagens'][] = "Correção concluída com sucesso!";
    
} catch (Exception $e) {
    $conn->rollback();
    $resultado['status'] = 'erro';
    $resultado['mensagens'][] = "Erro: " . $e->getMessage();
    http_response_code(500);
}

$conn->close();

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

