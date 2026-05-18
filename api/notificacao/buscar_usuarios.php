<?php
/**
 * API para buscar usuários para teste de notificação
 */
header('Content-Type: application/json; charset=UTF-8');
include_once __DIR__ . '/../conexao.php';

$response = ['status' => 'erro', 'mensagem' => '', 'usuarios' => []];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $busca = filter_input(INPUT_GET, 'busca', FILTER_SANITIZE_STRING) ?: '';
        $status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING) ?: '';
        $contato = filter_input(INPUT_GET, 'contato', FILTER_SANITIZE_STRING) ?: '';
        
        $data_hoje = date('Y-m-d');
        
        // Construir query base
        $sql = "
            SELECT 
                u.id, 
                u.nome, 
                u.email, 
                u.telefone,
                CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM reservas_almoco r 
                        WHERE r.id_usuario = u.id 
                        AND r.data = ?
                    ) THEN 1 
                    ELSE 0 
                END as tem_reserva
            FROM usuarios u
            WHERE u.ativo = 1
        ";
        
        $params = [$data_hoje];
        $types = 's';
        
        // Adicionar filtros
        if (!empty($busca)) {
            $sql .= " AND (u.nome LIKE ? OR u.email LIKE ?)";
            $params[] = "%$busca%";
            $params[] = "%$busca%";
            $types .= 'ss';
        }
        
        if ($status === 'sem_reserva') {
            $sql .= " AND NOT EXISTS (
                SELECT 1 FROM reservas_almoco r 
                WHERE r.id_usuario = u.id 
                AND r.data = ?
            )";
            $params[] = $data_hoje;
            $types .= 's';
        } elseif ($status === 'com_reserva') {
            $sql .= " AND EXISTS (
                SELECT 1 FROM reservas_almoco r 
                WHERE r.id_usuario = u.id 
                AND r.data = ?
            )";
            $params[] = $data_hoje;
            $types .= 's';
        }
        
        if ($contato === 'whatsapp') {
            $sql .= " AND u.telefone IS NOT NULL AND u.telefone != ''";
        } elseif ($contato === 'email') {
            $sql .= " AND u.email IS NOT NULL AND u.email != ''";
        }
        
        $sql .= " ORDER BY u.nome";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $usuarios = [];
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
        
        $stmt->close();
        
        $response['status'] = 'sucesso';
        $response['usuarios'] = $usuarios;
        
    } catch (Exception $e) {
        $response['mensagem'] = 'Erro ao buscar usuários: ' . $e->getMessage();
    }
} else {
    $response['mensagem'] = 'Método de requisição inválido';
}

echo json_encode($response);
?>

