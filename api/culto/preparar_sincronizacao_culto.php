<?php
/**
 * API para preparar sincronização de usuários com culto=1
 * Este script deve ser executado periodicamente para manter usuários do culto sincronizados
 */
header('Content-Type: application/json; charset=UTF-8');
include_once(__DIR__ . '/../../api/conexao.php');

// Verificar se a tabela facial_sync_culto existe
$result = $conn->query("SHOW TABLES LIKE 'facial_sync_culto'");
if ($result->num_rows == 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Tabela facial_sync_culto não existe']);
    exit;
}

try {
    // Buscar usuários com culto=1 e foto cadastrada
    $sql = "SELECT u.id, u.nome, u.foto_base64 
            FROM usuarios u 
            WHERE u.culto = 1 
            AND u.ativo = 1 
            AND u.foto_base64 IS NOT NULL 
            AND u.foto_base64 != ''";
    
    $result = $conn->query($sql);
    $usuarios_culto = [];
    
    while ($row = $result->fetch_assoc()) {
        $usuarios_culto[] = $row;
    }
    
    if (empty($usuarios_culto)) {
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => 'Nenhum usuário com culto ativo encontrado',
            'usuarios_processados' => 0
        ]);
        exit;
    }
    
    // Buscar dispositivos faciais do tipo 'culto' ativos
    $sql_dispositivos = "SELECT id, nome, ip, porta, usuario, senha 
                         FROM dispositivos_faciais 
                         WHERE ativo = 1 AND tipo_dispositivo = 'culto'";
    
    $result_dispositivos = $conn->query($sql_dispositivos);
    $dispositivos_culto = [];
    
    while ($row = $result_dispositivos->fetch_assoc()) {
        $dispositivos_culto[] = $row;
    }
    
    if (empty($dispositivos_culto)) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Nenhum dispositivo facial do tipo culto ativo encontrado'
        ]);
        exit;
    }
    
    $data_atual = date('Y-m-d');
    $usuarios_processados = 0;
    $registros_criados = 0;
    
    // Para cada usuário do culto, criar registros de sincronização para cada dispositivo
    foreach ($usuarios_culto as $usuario) {
        foreach ($dispositivos_culto as $dispositivo) {
            // Verificar se já existe registro para este usuário e dispositivo hoje
            $sql_check = "SELECT id FROM facial_sync_culto 
                         WHERE id_usuario = ? AND id_dispositivo = ? AND data = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("iis", $usuario['id'], $dispositivo['id'], $data_atual);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows == 0) {
                // Criar registro de sincronização
                $sql_insert = "INSERT INTO facial_sync_culto 
                              (id_usuario, id_dispositivo, data, status, origem, tentativas, detalhes) 
                              VALUES (?, ?, ?, 'pendente', 'culto', 0, 'Sincronização automática para usuário do culto')";
                
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("iis", $usuario['id'], $dispositivo['id'], $data_atual);
                
                if ($stmt_insert->execute()) {
                    $registros_criados++;
                }
                $stmt_insert->close();
            }
        }
        $usuarios_processados++;
    }
    
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Sincronização preparada com sucesso',
        'usuarios_processados' => $usuarios_processados,
        'dispositivos_encontrados' => count($dispositivos_culto),
        'registros_criados' => $registros_criados,
        'data' => $data_atual
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao preparar sincronização: ' . $e->getMessage()
    ]);
}

$conn->close();
?>

