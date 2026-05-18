<?php
/**
 * API: Atualizar permissão individual de um usuário
 * 
 * POST /api/permissoes/atualizar.php
 * Body: { usuario_id, modulo, nivel }
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
    
    $usuario_id = (int)($input['usuario_id'] ?? 0);
    $modulo = $input['modulo'] ?? '';
    $nivel = (int)($input['nivel'] ?? 0);
    
    // Validações
    if ($usuario_id <= 0) {
        throw new Exception('ID do usuário inválido');
    }
    
    if (empty($modulo)) {
        throw new Exception('Módulo não informado');
    }
    
    if ($nivel < 0 || $nivel > 4) {
        throw new Exception('Nível de permissão inválido (deve ser 0-4)');
    }
    
    // Verificar se o módulo existe
    $modulo_id = PermissaoService::getModuloId($modulo);
    if (!$modulo_id) {
        throw new Exception('Módulo não encontrado: ' . $modulo);
    }
    
    // Verificar se o usuário existe e não é admin
    $stmt = $conn->prepare("SELECT id, categoria FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();
    
    if (!$usuario) {
        throw new Exception('Usuário não encontrado');
    }
    
    if ($usuario['categoria'] === 'admin') {
        throw new Exception('Não é possível alterar permissões de administradores');
    }
    
    // Atualizar permissão
    $success = PermissaoService::setPermissao($usuario_id, $modulo, $nivel);
    
    if ($success) {
        // Log da alteração
        error_log(sprintf(
            "Permissão alterada: Admin %d alterou usuario_id=%d, modulo=%s, nivel=%d",
            $_SESSION['usuario_id'],
            $usuario_id,
            $modulo,
            $nivel
        ));
        
        echo json_encode([
            'success' => true,
            'message' => 'Permissão atualizada com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao atualizar permissão');
    }

} catch (Exception $e) {
    error_log("API permissoes/atualizar: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

