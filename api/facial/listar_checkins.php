<?php
// api/facial/listar_checkins.php
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
    
    // Buscar check-ins da data especificada
    $sql = "
        SELECT 
            ch.id, 
            ch.id_usuario, 
            u.nome as nome_usuario, 
            ch.data_hora, 
            ch.dispositivo_id,
            ch.detalhes
        FROM 
            facial_checkin ch
        LEFT JOIN 
            usuarios u ON ch.id_usuario = u.id
        WHERE 
            DATE(ch.data_hora) = ?
        ORDER BY 
            ch.data_hora DESC
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $data);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $checkins = [];
    while ($row = $result->fetch_assoc()) {
        // Formatar a data
        if (!empty($row['data_hora'])) {
            $datetime = new DateTime($row['data_hora']);
            $row['data_hora'] = $datetime->format('d/m/Y H:i:s');
        }
        
        $checkins[] = $row;
    }
    
    // Verificar a existência da tabela facial_checkin
    $table_exists = true;
    $check_table = $conn->query("SHOW TABLES LIKE 'facial_checkin'");
    if ($check_table->num_rows == 0) {
        $table_exists = false;
        
        // Sugestão para criar a tabela se necessário
        $create_table_sql = "
            CREATE TABLE `facial_checkin` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `id_usuario` int(11) NOT NULL,
              `data_hora` datetime NOT NULL,
              `dispositivo_id` varchar(50) DEFAULT NULL,
              `detalhes` text,
              PRIMARY KEY (`id`),
              KEY `idx_usuario` (`id_usuario`),
              KEY `idx_data` (`data_hora`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
    }
    
    // Enviar resposta
    echo json_encode([
        'status' => 'ok',
        'data' => $data,
        'total' => count($checkins),
        'checkins' => $checkins,
        'tabela_existe' => $table_exists
    ]);
    
} catch (Exception $e) {
    // Em caso de erro, enviar resposta de erro
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar check-ins: ' . $e->getMessage()
    ]);
}
?>