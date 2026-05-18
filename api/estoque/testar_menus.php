<?php
/**
 * Script de teste para verificar se os menus estão sendo retornados
 */
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../api/conexao.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<h1>Teste de Menus de Configuração</h1>";
echo "<pre>";

$usuario_id = $_SESSION['usuario_id'] ?? null;
$usuario_nome = $_SESSION['usuario_nome'] ?? 'N/A';

echo "Usuário: $usuario_nome (ID: $usuario_id)\n";
echo "É Admin: " . (MenuPermissaoService::isAdmin() ? 'Sim' : 'Não') . "\n\n";

// Limpar cache
MenuPermissaoService::limparCache();

// Testar getMenusDoUsuario
echo "=== getMenusDoUsuario() ===\n";
$todosMenus = MenuPermissaoService::getMenusDoUsuario();
echo "Total de menus retornados: " . count($todosMenus) . "\n\n";

// Testar getMenusPorCategoria
echo "=== getMenusPorCategoria('estoque_config') ===\n";
$menusConfig = MenuPermissaoService::getMenusPorCategoria('estoque_config');
echo "Menus de estoque_config: " . count($menusConfig) . "\n";
foreach ($menusConfig as $menu) {
    echo "  - {$menu['codigo']}: {$menu['nome']}\n";
}
echo "\n";

// Verificar grupos do usuário
echo "=== Grupos do Usuário ===\n";
$grupos = MenuPermissaoService::getGruposDoUsuario();
echo "Total de grupos: " . count($grupos) . "\n";
foreach ($grupos as $grupo) {
    echo "  - {$grupo['nome']} (ID: {$grupo['id']})\n";
}
echo "\n";

// Verificar menus no banco
echo "=== Menus no Banco (estoque_config) ===\n";
$stmt = $conn->prepare("SELECT id, codigo, nome, ativo FROM menus WHERE categoria = 'estoque_config' ORDER BY ordem");
$stmt->execute();
$result = $stmt->get_result();
$menusBanco = [];
while ($row = $result->fetch_assoc()) {
    $menusBanco[] = $row;
    echo "  - {$row['codigo']}: {$row['nome']} (ID: {$row['id']}, Ativo: {$row['ativo']})\n";
}
$stmt->close();
echo "\n";

// Verificar vínculos grupo_menus
echo "=== Vínculos Grupo-Menus (Grupo ID: 11) ===\n";
$grupoId = 11;
$stmt = $conn->prepare("
    SELECT m.id, m.codigo, m.nome 
    FROM menus m
    INNER JOIN grupo_menus gm ON gm.menu_id = m.id
    WHERE gm.grupo_id = ? AND m.categoria = 'estoque_config'
    ORDER BY m.ordem
");
$stmt->bind_param("i", $grupoId);
$stmt->execute();
$result = $stmt->get_result();
$menusVinculados = [];
while ($row = $result->fetch_assoc()) {
    $menusVinculados[] = $row;
    echo "  - {$row['codigo']}: {$row['nome']} (ID: {$row['id']})\n";
}
$stmt->close();
echo "\n";

// Verificar se usuário está no grupo
echo "=== Usuário no Grupo (ID: 11) ===\n";
$stmt = $conn->prepare("SELECT grupo_id FROM usuario_grupos WHERE usuario_id = ? AND grupo_id = ?");
$stmt->bind_param("ii", $usuario_id, $grupoId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo "  ✓ Usuário está no grupo ID 11\n";
} else {
    echo "  ✗ Usuário NÃO está no grupo ID 11\n";
}
$stmt->close();
echo "\n";

// Testar podeAcessar para cada menu
echo "=== Teste podeAcessar() ===\n";
$codigos = ['estoque_config_departamentos', 'estoque_config_categorias', 'estoque_config_unidades', 
            'estoque_config_fornecedores', 'estoque_config_responsaveis', 'estoque_config_localizacoes'];
foreach ($codigos as $codigo) {
    $pode = MenuPermissaoService::podeAcessar($codigo);
    echo "  - $codigo: " . ($pode ? 'SIM' : 'NÃO') . "\n";
}

echo "\n=== FIM DO TESTE ===\n";
echo "</pre>";

$conn->close();



