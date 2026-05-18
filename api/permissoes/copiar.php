<?php
/**
 * API: Copiar permissões de um usuário para outro
 * 
 * POST /api/permissoes/copiar.php
 * Body: { usuario_origem_id, usuario_destino_id }
 */

header('Content-Type: application/json; charset=UTF-8');
session_start();

// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../core/services/PermissaoService.php';

try {
    // Ler dados do body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados inválidos');
    }
    
    $origem_id = (int)($input['usuario_origem_id'] ?? 0);
    $destino_id = (int)($input['usuario_destino_id'] ?? 0);
    
    // Validações
    if ($origem_id <= 0 || $destino_id <= 0) {
        throw new Exception('IDs de usuário inválidos');
    }
    
    if ($origem_id === $destino_id) {
        throw new Exception('Origem e destino devem ser diferentes');
    }
    
    // Verificar se os usuários existem
    $stmt = $conn->prepare("SELECT id, categoria, nome FROM usuarios WHERE id IN (?, ?)");
    $stmt->bind_param("ii", $origem_id, $destino_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $usuarios = [];
    while ($row = $result->fetch_assoc()) {
        $usuarios[$row['id']] = $row;
    }
    $stmt->close();
    
    if (!isset($usuarios[$origem_id])) {
        throw new Exception('Usuário de origem não encontrado');
    }
    
    if (!isset($usuarios[$destino_id])) {
        throw new Exception('Usuário de destino não encontrado');
    }
    
    if ($usuarios[$destino_id]['categoria'] === 'admin') {
        throw new Exception('Não é possível alterar permissões de administradores');
    }
    
    // Obter permissões do usuário de origem
    $permissoes_origem = PermissaoService::getPermissoesDoUsuario($origem_id);
    
    if (empty($permissoes_origem)) {
        throw new Exception('Usuário de origem não possui permissões configuradas');
    }
    
    // Copiar permissões para o destino
    $atualizados = 0;
    foreach ($permissoes_origem as $codigo => $dados) {
        if (PermissaoService::setPermissao($destino_id, $codigo, $dados['nivel'])) {
            $atualizados++;
        }
    }
    
    // Log da alteração
    error_log(sprintf(
        "Permissões copiadas: Admin %d copiou de usuario_id=%d (%s) para usuario_id=%d (%s), %d módulos",
        $_SESSION['usuario_id'],
        $origem_id,
        $usuarios[$origem_id]['nome'],
        $destino_id,
        $usuarios[$destino_id]['nome'],
        $atualizados
    ));
    
    echo json_encode([
        'success' => true,
        'message' => "Permissões copiadas com sucesso ({$atualizados} módulos)"
    ]);

} catch (Exception $e) {
    error_log("API permissoes/copiar: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

