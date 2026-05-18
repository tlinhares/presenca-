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
    
    $justificativa_id = intval($_GET['id'] ?? 0);
    
    if ($justificativa_id <= 0) {
        throw new Exception('ID da justificativa inválido');
    }
    
    $stmt = $conn->prepare("
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
        WHERE j.id = ?
    ");
    
    $stmt->bind_param("i", $justificativa_id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 0) {
        throw new Exception('Justificativa não encontrada');
    }
    
    $justificativa = $resultado->fetch_assoc();
    
    // Otimizar foto se existir
    if (!empty($justificativa['foto_base64']) && $justificativa['foto_base64'] !== 'null') {
        $justificativa['foto_base64'] = otimizarFotoBase64($justificativa['foto_base64']);
    } else {
        $justificativa['foto_base64'] = null;
    }
    
    echo json_encode([
        'status' => 'ok',
        'justificativa' => $justificativa
    ]);
    
} catch (Exception $e) {
    error_log("Erro em detalhes_justificativa.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao buscar detalhes: ' . $e->getMessage()]);
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
            
            // Definir tamanho máximo (100x100 para detalhes)
            $tamanho_maximo = 100;
            
            // Calcular novas dimensões mantendo proporção
            if ($largura_original > $altura_original) {
                $nova_largura = $tamanho_maximo;
                $nova_altura = ($altura_original * $tamanho_maximo) / $largura_original;
            } else {
                $nova_altura = $tamanho_maximo;
                $nova_largura = ($largura_original * $tamanho_maximo) / $largura_original;
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
            imagejpeg($nova_imagem, null, 85); // Qualidade 85%
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
