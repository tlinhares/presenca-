<?php
/**
 * API para Salvar Manutenção
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../conexao.php';

if (!MenuPermissaoService::podeAcessar('frota_admin_manutencoes')) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado']);
    exit;
}

$id = isset($_POST['id']) && !empty($_POST['id']) ? intval($_POST['id']) : null;
$id_veiculo = isset($_POST['id_veiculo']) ? intval($_POST['id_veiculo']) : 0;
$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
$descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : '';
$data_manutencao = isset($_POST['data_manutencao']) ? $_POST['data_manutencao'] : '';
$km_manutencao = isset($_POST['km_manutencao']) && !empty($_POST['km_manutencao']) ? intval($_POST['km_manutencao']) : null;
$valor = isset($_POST['valor']) && !empty($_POST['valor']) ? floatval($_POST['valor']) : null;
$data_proxima_revisao = isset($_POST['data_proxima_revisao']) && !empty($_POST['data_proxima_revisao']) ? $_POST['data_proxima_revisao'] : null;
$km_proxima_revisao = isset($_POST['km_proxima_revisao']) && !empty($_POST['km_proxima_revisao']) ? intval($_POST['km_proxima_revisao']) : null;
$status = isset($_POST['status']) ? $_POST['status'] : 'pendente';
$usuario_id = $_SESSION['usuario_id'] ?? 0;

// Validações
if (!$id_veiculo) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Veículo não informado']);
    exit;
}
if (!$tipo) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Tipo não informado']);
    exit;
}
if (!$descricao) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Descrição não informada']);
    exit;
}
if (!$data_manutencao) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Data não informada']);
    exit;
}

try {
    if ($id) {
        // Atualizar
        $sql = "UPDATE frota_manutencoes SET 
                id_veiculo = ?, tipo = ?, descricao = ?, data_manutencao = ?,
                km_manutencao = ?, valor = ?, data_proxima_revisao = ?, 
                km_proxima_revisao = ?, status = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssissisi", 
            $id_veiculo, $tipo, $descricao, $data_manutencao,
            $km_manutencao, $valor, $data_proxima_revisao,
            $km_proxima_revisao, $status, $id
        );
    } else {
        // Inserir
        $sql = "INSERT INTO frota_manutencoes 
                (id_veiculo, id_usuario_registro, tipo, descricao, data_manutencao,
                 km_manutencao, valor, data_proxima_revisao, km_proxima_revisao, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssissis", 
            $id_veiculo, $usuario_id, $tipo, $descricao, $data_manutencao,
            $km_manutencao, $valor, $data_proxima_revisao, $km_proxima_revisao, $status
        );
    }
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'ok',
            'mensagem' => $id ? 'Manutenção atualizada com sucesso!' : 'Manutenção registrada com sucesso!',
            'id' => $id ?: $conn->insert_id
        ]);
    } else {
        throw new Exception($stmt->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao salvar manutenção: ' . $e->getMessage()
    ]);
}
?>



