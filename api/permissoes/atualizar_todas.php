<?php
/**
 * API: Atualizar todas as permissões de um usuário de uma vez
 * 
 * POST /api/permissoes/atualizar_todas.php
 * Body: { usuario_id, permissoes: { modulo: nivel, ... } }
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
    $permissoes = $input['permissoes'] ?? [];
    
    // Validações
    if ($usuario_id <= 0) {
        throw new Exception('ID do usuário inválido');
    }
    
    if (!is_array($permissoes) || empty($permissoes)) {
        throw new Exception('Permissões não informadas');
    }
    
    // Verificar se o usuário existe e não é admin
    $stmt = $conn->prepare("SELECT id, categoria, nome FROM usuarios WHERE id = ?");
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
    
    // Atualizar cada permissão
    $erros = [];
    $atualizados = 0;
    
    foreach ($permissoes as $modulo => $nivel) {
        $nivel = (int)$nivel;
        
        if ($nivel < 0 || $nivel > 4) {
            $erros[] = "Nível inválido para módulo {$modulo}";
            continue;
        }
        
        $modulo_id = PermissaoService::getModuloId($modulo);
        if (!$modulo_id) {
            $erros[] = "Módulo não encontrado: {$modulo}";
            continue;
        }
        
        if (PermissaoService::setPermissao($usuario_id, $modulo, $nivel)) {
            $atualizados++;
        } else {
            $erros[] = "Erro ao atualizar módulo {$modulo}";
        }
    }
    
    // Log da alteração
    error_log(sprintf(
        "Permissões em lote: Admin %d alterou usuario_id=%d (%s), %d módulos atualizados",
        $_SESSION['usuario_id'],
        $usuario_id,
        $usuario['nome'],
        $atualizados
    ));
    
    if ($atualizados > 0) {
        $response = [
            'success' => true,
            'message' => "{$atualizados} permissões atualizadas com sucesso"
        ];
        
        if (!empty($erros)) {
            $response['avisos'] = $erros;
        }
        
        echo json_encode($response);
    } else {
        throw new Exception('Nenhuma permissão foi atualizada. ' . implode('; ', $erros));
    }

} catch (Exception $e) {
    error_log("API permissoes/atualizar_todas: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

