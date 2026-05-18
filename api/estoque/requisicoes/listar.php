<?php
/**
 * API - Listar Requisições de Estoque
 *
 * Regra de visibilidade:
 *  - Admin do sistema ou almoxarife (estoque_responsaveis): vê tudo, com filtros.
 *  - Demais usuários: visualizam APENAS as próprias requisições (server-side
 *    força id_solicitante = usuário logado, ignorando parâmetro 'minhas').
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../almoxarife_helper.php';

try {
    $usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
    $is_admin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
    $tem_visao_completa = eh_almoxarife($conn, $usuario_id, $is_admin);

    $status = $_GET['status'] ?? '';
    $limite = isset($_GET['limite']) ? intval($_GET['limite']) : 50;
    $departamento = isset($_GET['departamento']) ? intval($_GET['departamento']) : 0;
    $minhas = isset($_GET['minhas']) && $_GET['minhas'] === 'true';
    $busca = trim($_GET['busca'] ?? '');

    // Server-side: usuário sem visão completa SEMPRE vê só as próprias requisições
    // (evita bypass de cliente via URL).
    if (!$tem_visao_completa) {
        $minhas = true;
        $departamento = 0; // filtro de depto não faz sentido nesse modo
    }
    
    $sql = "SELECT 
                r.id,
                r.numero,
                r.status,
                r.prioridade,
                r.finalidade,
                r.motivo,
                r.data_solicitacao,
                r.data_necessidade,
                r.data_aprovacao,
                r.data_entrega,
                DATE_FORMAT(r.data_solicitacao, '%d/%m/%Y %H:%i') as data_formatada,
                s.nome as solicitante,
                a.nome as aprovador,
                e.nome as entregador,
                do.nome as departamento_origem,
                dd.nome as departamento_destino,
                (SELECT COUNT(*) FROM estoque_requisicoes_itens WHERE id_requisicao = r.id) as total_itens
            FROM estoque_requisicoes r
            JOIN usuarios s ON r.id_solicitante = s.id
            LEFT JOIN usuarios a ON r.id_aprovador = a.id
            LEFT JOIN usuarios e ON r.id_entregador = e.id
            LEFT JOIN estoque_departamentos do ON r.id_departamento_origem = do.id
            LEFT JOIN estoque_departamentos dd ON r.id_departamento_destino = dd.id
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Filtrar por status (se vazio, mostra todas exceto rascunho, a menos que seja admin ou minhas)
    if (!empty($status)) {
        $sql .= " AND r.status = ?";
        $params[] = $status;
        $types .= "s";
    } else {
        // Se não especificar status, mostrar todas exceto rascunho (a menos que seja minhas requisições)
        if (!$minhas) {
            $sql .= " AND r.status != 'rascunho'";
        }
    }
    
    if ($departamento > 0) {
        $sql .= " AND (r.id_departamento_destino = ? OR r.id_departamento_origem = ?)";
        $params[] = $departamento;
        $params[] = $departamento;
        $types .= "ii";
    }
    
    if ($minhas) {
        $sql .= " AND r.id_solicitante = ?";
        $params[] = $usuario_id;
        $types .= "i";
    }
    
    if (!empty($busca)) {
        $sql .= " AND (r.numero LIKE ? OR r.motivo LIKE ?)";
        $busca_like = "%$busca%";
        $params[] = $busca_like;
        $params[] = $busca_like;
        $types .= "ss";
    }
    
    $sql .= " ORDER BY 
                FIELD(r.prioridade, 'urgente', 'alta', 'normal', 'baixa'),
                r.data_solicitacao DESC
              LIMIT ?";
    $params[] = $limite;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta: ' . $conn->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $requisicoes = [];
    while ($row = $result->fetch_assoc()) {
        $requisicoes[] = [
            'id' => intval($row['id']),
            'numero' => $row['numero'] ? $row['numero'] : 'REQ-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT),
            'status' => $row['status'],
            'prioridade' => $row['prioridade'],
            'finalidade' => $row['finalidade'],
            'motivo' => $row['motivo'],
            'data_solicitacao' => $row['data_solicitacao'],
            'data_formatada' => $row['data_formatada'],
            'data_necessidade' => $row['data_necessidade'],
            'solicitante' => $row['solicitante'],
            'aprovador' => $row['aprovador'],
            'entregador' => $row['entregador'],
            'departamento_origem' => $row['departamento_origem'],
            'departamento_destino' => $row['departamento_destino'],
            'total_itens' => intval($row['total_itens'])
        ];
    }
    
    // Contar total com os mesmos filtros
    $sql_total = "SELECT COUNT(*) as total FROM estoque_requisicoes r WHERE 1=1";
    $params_total = [];
    $types_total = "";
    
    if (!empty($status)) {
        $sql_total .= " AND r.status = ?";
        $params_total[] = $status;
        $types_total .= "s";
    } else {
        if (!$minhas) {
            $sql_total .= " AND r.status != 'rascunho'";
        }
    }
    
    if ($departamento > 0) {
        $sql_total .= " AND (r.id_departamento_destino = ? OR r.id_departamento_origem = ?)";
        $params_total[] = $departamento;
        $params_total[] = $departamento;
        $types_total .= "ii";
    }
    
    if ($minhas) {
        $sql_total .= " AND r.id_solicitante = ?";
        $params_total[] = $usuario_id;
        $types_total .= "i";
    }
    
    if (!empty($busca)) {
        $sql_total .= " AND (r.numero LIKE ? OR r.motivo LIKE ?)";
        $busca_like = "%$busca%";
        $params_total[] = $busca_like;
        $params_total[] = $busca_like;
        $types_total .= "ss";
    }
    
    $stmt_total = $conn->prepare($sql_total);
    if ($stmt_total) {
        if (!empty($params_total)) {
            $stmt_total->bind_param($types_total, ...$params_total);
        }
        $stmt_total->execute();
        $result_total = $stmt_total->get_result();
        $total = $result_total->fetch_assoc()['total'];
    } else {
        $total = count($requisicoes);
    }
    
    echo json_encode([
        'status' => 'ok',
        'requisicoes' => $requisicoes,
        'total' => intval($total)
    ]);

} catch (Exception $e) {
    error_log("Erro em requisicoes/listar.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();

