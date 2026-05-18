<?php
// api/facial/sync.php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../conexao.php';

// Autenticação (implementar conforme documentação)
$api_key = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
// Verificar API key conforme documentação específica

// Receber dados do dispositivo
$dados = json_decode(file_get_contents('php://input'), true);

// Log para debug
file_put_contents(__DIR__ . '/../../logs/facial_sync_' . date('Y-m-d') . '.log', 
    date('Y-m-d H:i:s') . ' - ' . json_encode($dados) . PHP_EOL, 
    FILE_APPEND);

// Processar conforme o tipo de requisição
$acao = isset($_GET['acao']) ? $_GET['acao'] : '';

switch ($acao) {
    case 'check_in':
        // Processar check-in do usuário
        processarCheckIn($dados, $conn);
        break;
        
    case 'status':
        // Retornar status de sincronização
        retornarStatus($conn);
        break;
        
    case 'usuarios':
        // Listar usuários para sincronização
        listarUsuarios($conn);
        break;
        
    default:
        echo json_encode(array('status' => 'erro', 'mensagem' => 'Ação não reconhecida'));
}

function processarCheckIn($dados, $conn) {
    // Implementar lógica de check-in conforme documentação específica
    
    // Exemplo básico:
    $usuario_id = isset($dados['usuario_id']) ? $dados['usuario_id'] : 0;
    $dispositivo_id = isset($dados['dispositivo_id']) ? $dados['dispositivo_id'] : '';
    $timestamp = isset($dados['timestamp']) ? $dados['timestamp'] : date('Y-m-d H:i:s');
    
    // Verificar se o usuário existe
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows == 0) {
        echo json_encode(array('status' => 'erro', 'mensagem' => 'Usuário não encontrado'));
        exit;
    }
    
    // Registrar check-in
    $stmt = $conn->prepare("INSERT INTO checkin_facial (id_usuario, data_hora, dispositivo_id, tipo) 
                          VALUES (?, ?, ?, 'almoco')");
    $stmt->bind_param("iss", $usuario_id, $timestamp, $dispositivo_id);
    
    if ($stmt->execute()) {
        echo json_encode(array('status' => 'ok', 'mensagem' => 'Check-in registrado com sucesso'));
    } else {
        echo json_encode(array('status' => 'erro', 'mensagem' => 'Erro ao registrar check-in: ' . $conn->error));
    }
}

function retornarStatus($conn) {
    // Implementar conforme necessidade
    $data_hoje = date('Y-m-d');
    
    $stmt = $conn->prepare("SELECT 
        (SELECT COUNT(*) FROM facial_sync WHERE data = ? AND status = 'sincronizado') as sincronizados,
        (SELECT COUNT(*) FROM facial_sync WHERE data = ? AND status = 'pendente') as pendentes,
        (SELECT COUNT(*) FROM facial_sync WHERE data = ? AND status = 'falha') as falhas,
        (SELECT COUNT(*) FROM checkin_facial WHERE DATE(data_hora) = ?) as checkins
    ");
    $stmt->bind_param("ssss", $data_hoje, $data_hoje, $data_hoje, $data_hoje);
    $stmt->execute();
    $stmt->bind_result($sincronizados, $pendentes, $falhas, $checkins);
    $stmt->fetch();
    
    echo json_encode(array(
        'status' => 'ok',
        'data' => $data_hoje,
        'estatisticas' => array(
            'sincronizados' => $sincronizados,
            'pendentes' => $pendentes,
            'falhas' => $falhas,
            'checkins' => $checkins
        )
    ));
}

function listarUsuarios($conn) {
    // Implementar conforme documentação do dispositivo
    // Este é um exemplo genérico
    
    $data_hoje = date('Y-m-d');
    
    // Buscar usuários com reservas para hoje - versão PHP 5.3
    $query = "
        SELECT u.id, u.nome, u.foto_base64
        FROM usuarios u
        JOIN reservas_almoco ra ON u.id = ra.id_usuario
        WHERE ra.data = ? AND u.ativo = 1
        ORDER BY u.nome
    ";
    
    $usuarios = array();
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $data_hoje);
    $stmt->execute();
    
    // Alternativa para get_result() em PHP 5.3
    $stmt->bind_result($id, $nome, $foto_base64);
    while ($stmt->fetch()) {
        $usuarios[] = array(
            'id' => $id,
            'nome' => $nome,
            'foto' => $foto_base64
        );
    }
    $stmt->close();
    
    echo json_encode(array('status' => 'ok', 'usuarios' => $usuarios));
}
?>