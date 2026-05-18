<?php
/**
 * API - Listar Categorias de Estoque
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $apenas_ativos = !isset($_GET['todos']) || $_GET['todos'] !== 'true';
    $hierarquico = isset($_GET['hierarquico']) && $_GET['hierarquico'] === 'true';
    
    $sql = "SELECT 
                c.id,
                c.nome,
                c.descricao,
                c.id_categoria_pai,
                c.cor,
                c.icone,
                c.ordem,
                c.ativo,
                c.criado_em,
                cp.nome as categoria_pai_nome,
                (SELECT COUNT(*) FROM estoque_produtos p WHERE p.id_categoria = c.id AND p.ativo = 1) as total_produtos
            FROM estoque_categorias c
            LEFT JOIN estoque_categorias cp ON c.id_categoria_pai = cp.id";
    
    if ($apenas_ativos) {
        $sql .= " WHERE c.ativo = 1";
    }
    
    $sql .= " ORDER BY c.ordem ASC, c.nome ASC";
    
    $result = $conn->query($sql);
    
    $categorias = [];
    while ($row = $result->fetch_assoc()) {
        $categorias[] = [
            'id' => intval($row['id']),
            'nome' => $row['nome'],
            'descricao' => $row['descricao'],
            'id_categoria_pai' => $row['id_categoria_pai'] ? intval($row['id_categoria_pai']) : null,
            'categoria_pai_nome' => $row['categoria_pai_nome'],
            'cor' => $row['cor'] ?: '#6c757d',
            'icone' => $row['icone'] ?: 'bi-tag',
            'ordem' => intval($row['ordem']),
            'ativo' => (bool)$row['ativo'],
            'total_produtos' => intval($row['total_produtos']),
            'criado_em' => $row['criado_em']
        ];
    }
    
    // Se hierárquico, organizar em árvore
    if ($hierarquico) {
        $categorias = organizarHierarquia($categorias);
    }
    
    echo json_encode([
        'status' => 'ok',
        'categorias' => $categorias,
        'total' => count($categorias)
    ]);

} catch (Exception $e) {
    error_log("Erro em categorias/listar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

function organizarHierarquia($categorias, $paiId = null) {
    $resultado = [];
    foreach ($categorias as $cat) {
        if ($cat['id_categoria_pai'] === $paiId) {
            $cat['subcategorias'] = organizarHierarquia($categorias, $cat['id']);
            $resultado[] = $cat;
        }
    }
    return $resultado;
}

$conn->close();



