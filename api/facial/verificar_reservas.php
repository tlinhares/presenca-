<?php
// api/facial/verificar_reservas.php
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);

// Incluir arquivos necessários
require_once __DIR__ . '/../../api/conexao.php';
require_once __DIR__ . '/../../auth/verifica_sessao.php';



// Data para verificação (padrão: hoje)
$data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');

// Criar pasta logs se não existir
$logs_dir = __DIR__ . '/../../logs';
if (!file_exists($logs_dir)) {
    mkdir($logs_dir, 0777, true);
}

// Arquivo de log
$log_file = $logs_dir . '/verificacao_reservas_' . date('Y-m-d') . '.log';
$time = date('Y-m-d H:i:s');
file_put_contents($log_file, "[$time] Verificação de reservas para a data: $data\n", FILE_APPEND);

// Função para verificar a existência das tabelas
function verificarTabela($conn, $tabela) {
    $resultado = $conn->query("SHOW TABLES LIKE '$tabela'");
    return $resultado->num_rows > 0;
}

// Verificar tabelas necessárias
$tabelas_existem = [
    'reservas_almoco' => verificarTabela($conn, 'reservas_almoco'),
    'facial_sync' => verificarTabela($conn, 'facial_sync')
];

if (!$tabelas_existem['reservas_almoco'] || !$tabelas_existem['facial_sync']) {
    $mensagem = "ERRO: Algumas tabelas necessárias não existem.";
    file_put_contents($log_file, "[$time] $mensagem\n", FILE_APPEND);
    
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $mensagem,
        'tabelas' => $tabelas_existem
    ]);
    exit;
}

// Passo 1: Verificar reservas existentes para a data
$sql_reservas = "SELECT ra.id, ra.id_usuario, u.nome 
                 FROM reservas_almoco ra 
                 JOIN usuarios u ON ra.id_usuario = u.id 
                 WHERE ra.data = ? AND u.ativo = 1
                 ORDER BY u.nome";

$stmt_reservas = $conn->prepare($sql_reservas);
$stmt_reservas->bind_param("s", $data);
$stmt_reservas->execute();
$resultado_reservas = $stmt_reservas->get_result();
$reservas = [];

while ($row = $resultado_reservas->fetch_assoc()) {
    $reservas[] = $row;
}

$total_reservas = count($reservas);
file_put_contents($log_file, "[$time] Total de reservas para a data $data: $total_reservas\n", FILE_APPEND);

// Passo 2: Verificar entradas na tabela facial_sync para a data
$sql_sync = "SELECT fs.id, fs.id_usuario, fs.status, fs.horario_sync, u.nome 
             FROM facial_sync fs 
             JOIN usuarios u ON fs.id_usuario = u.id 
             WHERE fs.data = ?
             ORDER BY u.nome";

$stmt_sync = $conn->prepare($sql_sync);
$stmt_sync->bind_param("s", $data);
$stmt_sync->execute();
$resultado_sync = $stmt_sync->get_result();
$sincronizacoes = [];

while ($row = $resultado_sync->fetch_assoc()) {
    $sincronizacoes[] = $row;
}

$total_sincronizacoes = count($sincronizacoes);
file_put_contents($log_file, "[$time] Total de registros na facial_sync para a data $data: $total_sincronizacoes\n", FILE_APPEND);

// Passo 3: Identificar usuários com reserva mas sem sincronização
$usuarios_sem_sync = [];
$ids_com_sync = array_column($sincronizacoes, 'id_usuario');

foreach ($reservas as $reserva) {
    if (!in_array($reserva['id_usuario'], $ids_com_sync)) {
        $usuarios_sem_sync[] = $reserva;
    }
}

$total_sem_sync = count($usuarios_sem_sync);
file_put_contents($log_file, "[$time] Total de usuários com reserva mas sem sincronização: $total_sem_sync\n", FILE_APPEND);

// Passo 4: Se necessário, criar registros na facial_sync para esses usuários
if ($total_sem_sync > 0 && isset($_GET['criar']) && $_GET['criar'] == 1) {
    file_put_contents($log_file, "[$time] Criando registros na facial_sync para usuários pendentes...\n", FILE_APPEND);
    
    $sql_inserir = "INSERT INTO facial_sync (id_usuario, data, status) VALUES (?, ?, 'pendente')";
    $stmt_inserir = $conn->prepare($sql_inserir);
    
    $inseridos = 0;
    foreach ($usuarios_sem_sync as $usuario) {
        $stmt_inserir->bind_param("is", $usuario['id_usuario'], $data);
        if ($stmt_inserir->execute()) {
            $inseridos++;
            file_put_contents($log_file, "[$time] Inserido registro para usuário: {$usuario['nome']} (ID: {$usuario['id_usuario']})\n", FILE_APPEND);
        } else {
            file_put_contents($log_file, "[$time] ERRO ao inserir registro para usuário: {$usuario['nome']} - " . $stmt_inserir->error . "\n", FILE_APPEND);
        }
    }
    
    file_put_contents($log_file, "[$time] Total de registros inseridos: $inseridos\n", FILE_APPEND);
}

// Preparar resposta
$resposta = [
    'status' => 'ok',
    'data' => $data,
    'total_reservas' => $total_reservas,
    'total_sincronizacoes' => $total_sincronizacoes,
    'usuarios_sem_sincronizacao' => $total_sem_sync,
    'tabelas_existem' => $tabelas_existem,
    'reservas' => $reservas,
    'sincronizacoes' => $sincronizacoes,
    'usuarios_sem_sync' => $usuarios_sem_sync,
    'mensagem' => "Verificação concluída. $total_reservas reservas encontradas, $total_sincronizacoes sincronizações existentes, $total_sem_sync usuários sem sincronização."
];

// Adicionar link para criar registros pendentes
if ($total_sem_sync > 0 && (!isset($_GET['criar']) || $_GET['criar'] != 1)) {
    $resposta['link_criar'] = "Para criar registros pendentes, acesse: " . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') ? '&' : '?') . "criar=1";
}

// Adicionar informação sobre scripts relacionados
$resposta['links_uteis'] = [
    'verificar_tabelas' => '/api/facial/verificar_tabelas.php',
    'dashboard' => '/api/facial/dashboard.php?data=' . $data,
    'listar_sincronizacoes' => '/api/facial/listar_sincronizacoes.php?data=' . $data
];

echo json_encode($resposta, JSON_PRETTY_PRINT);
?> 