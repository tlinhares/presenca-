<?php
/**
 * API para Salvar/Atualizar Veículo
 */
ini_set('post_max_size', '10M');
ini_set('upload_max_filesize', '10M');

header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../conexao.php';

// Verificar permissão
if (!MenuPermissaoService::podeAcessar('frota_admin_veiculos')) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado']);
    exit;
}

$id = isset($_POST['id']) && !empty($_POST['id']) ? intval($_POST['id']) : null;
$placa = strtoupper(trim($_POST['placa'] ?? ''));
$modelo = trim($_POST['modelo'] ?? '');
$marca = trim($_POST['marca'] ?? '');
$ano = !empty($_POST['ano']) ? intval($_POST['ano']) : null;
$cor = trim($_POST['cor'] ?? '');
$km_atual = !empty($_POST['km_atual']) ? intval($_POST['km_atual']) : 0;
$status = $_POST['status'] ?? 'disponivel';
$observacoes = trim($_POST['observacoes'] ?? '');

// Validações
if (empty($placa)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Placa é obrigatória']);
    exit;
}
if (empty($modelo)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Modelo é obrigatório']);
    exit;
}
if (empty($marca)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Marca é obrigatória']);
    exit;
}

// Validar status
$statusValidos = ['disponivel', 'em_uso', 'manutencao', 'inativo'];
if (!in_array($status, $statusValidos)) {
    $status = 'disponivel';
}

$foto_veiculo_input = $_POST['foto_veiculo'] ?? '';

try {
    $foto_path = null;
    $atualizar_foto = false;

    if ($foto_veiculo_input === 'remover') {
        $atualizar_foto = true;
        $foto_path = null;
        if ($id) {
            $stmt_old = $conn->prepare("SELECT foto_veiculo FROM frota_veiculos WHERE id = ?");
            $stmt_old->bind_param("i", $id);
            $stmt_old->execute();
            $old = $stmt_old->get_result()->fetch_assoc();
            if ($old && $old['foto_veiculo']) {
                $old_file = __DIR__ . '/../../uploads/frota/' . $old['foto_veiculo'];
                if (file_exists($old_file)) @unlink($old_file);
            }
        }
    } elseif ($foto_veiculo_input && $foto_veiculo_input !== 'manter' && preg_match('/^data:image\/(\w+);base64,/', $foto_veiculo_input, $type)) {
        $atualizar_foto = true;
        $base64_data = substr($foto_veiculo_input, strpos($foto_veiculo_input, ',') + 1);
        $ext = strtolower($type[1]);
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $ext = 'jpg';

        $upload_dir = __DIR__ . '/../../uploads/frota/veiculos';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $decoded = base64_decode($base64_data);
        if ($decoded !== false) {
            if ($id) {
                $stmt_old = $conn->prepare("SELECT foto_veiculo FROM frota_veiculos WHERE id = ?");
                $stmt_old->bind_param("i", $id);
                $stmt_old->execute();
                $old = $stmt_old->get_result()->fetch_assoc();
                if ($old && $old['foto_veiculo']) {
                    $old_file = __DIR__ . '/../../uploads/frota/' . $old['foto_veiculo'];
                    if (file_exists($old_file)) @unlink($old_file);
                }
            }
            $filename = 'veiculo_' . uniqid() . '.' . $ext;
            file_put_contents($upload_dir . '/' . $filename, $decoded);
            $foto_path = 'veiculos/' . $filename;
        }
    }

    // Verificar se placa já existe (exceto para o próprio veículo em edição)
    $sql_check = "SELECT id FROM frota_veiculos WHERE placa = ?";
    if ($id) {
        $sql_check .= " AND id != ?";
    }
    
    $stmt_check = $conn->prepare($sql_check);
    if ($id) {
        $stmt_check->bind_param("si", $placa, $id);
    } else {
        $stmt_check->bind_param("s", $placa);
    }
    $stmt_check->execute();
    
    if ($stmt_check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe um veículo com esta placa']);
        exit;
    }
    
    if ($id) {
        if ($atualizar_foto) {
            $sql = "UPDATE frota_veiculos SET 
                    placa = ?, modelo = ?, marca = ?, ano = ?, cor = ?, 
                    km_atual = ?, status = ?, observacoes = ?, foto_veiculo = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssisssssi", $placa, $modelo, $marca, $ano, $cor, $km_atual, $status, $observacoes, $foto_path, $id);
        } else {
            $sql = "UPDATE frota_veiculos SET 
                    placa = ?, modelo = ?, marca = ?, ano = ?, cor = ?, 
                    km_atual = ?, status = ?, observacoes = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssissssi", $placa, $modelo, $marca, $ano, $cor, $km_atual, $status, $observacoes, $id);
        }
    } else {
        $sql = "INSERT INTO frota_veiculos (placa, modelo, marca, ano, cor, km_atual, status, observacoes, foto_veiculo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssisssss", $placa, $modelo, $marca, $ano, $cor, $km_atual, $status, $observacoes, $foto_path);
    }
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => $id ? 'Veículo atualizado com sucesso' : 'Veículo cadastrado com sucesso',
            'id' => $id ?: $conn->insert_id
        ]);
    } else {
        throw new Exception($stmt->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao salvar veículo: ' . $e->getMessage()
    ]);
}
?>



