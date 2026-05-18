<?php
// api/facial/listar_sincronizacoes.php
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Incluir arquivos necessários
require_once __DIR__ . '/../../api/conexao.php';
include_once __DIR__ . '/verificar_sessao_admin.php';

try {
    // Verificar acesso
    verificarAcessoSistema(basename(__FILE__));
    
    // Data para filtrar (padrão: hoje)
    $data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
    
    // Buscar sincronizações da data especificada
    $sql = "
        SELECT 
            fs.id, 
            fs.id_usuario, 
            u.nome as nome_usuario, 
            fs.status, 
            fs.horario_sync, 
            fs.detalhes
        FROM 
            facial_sync fs
        JOIN 
            usuarios u ON fs.id_usuario = u.id
        WHERE 
            fs.data = ?
        ORDER BY 
            fs.status = 'pendente' DESC, 
            fs.status = 'falha' DESC, 
            fs.horario_sync DESC
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $data);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sincronizacoes = [];
    while ($row = $result->fetch_assoc()) {
        // Formatar a data se existir
        if (!empty($row['horario_sync'])) {
            $datetime = new DateTime($row['horario_sync']);
            $row['horario_sync'] = $datetime->format('d/m/Y H:i:s');
        }
        
        $sincronizacoes[] = $row;
    }
    
    // Enviar resposta
    echo json_encode([
        'status' => 'ok',
        'data' => $data,
        'total' => count($sincronizacoes),
        'sincronizacoes' => $sincronizacoes
    ]);
    
} catch (Exception $e) {
    // Em caso de erro, enviar resposta de erro
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar sincronizações: ' . $e->getMessage()
    ]);
}
?>