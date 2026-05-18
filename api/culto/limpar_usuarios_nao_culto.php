<?php
/**
 * API para limpar registros de usuários que não são mais do culto
 */
header('Content-Type: application/json; charset=UTF-8');
include_once(__DIR__ . '/../../api/conexao.php');

try {
    // Buscar usuários que não são mais do culto mas têm registros de sincronização
    $sql_usuarios_nao_culto = "SELECT DISTINCT fs.id_usuario, u.nome, u.email
                               FROM facial_sync_culto fs
                               JOIN usuarios u ON fs.id_usuario = u.id
                               WHERE u.culto = 0 OR u.ativo = 0";
    
    $result_usuarios = $conn->query($sql_usuarios_nao_culto);
    $usuarios_nao_culto = [];
    
    while ($row = $result_usuarios->fetch_assoc()) {
        $usuarios_nao_culto[] = $row;
    }
    
    if (empty($usuarios_nao_culto)) {
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => 'Nenhum usuário não-culto encontrado com registros de sincronização',
            'usuarios_removidos' => 0
        ]);
        exit;
    }
    
    // Remover registros de sincronização para usuários que não são mais do culto
    $ids_usuarios = array_column($usuarios_nao_culto, 'id_usuario');
    $placeholders = str_repeat('?,', count($ids_usuarios) - 1) . '?';
    
    $sql_remover = "DELETE FROM facial_sync_culto WHERE id_usuario IN ($placeholders)";
    $stmt_remover = $conn->prepare($sql_remover);
    $stmt_remover->bind_param(str_repeat('i', count($ids_usuarios)), ...$ids_usuarios);
    $stmt_remover->execute();
    $registros_removidos = $stmt_remover->affected_rows;
    $stmt_remover->close();
    
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Registros de usuários não-culto removidos com sucesso',
        'usuarios_removidos' => count($usuarios_nao_culto),
        'registros_removidos' => $registros_removidos,
        'usuarios' => $usuarios_nao_culto
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao limpar registros: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
