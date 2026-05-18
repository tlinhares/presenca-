<?php
/**
 * Script de diagnóstico de permissões de estoque
 * Remove este arquivo após resolver o problema
 */
session_start();
require_once __DIR__ . '/../auth/verifica_sessao.php';
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';

$usuarioId = $_SESSION['usuario_id'] ?? 0;
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$isAdmin = MenuPermissaoService::isAdmin();

require_once __DIR__ . '/../api/conexao.php';

echo "<h1>Diagnóstico de Permissões - Estoque Config</h1>";
echo "<p><strong>Usuário:</strong> {$nomeUsuario} (ID: {$usuarioId})</p>";
echo "<p><strong>É Admin:</strong> " . ($isAdmin ? 'Sim' : 'Não') . "</p>";
echo "<hr>";

// Verificar grupos do usuário
$grupos = MenuPermissaoService::getGruposDoUsuario($usuarioId);
echo "<h2>Grupos do Usuário:</h2>";
if (empty($grupos)) {
    echo "<p style='color: red;'>Nenhum grupo encontrado!</p>";
} else {
    echo "<ul>";
    foreach ($grupos as $grupo) {
        echo "<li>{$grupo['nome']} (ID: {$grupo['id']})</li>";
    }
    echo "</ul>";
}

echo "<hr>";

// Verificar menus de estoque_config
$menusConfig = [
    'estoque_config_departamentos',
    'estoque_config_categorias',
    'estoque_config_unidades',
    'estoque_config_fornecedores',
    'estoque_config_responsaveis',
    'estoque_config_localizacoes'
];

echo "<h2>Verificação de Menus de Configuração:</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Código do Menu</th><th>Existe no BD?</th><th>Ativo?</th><th>Requer Admin?</th><th>Acesso Padrão?</th><th>Pode Acessar?</th><th>Via Grupo?</th></tr>";

foreach ($menusConfig as $codigo) {
    $menu = MenuPermissaoService::getMenu($codigo);
    $existe = $menu ? 'Sim' : 'Não';
    $ativo = $menu && $menu['ativo'] ? 'Sim' : 'Não';
    $requerAdmin = $menu && $menu['requer_admin'] ? 'Sim' : 'Não';
    $acessoPadrao = $menu && $menu['acesso_padrao'] ? 'Sim' : 'Não';
    $podeAcessar = MenuPermissaoService::podeAcessar($codigo) ? 'Sim' : 'Não';
    
    // Verificar acesso via grupo
    $viaGrupo = 'Não';
    if ($menu && !empty($grupos)) {
        $menuId = $menu['id'];
        $stmt = $conn->prepare("
            SELECT COUNT(*) as tem_acesso
            FROM usuario_grupos ug
            INNER JOIN grupo_menus gm ON gm.grupo_id = ug.grupo_id
            WHERE ug.usuario_id = ? AND gm.menu_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $usuarioId, $menuId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $viaGrupo = $row && $row['tem_acesso'] > 0 ? 'Sim' : 'Não';
            $stmt->close();
        }
    }
    
    $cor = $podeAcessar === 'Sim' ? 'green' : 'red';
    echo "<tr>";
    echo "<td><strong>{$codigo}</strong></td>";
    echo "<td>{$existe}</td>";
    echo "<td>{$ativo}</td>";
    echo "<td>{$requerAdmin}</td>";
    echo "<td>{$acessoPadrao}</td>";
    echo "<td style='color: {$cor};'><strong>{$podeAcessar}</strong></td>";
    echo "<td>{$viaGrupo}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";

// Verificar menus vinculados aos grupos do usuário
if (!empty($grupos)) {
    echo "<h2>Menus Vinculados aos Grupos do Usuário:</h2>";
    $grupoIds = array_column($grupos, 'id');
    $placeholders = str_repeat('?,', count($grupoIds) - 1) . '?';
    
    $stmt = $conn->prepare("
        SELECT m.codigo, m.nome, m.categoria, m.ativo, g.nome as grupo_nome
        FROM menus m
        INNER JOIN grupo_menus gm ON gm.menu_id = m.id
        INNER JOIN grupos_acesso g ON g.id = gm.grupo_id
        WHERE gm.grupo_id IN ({$placeholders})
        ORDER BY m.categoria, m.nome
    ");
    
    if ($stmt) {
        $types = str_repeat('i', count($grupoIds));
        $stmt->bind_param($types, ...$grupoIds);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $menusGrupo = [];
        while ($row = $result->fetch_assoc()) {
            $menusGrupo[] = $row;
        }
        $stmt->close();
        
        if (empty($menusGrupo)) {
            echo "<p style='color: red;'>Nenhum menu vinculado aos grupos do usuário!</p>";
        } else {
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>Código</th><th>Nome</th><th>Categoria</th><th>Ativo</th><th>Grupo</th></tr>";
            foreach ($menusGrupo as $menu) {
                $cor = $menu['categoria'] === 'estoque_config' ? 'blue' : 'black';
                echo "<tr>";
                echo "<td style='color: {$cor};'><strong>{$menu['codigo']}</strong></td>";
                echo "<td>{$menu['nome']}</td>";
                echo "<td>{$menu['categoria']}</td>";
                echo "<td>" . ($menu['ativo'] ? 'Sim' : 'Não') . "</td>";
                echo "<td>{$menu['grupo_nome']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
}

$conn->close();
?>




