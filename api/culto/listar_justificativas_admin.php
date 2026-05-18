<?php
session_start();

// ╔════════════════════════════════════════════════════════════════╗
// ║  Acesso: Mesma permissão de culto_justificativas_admin        ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('culto_justificativas_admin');

header('Content-Type: application/json; charset=UTF-8');

try {
    require_once __DIR__ . '/../../api/conexao.php';
    
    if (!isset($conn) || !$conn) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }
    
    $conn->set_charset("utf8");
    
    // Parâmetros de filtro
    $status = $_GET['status'] ?? 'todos';
    $nome = $_GET['nome'] ?? '';
    $data_inicio = $_GET['data_inicio'] ?? '';
    $data_fim = $_GET['data_fim'] ?? '';
    
    // Construir query base
    $sql = "
        SELECT 
            j.id,
            j.id_usuario,
            j.data_falta,
            j.motivo,
            j.observacoes,
            j.status,
            j.id_admin_aprovador,
            j.data_aprovacao,
            j.observacoes_admin,
            j.data_cadastro,
            u.nome as nome_usuario,
            u.email as email_usuario,
            u.foto_base64,
            admin.nome as nome_admin
        FROM justificativas_culto j
        INNER JOIN usuarios u ON j.id_usuario = u.id
        LEFT JOIN usuarios admin ON j.id_admin_aprovador = admin.id
        WHERE 1=1
    ";
    
    $params = [];
    $types = '';
    
    // Filtro por status
    if ($status !== 'todos') {
        $sql .= " AND j.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    // Filtro por nome do funcionário (busca parcial, case-insensitive)
    if (!empty($nome)) {
        $sql .= " AND u.nome LIKE ?";
        $params[] = '%' . $nome . '%';
        $types .= 's';
    }
    
    // Filtro por data início
    if (!empty($data_inicio)) {
        $sql .= " AND j.data_falta >= ?";
        $params[] = $data_inicio;
        $types .= 's';
    }
    
    // Filtro por data fim
    if (!empty($data_fim)) {
        $sql .= " AND j.data_falta <= ?";
        $params[] = $data_fim;
        $types .= 's';
    }
    
    $sql .= " ORDER BY j.data_cadastro DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $justificativas = [];
    $estatisticas = [
        'pendentes' => 0,
        'aprovadas' => 0,
        'rejeitadas' => 0,
        'total' => 0
    ];
    
    while ($justificativa = $resultado->fetch_assoc()) {
        // Otimizar foto se existir
        if (!empty($justificativa['foto_base64']) && $justificativa['foto_base64'] !== 'null') {
            $justificativa['foto_base64'] = otimizarFotoBase64($justificativa['foto_base64']);
        } else {
            $justificativa['foto_base64'] = null;
        }
        
        // Contar estatísticas
        $estatisticas['total']++;
        switch ($justificativa['status']) {
            case 'pendente':
                $estatisticas['pendentes']++;
                break;
            case 'aprovada':
                $estatisticas['aprovadas']++;
                break;
            case 'rejeitada':
                $estatisticas['rejeitadas']++;
                break;
        }
        
        $justificativas[] = $justificativa;
    }
    
    echo json_encode([
        'status' => 'ok',
        'justificativas' => $justificativas,
        'estatisticas' => $estatisticas
    ]);
    
} catch (Exception $e) {
    error_log("Erro em listar_justificativas_admin.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao listar justificativas: ' . $e->getMessage()]);
}

// Função para otimizar foto base64
function otimizarFotoBase64($foto_base64) {
    try {
        // Decodificar o base64
        $imagem_data = base64_decode($foto_base64);
        
        // Criar imagem a partir dos dados
        $imagem = imagecreatefromstring($imagem_data);
        
        if ($imagem !== false) {
            // Obter dimensões originais
            $largura_original = imagesx($imagem);
            $altura_original = imagesy($imagem);
            
            // Definir tamanho máximo (50x50 para lista)
            $tamanho_maximo = 50;
            
            // Calcular novas dimensões mantendo proporção
            if ($largura_original > $altura_original) {
                $nova_largura = $tamanho_maximo;
                $nova_altura = ($altura_original * $tamanho_maximo) / $largura_original;
            } else {
                $nova_altura = $tamanho_maximo;
                $nova_largura = ($largura_original * $tamanho_maximo) / $altura_original;
            }
            
            // Criar nova imagem redimensionada
            $nova_imagem = imagecreatetruecolor($nova_largura, $nova_altura);
            
            // Preservar transparência para PNG
            imagealphablending($nova_imagem, false);
            imagesavealpha($nova_imagem, true);
            
            // Redimensionar
            imagecopyresampled(
                $nova_imagem, $imagem,
                0, 0, 0, 0,
                $nova_largura, $nova_altura,
                $largura_original, $altura_original
            );
            
            // Capturar como JPG (mais compacto)
            ob_start();
            imagejpeg($nova_imagem, null, 80); // Qualidade 80%
            $imagem_otimizada = ob_get_contents();
            ob_end_clean();
            
            // Converter para base64
            $foto_otimizada_base64 = 'data:image/jpeg;base64,' . base64_encode($imagem_otimizada);
            
            // Limpar memória
            imagedestroy($imagem);
            imagedestroy($nova_imagem);
            
            return $foto_otimizada_base64;
        }
    } catch (Exception $e) {
        // Se houver erro na otimização, retornar null
        return null;
    }
    
    return null;
}

$conn->close();
?>
