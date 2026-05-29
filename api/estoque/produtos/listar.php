<?php
/**
 * API - Listar Produtos do Estoque
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    $departamento = isset($_GET['departamento']) ? intval($_GET['departamento']) : 0;
    $categoria = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
    $busca = trim($_GET['busca'] ?? '');
    $filtro = $_GET['filtro'] ?? '';
    // status: 'ativos' (padrão), 'inativos' ou 'todos'
    $status = $_GET['status'] ?? 'ativos';
    if (!in_array($status, ['ativos', 'inativos', 'todos'], true)) {
        $status = 'ativos';
    }
    $limite = isset($_GET['limite']) ? intval($_GET['limite']) : 100;
    $pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
    $offset = ($pagina - 1) * $limite;

    // Cláusula de status reutilizada na listagem e na contagem
    $whereStatus = $status === 'ativos'   ? ' AND p.ativo = 1'
                 : ($status === 'inativos' ? ' AND p.ativo = 0' : '');
    
    $sql = "SELECT 
                p.id,
                p.codigo,
                p.codigo_barras,
                p.nome,
                p.descricao,
                p.quantidade_atual,
                p.quantidade_minima,
                p.quantidade_ideal,
                p.quantidade_maxima,
                p.valor_unitario,
                p.valor_medio,
                p.marca,
                p.modelo,
                p.ncm,
                p.id_departamento,
                p.id_categoria,
                p.id_unidade,
                p.id_localizacao,
                p.ativo,
                p.criado_em,
                u.sigla as unidade,
                u.nome as unidade_nome,
                c.nome as categoria,
                c.cor as categoria_cor,
                d.nome as departamento,
                l.nome as localizacao
            FROM estoque_produtos p
            JOIN estoque_unidades u ON p.id_unidade = u.id
            JOIN estoque_departamentos d ON p.id_departamento = d.id
            LEFT JOIN estoque_categorias c ON p.id_categoria = c.id
            LEFT JOIN estoque_localizacoes l ON p.id_localizacao = l.id
            WHERE 1=1" . $whereStatus;

    $params = [];
    $types = "";
    
    if ($departamento > 0) {
        $sql .= " AND p.id_departamento = ?";
        $params[] = $departamento;
        $types .= "i";
    }
    
    if ($categoria > 0) {
        $sql .= " AND p.id_categoria = ?";
        $params[] = $categoria;
        $types .= "i";
    }
    
    if (!empty($busca)) {
        $sql .= " AND (p.nome LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?)";
        $busca_like = "%$busca%";
        $params[] = $busca_like;
        $params[] = $busca_like;
        $params[] = $busca_like;
        $types .= "sss";
    }
    
    if ($filtro === 'estoque_baixo') {
        $sql .= " AND p.quantidade_atual <= p.quantidade_minima";
    } elseif ($filtro === 'estoque_zerado') {
        $sql .= " AND p.quantidade_atual <= 0";
    }
    
    $sql .= " ORDER BY p.nome ASC LIMIT ? OFFSET ?";
    $params[] = $limite;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $produtos = [];
    while ($row = $result->fetch_assoc()) {
        $nivel_estoque = 'normal';
        if ($row['quantidade_atual'] <= 0) {
            $nivel_estoque = 'zerado';
        } elseif ($row['quantidade_atual'] <= $row['quantidade_minima']) {
            $nivel_estoque = 'critico';
        } elseif ($row['quantidade_atual'] < $row['quantidade_ideal']) {
            $nivel_estoque = 'baixo';
        }
        
        $produtos[] = [
            'id' => intval($row['id']),
            'codigo' => $row['codigo'],
            'codigo_barras' => $row['codigo_barras'],
            'nome' => $row['nome'],
            'descricao' => $row['descricao'],
            'quantidade_atual' => floatval($row['quantidade_atual']),
            'quantidade_minima' => floatval($row['quantidade_minima']),
            'quantidade_ideal' => floatval($row['quantidade_ideal']),
            'quantidade_maxima' => $row['quantidade_maxima'] ? floatval($row['quantidade_maxima']) : null,
            'valor_unitario' => floatval($row['valor_unitario']),
            'valor_medio' => floatval($row['valor_medio']),
            'marca' => $row['marca'],
            'modelo' => $row['modelo'],
            'ncm' => $row['ncm'],
            'id_departamento' => intval($row['id_departamento']),
            'id_categoria' => $row['id_categoria'] ? intval($row['id_categoria']) : null,
            'id_unidade' => intval($row['id_unidade']),
            'id_localizacao' => $row['id_localizacao'] ? intval($row['id_localizacao']) : null,
            'unidade' => $row['unidade'],
            'unidade_nome' => $row['unidade_nome'],
            'categoria' => $row['categoria'],
            'categoria_cor' => $row['categoria_cor'],
            'departamento' => $row['departamento'],
            'localizacao' => $row['localizacao'],
            'nivel_estoque' => $nivel_estoque,
            'ativo' => (bool)$row['ativo']
        ];
    }
    
    // Contar total (respeitando o mesmo status). Nota: 'p.' usado p/ casar com $whereStatus
    $sql_count = "SELECT COUNT(*) as total FROM estoque_produtos p WHERE 1=1" . $whereStatus;
    $result_count = $conn->query($sql_count);
    $total = $result_count->fetch_assoc()['total'];
    
    echo json_encode([
        'status' => 'ok',
        'produtos' => $produtos,
        'total' => intval($total),
        'pagina' => $pagina,
        'limite' => $limite
    ]);

} catch (Exception $e) {
    error_log("Erro em produtos/listar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();

