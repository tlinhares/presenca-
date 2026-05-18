<?php
// api/presenca/listar_registros.php - Listar registros de presença
header('Content-Type: application/json; charset=UTF-8');

// Incluir arquivo de conexão
require_once __DIR__ . '/..../../conexao.php';

// Verificar se está autenticado via sessão
session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

// Verificar permissão
$is_admin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
if (!$is_admin) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso não autorizado']);
    exit;
}

// Data para filtrar (padrão: hoje)
$data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');

try {
    // Buscar registros
    $sql = "
        SELECT 
            fs.id,
            fs.id_usuario,
            u.nome as nome_usuario,
            fs.data,
            fs.horario_sync as hora,
            fs.status,
            CASE 
                WHEN fs.status = 'sincronizado' THEN 1
                ELSE 0
            END as sincronizado
        FROM facial_sync fs
        JOIN usuarios u ON fs.id_usuario = u.id
        WHERE fs.data = ?
        ORDER BY fs.horario_sync DESC, u.nome
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $data);
    $stmt->execute();
    
    $registros = array();
    
    // Método alternativo para PHP 5.5 sem mysqlnd
    $stmt->bind_result($id, $id_usuario, $nome_usuario, $data_reg, $hora, $status, $sincronizado);
    
    while ($stmt->fetch()) {
        $row = array(
            'id' => $id,
            'id_usuario' => $id_usuario,
            'nome_usuario' => $nome_usuario,
            'data' => $data_reg,
            'hora' => $hora,
            'status' => $status,
            'sincronizado' => $sincronizado
        );
        
        // Formatar dados
        $row['data_hora'] = date('d/m/Y', strtotime($row['data'])) . ' ' . substr($row['hora'], 0, 5);
        
        // Definir tipo por extenso
        if ($row['status'] == 'pendente') {
            $row['status_extenso'] = 'Pendente';
        } else if ($row['status'] == 'sincronizado') {
            $row['status_extenso'] = 'Sincronizado';
        } else if ($row['status'] == 'falha') {
            $row['status_extenso'] = 'Falha';
        } else {
            $row['status_extenso'] = ucfirst($row['status']);
        }
        
        $registros[] = $row;
    }
    
    $stmt->close();
    
    // Retornar resposta
    echo json_encode([
        'status' => 'ok',
        'data' => $data,
        'data_formatada' => date('d/m/Y', strtotime($data)),
        'registros' => $registros,
        'total' => count($registros)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar registros: ' . $e->getMessage()
    ]);
}
?> 