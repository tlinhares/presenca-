<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
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
    
    $data_hoje = $_GET['data'] ?? date('Y-m-d');
    
    // Buscar apenas usuários ativos com culto = 1
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.nome,
            u.email,
            u.foto_base64,
            COALESCE(pc.status, 'sem-presenca') as status_presenca,
            pc.horario_confirmacao,
            pc.tipo_confirmacao,
            j.id as justificativa_id,
            j.motivo,
            j.status as justificativa_status
        FROM usuarios u
        LEFT JOIN presencas_culto pc ON u.id = pc.id_usuario AND pc.data = ?
        LEFT JOIN justificativas_culto j ON u.id = j.id_usuario AND j.data_falta = ?
        WHERE u.ativo = 1 AND u.culto = 1
        ORDER BY u.nome
    ");
    $stmt->bind_param("ss", $data_hoje, $data_hoje);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $usuarios = [];
    $estatisticas = [
        'total_usuarios' => 0,
        'total_presentes' => 0,
        'total_atrasados' => 0,
        'total_faltas' => 0
    ];
    
    // Verificar se já existe alguma presença hoje
    $stmt_check = $conn->prepare("SELECT COUNT(*) as total FROM presencas_culto WHERE data = ?");
    $stmt_check->bind_param("s", $data_hoje);
    $stmt_check->execute();
    $check_result = $stmt_check->get_result();
    $check_row = $check_result->fetch_assoc();
    $tem_presencas = $check_row['total'] > 0;
    
    while ($usuario = $resultado->fetch_assoc()) {
        $estatisticas['total_usuarios']++;
        
        // Se tem justificativa para esta data específica, o status deve ser 'justificado'
        if ($usuario['justificativa_id']) {
            $usuario['status_presenca'] = 'justificado';
            $usuario['justificativa'] = [
                'id' => $usuario['justificativa_id'],
                'motivo' => $usuario['motivo'],
                'status' => $usuario['justificativa_status']
            ];
        } else {
            // Lógica de falta automática
            if ($tem_presencas && $usuario['status_presenca'] === 'sem-presenca') {
                $usuario['status_presenca'] = 'falta';
            }
            // Limpar dados de justificativa se não há justificativa para esta data
            $usuario['justificativa'] = null;
        }
        
        // Otimizar foto se existir
        if (!empty($usuario['foto_base64']) && $usuario['foto_base64'] !== 'null') {
            $usuario['foto_base64'] = otimizarFotoBase64($usuario['foto_base64']);
        } else {
            $usuario['foto_base64'] = null;
        }
        
        // Contar estatísticas
        switch ($usuario['status_presenca']) {
            case 'presente':
                $estatisticas['total_presentes']++;
                break;
            case 'atrasado':
                $estatisticas['total_atrasados']++;
                break;
            case 'falta':
                $estatisticas['total_faltas']++;
                break;
            case 'justificado':
                $estatisticas['total_faltas']++; // Contar como falta para estatísticas
                break;
        }
        
        // Remover campos duplicados
        unset($usuario['justificativa_id'], $usuario['motivo'], $usuario['justificativa_status']);
        
        $usuarios[] = $usuario;
    }
    
    echo json_encode([
        'status' => 'ok',
        'usuarios' => $usuarios,
        'estatisticas' => $estatisticas,
        'data' => $data_hoje
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao carregar usuários: ' . $e->getMessage()
    ]);
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
            
            // Definir tamanho máximo (100x100 para lista)
            $tamanho_maximo = 100;
            
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
