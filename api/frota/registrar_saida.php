<?php
/**
 * API para Registrar Saída de Veículo
 */
// Aumentar limites para fotos base64
ini_set('post_max_size', '50M');
ini_set('upload_max_filesize', '50M');
ini_set('memory_limit', '256M');

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

require_once __DIR__ . '/../conexao.php';

// Inicia sessão ANTES do middleware (compatível com web)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Middleware mobile: converte Bearer Token em sessão PHP se necessário
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

// Verifica autenticação (web ou mobile)
if (!isset($_SESSION['usuario_id'])) {
    // Tenta autenticar via token mobile
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Usuário não autenticado. Token inválido ou ausente.'
        ]);
        exit;
    }
}

$usuario_id = $_SESSION['usuario_id'];

// Receber dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos']);
    exit;
}

$id_veiculo = intval($input['id_veiculo'] ?? 0);
$id_entidade = intval($input['id_entidade'] ?? 0);
$id_departamento = intval($input['id_departamento'] ?? 0);
$km_saida = intval($input['km_saida'] ?? 0);
$destino = trim($input['destino'] ?? '');
$motivo = trim($input['motivo'] ?? '');
$observacoes_saida = trim($input['observacoes_saida'] ?? '');
$foto_selfie = $input['foto_selfie'] ?? '';
$foto_km = $input['foto_km'] ?? '';
$foto_veiculo1 = $input['foto_veiculo1'] ?? '';
$foto_veiculo2 = $input['foto_veiculo2'] ?? '';
$foto_veiculo3 = $input['foto_veiculo3'] ?? '';
$checklist = $input['checklist'] ?? [];

// Debug: verificar se fotos chegaram
error_log("[FROTA] registrar_saida: foto_selfie recebida: " . (empty($foto_selfie) ? "NÃO" : "SIM, tamanho " . strlen($foto_selfie)));
error_log("[FROTA] registrar_saida: foto_km recebida: " . (empty($foto_km) ? "NÃO" : "SIM, tamanho " . strlen($foto_km)));

