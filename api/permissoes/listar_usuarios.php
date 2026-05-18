<?php
/**
 * API: Listar usuários com suas permissões
 * 
 * GET /api/permissoes/listar_usuarios.php
 * Params: busca, categoria, ativo
 */

header('Content-Type: application/json; charset=UTF-8');
session_start();

// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../core/services/PermissaoService.php';

try {
    // Parâmetros de filtro
    $busca = $_GET['busca'] ?? '';
    $categoria = $_GET['categoria'] ?? '';
    $ativo = $_GET['ativo'] ?? '';
    
    // Construir query base
    $sql = "SELECT id, nome, email, categoria, ativo, 
            SUBSTRING(foto_base64, 1, 500) as foto_base64_preview
            FROM usuarios WHERE 1=1";
    $params = [];
    $types = "";
    
    // Filtro de busca
    if (!empty($busca)) {
        $sql .= " AND (nome LIKE ? OR email LIKE ?)";
        $busca_like = "%{$busca}%";
        $params[] = $busca_like;
        $params[] = $busca_like;
        $types .= "ss";
    }
    
    // Filtro de categoria
    if (!empty($categoria)) {
        $sql .= " AND categoria = ?";
        $params[] = $categoria;
        $types .= "s";
    }
    
    // Filtro de status
    if ($ativo !== '') {
        $sql .= " AND ativo = ?";
        $params[] = (int)$ativo;
        $types .= "i";
    }
    
    $sql .= " ORDER BY nome ASC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $usuarios = [];
    while ($row = $result->fetch_assoc()) {
        // Buscar permissões do usuário
        $permissoes = PermissaoService::getPermissoesDoUsuario($row['id']);
        
        // Converter para formato simples (codigo => nivel)
        $permissoes_simples = [];
        foreach ($permissoes as $codigo => $dados) {
            $permissoes_simples[$codigo] = $dados['nivel'];
        }
        
        // Verifica se tem foto (sem enviar a foto completa)
        $tem_foto = !empty($row['foto_base64_preview']);
        
        $usuarios[] = [
            'id' => $row['id'],
            'nome' => $row['nome'],
            'email' => $row['email'],
            'categoria' => $row['categoria'],
            'ativo' => $row['ativo'],
            'foto_base64' => $tem_foto ? $row['foto_base64_preview'] . '...' : null,
            'permissoes' => $permissoes_simples
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'usuarios' => $usuarios,
        'total' => count($usuarios)
    ]);

} catch (Exception $e) {
    error_log("API permissoes/listar_usuarios: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno']);
}

