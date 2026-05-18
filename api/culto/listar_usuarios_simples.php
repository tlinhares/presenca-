<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

// ╔════════════════════════════════════════════════════════════════╗
// ║  Acesso: Mesma permissão de culto_presencas                   ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('culto_presencas');

try {
    require_once '../../api/conexao.php';
    
    if (!isset($conn) || !$conn) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }
    
    $conn->set_charset("utf8");
    
    // Buscar apenas usuários ativos com culto = 1 (versão simplificada para selects)
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.nome
        FROM usuarios u
        WHERE u.ativo = 1 AND u.culto = 1
        ORDER BY u.nome
    ");
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $usuarios = [];
    while ($usuario = $resultado->fetch_assoc()) {
        $usuarios[] = [
            'id' => $usuario['id'],
            'nome' => $usuario['nome']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'status' => 'ok',
        'usuarios' => $usuarios
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao carregar usuários: ' . $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>