// Validações
if (!$id_veiculo) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Veículo não informado']);
    exit;
}
if (!$id_entidade) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Entidade não informada']);
    exit;
}
if (!$id_departamento) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Departamento não informado']);
    exit;
}
if (!$km_saida) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'KM de saída não informado']);
    exit;
}
if (!$destino) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Destino não informado']);
    exit;
}
if (!$motivo) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Motivo não informado']);
    exit;
}
if (!$foto_selfie || !$foto_km) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Fotos obrigatórias não enviadas']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Verificar se veículo está disponível
    $sql_check = "SELECT status, km_atual FROM frota_veiculos WHERE id = ? FOR UPDATE";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id_veiculo);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        throw new Exception('Veículo não encontrado');
    }
    
    $veiculo = $result_check->fetch_assoc();
    if ($veiculo['status'] !== 'disponivel') {
        throw new Exception('Veículo não está disponível');
    }
    
    // Verificar se usuário já tem veículo em uso
    $sql_uso = "SELECT id FROM frota_utilizacoes WHERE id_usuario = ? AND status = 'em_andamento'";
    $stmt_uso = $conn->prepare($sql_uso);
    $stmt_uso->bind_param("s", $usuario_id);
    $stmt_uso->execute();
    if ($stmt_uso->get_result()->num_rows > 0) {
        throw new Exception('Você já possui um veículo em uso');
    }
    
    // Criar pasta de uploads se não existir
    $upload_dir = __DIR__ . '/../../uploads/frota/' . date('Y/m');
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Função para salvar imagem base64
    function salvarImagem($base64, $prefix, $upload_dir) {
        if (empty($base64)) {
            error_log("[FROTA] salvarImagem: base64 vazio para $prefix");
            return null;
        }
        
        error_log("[FROTA] salvarImagem: recebido para $prefix, tamanho: " . strlen($base64));
        
        // Extrair dados da imagem
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
            $base64_data = substr($base64, strpos($base64, ',') + 1);
            $type = strtolower($type[1]);
            
            if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $type = 'jpg';
            }
            
            $decoded = base64_decode($base64_data);
            if ($decoded === false) {
                error_log("[FROTA] salvarImagem: falha ao decodificar base64 para $prefix");
                return null;
            }
            
            $filename = $prefix . '_' . uniqid() . '.' . $type;
            $filepath = $upload_dir . '/' . $filename;
            
            error_log("[FROTA] salvarImagem: tentando salvar em $filepath");
            
            if (file_put_contents($filepath, $decoded)) {
                error_log("[FROTA] salvarImagem: sucesso salvando $filename");
                return date('Y/m') . '/' . $filename;
            } else {
                error_log("[FROTA] salvarImagem: erro ao salvar arquivo em $filepath");
            }
        } else {
            error_log("[FROTA] salvarImagem: regex não encontrou formato esperado para $prefix, inicio: " . substr($base64, 0, 50));
        }
        return null;
    }
    
    // Salvar fotos
    $foto_selfie_path = salvarImagem($foto_selfie, 'selfie_saida', $upload_dir);
    $foto_km_path = salvarImagem($foto_km, 'km_saida', $upload_dir);
    $foto_veiculo1_path = salvarImagem($foto_veiculo1, 'veiculo_saida1', $upload_dir);
    $foto_veiculo2_path = salvarImagem($foto_veiculo2, 'veiculo_saida2', $upload_dir);
    $foto_veiculo3_path = salvarImagem($foto_veiculo3, 'veiculo_saida3', $upload_dir);
    
    // Inserir utilização
    $data_saida = date('Y-m-d H:i:s');
    
    $sql_insert = "INSERT INTO frota_utilizacoes 
                   (id_veiculo, id_usuario, id_entidade, id_departamento, data_saida, km_saida, destino, motivo, 
                    foto_km_saida, foto_selfie_saida, foto_veiculo_saida_1, foto_veiculo_saida_2, foto_veiculo_saida_3,
                    observacoes_saida, status)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'em_andamento')";
    
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("iiiisissssssss",
        $id_veiculo,
        $usuario_id,
        $id_entidade,
        $id_departamento,
        $data_saida,
        $km_saida,
        $destino,
        $motivo,
        $foto_km_path,
        $foto_selfie_path,
        $foto_veiculo1_path,
        $foto_veiculo2_path,
        $foto_veiculo3_path,
        $observacoes_saida
    );
    
    if (!$stmt_insert->execute()) {
        throw new Exception('Erro ao registrar utilização: ' . $stmt_insert->error);
    }
    
    $utilizacao_id = $conn->insert_id;
    
    // Inserir checklist
    if (!empty($checklist)) {
        $sql_checklist = "INSERT INTO frota_checklist 
                          (id_utilizacao, tipo, pneus_ok, farois_ok, documentos_ok, limpeza_ok, nivel_combustivel, avarias_encontradas)
                          VALUES (?, 'saida', ?, ?, ?, ?, ?, ?)";
        
        $stmt_checklist = $conn->prepare($sql_checklist);
        $pneus = isset($checklist['pneus_ok']) ? intval($checklist['pneus_ok']) : null;
        $farois = isset($checklist['farois_ok']) ? intval($checklist['farois_ok']) : null;
        $documentos = isset($checklist['documentos_ok']) ? intval($checklist['documentos_ok']) : null;
        $limpeza = isset($checklist['limpeza_ok']) ? intval($checklist['limpeza_ok']) : null;
        $nivel = $checklist['nivel_combustivel'] ?? null;
        $avarias = $checklist['avarias_encontradas'] ?? null;
        
        $stmt_checklist->bind_param("iiiiiss",
            $utilizacao_id,
            $pneus,
            $farois,
            $documentos,
            $limpeza,
            $nivel,
            $avarias
        );
        $stmt_checklist->execute();
    }
    
    // Atualizar status do veículo
    $sql_update = "UPDATE frota_veiculos SET status = 'em_uso', km_atual = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ii", $km_saida, $id_veiculo);
    $stmt_update->execute();
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Veículo retirado com sucesso',
        'utilizacao_id' => $utilizacao_id
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
?>


