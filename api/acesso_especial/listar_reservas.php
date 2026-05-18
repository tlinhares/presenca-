<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

include_once(__DIR__ . '/../../utils/acesso_especial.php');
include_once(__DIR__ . '/../conexao.php');

// Verificar se a conexão foi estabelecida
if (!isset($conn) || !$conn) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro de conexão com o banco de dados']);
    exit;
}

// Verificar se a sessão está ativa
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

// Verificar acesso especial
if (!pode_acessar_especial()) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado']);
    exit;
}

try {
    // Buscar todas as reservas próprias especiais
    $stmt = $conn->prepare("
        SELECT 
            ra.id,
            u.nome as usuario_nome,
            ra.data,
            'propria' as tipo,
            1 as quantidade,
            COALESCE(ra.valor_refeicao, 0) + COALESCE(ra.valor_marmitex, 0) as valor,
            COALESCE(ra.observacao_especial, 'Reserva especial') as observacao,
            ra.horario_confirmacao as criado_em
        FROM reservas_almoco ra
        JOIN usuarios u ON ra.id_usuario = u.id
        WHERE ra.observacao_especial IS NOT NULL 
        AND ra.observacao_especial != ''
        ORDER BY ra.horario_confirmacao DESC
        LIMIT 100
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $reservas = [];
    
    while ($row = $result->fetch_assoc()) {
        $reservas[] = $row;
    }
    $stmt->close();

    // Buscar todas as reservas adicionais especiais
    $stmt = $conn->prepare("
        SELECT 
            ra.id,
            u.nome as usuario_nome,
            ra.data,
            CASE 
                WHEN ra.tipo = 'presencial' THEN 'adicional'
                ELSE ra.tipo
            END as tipo,
            ra.quantidade,
            COALESCE(ra.valor_refeicao, 0) + COALESCE(ra.valor_marmitex, 0) as valor,
            COALESCE(ra.observacao_especial, 'Reserva especial') as observacao,
            ra.data_cadastro as criado_em,
            CASE 
                WHEN ra.id_dependente IS NOT NULL THEN d.nome
                ELSE NULL
            END as dependente_nome
        FROM reservas_adicionais ra
        JOIN usuarios u ON ra.id_usuario = u.id
        LEFT JOIN dependentes d ON ra.id_dependente = d.id
        WHERE ra.observacao_especial IS NOT NULL 
        AND ra.observacao_especial != ''
        ORDER BY ra.data_cadastro DESC
        LIMIT 100
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $reservas[] = $row;
    }
    $stmt->close();

    // Ordenar por data de criação (mais recentes primeiro)
    usort($reservas, function($a, $b) {
        return strtotime($b['criado_em']) - strtotime($a['criado_em']);
    });

    // Limitar a 50 resultados
    $reservas = array_slice($reservas, 0, 50);

    echo json_encode([
        'status' => 'sucesso',
        'dados' => $reservas,
        'total' => count($reservas)
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}
?>
