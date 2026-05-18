<?php
/**
 * API: Listar todas as preferências de notificação dos usuários (Admin)
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../conexao.php';

try {
    // Parâmetros de filtro
    $busca = $_GET['busca'] ?? '';
    $ativo = $_GET['ativo'] ?? '';
    
    // Construir query base
    $sql = "SELECT 
                u.id,
                u.nome,
                u.email,
                u.ativo,
                COALESCE(nu.notificar_reserva_propria, 0) as notificar_reserva_propria,
                COALESCE(nu.notificar_reserva_adicional, 0) as notificar_reserva_adicional,
                COALESCE(nu.notificar_reserva_multipla, 0) as notificar_reserva_multipla,
                COALESCE(nu.notificar_reserva_cancelada, 0) as notificar_reserva_cancelada,
                COALESCE(nu.notificar_lembrete_diario, 1) as notificar_lembrete_diario,
                nu.criado_em,
                nu.atualizado_em
            FROM usuarios u
            LEFT JOIN notificacoes_usuario nu ON u.id = nu.id_usuario
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Filtro de busca
    if (!empty($busca)) {
        $sql .= " AND (u.nome LIKE ? OR u.email LIKE ?)";
        $busca_like = "%{$busca}%";
        $params[] = $busca_like;
        $params[] = $busca_like;
        $types .= "ss";
    }
    
    // Filtro de status
    if ($ativo !== '') {
        $sql .= " AND u.ativo = ?";
        $params[] = (int)$ativo;
        $types .= "i";
    }
    
    $sql .= " ORDER BY u.nome ASC LIMIT 500";
    
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $usuarios = [];
    while ($row = $result->fetch_assoc()) {
        $usuarios[] = [
            'id' => (int)$row['id'],
            'nome' => $row['nome'],
            'email' => $row['email'],
            'ativo' => (bool)$row['ativo'],
            'preferencias' => [
                'notificar_reserva_propria' => (bool)$row['notificar_reserva_propria'],
                'notificar_reserva_adicional' => (bool)$row['notificar_reserva_adicional'],
                'notificar_reserva_multipla' => (bool)$row['notificar_reserva_multipla'],
                'notificar_reserva_cancelada' => (bool)$row['notificar_reserva_cancelada'],
                'notificar_lembrete_diario' => (bool)$row['notificar_lembrete_diario']
            ],
            'configurado' => !is_null($row['criado_em']),
            'criado_em' => $row['criado_em'],
            'atualizado_em' => $row['atualizado_em']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'status' => 'sucesso',
        'dados' => $usuarios,
        'total' => count($usuarios)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao listar preferências: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
