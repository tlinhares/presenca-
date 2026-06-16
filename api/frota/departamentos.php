<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

if (!isset($_SESSION['usuario_id'])) {
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
        exit;
    }
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $apenas_ativos = isset($_GET['apenas_ativos']) ? intval($_GET['apenas_ativos']) : 0;

        $sql = "SELECT id, nome, descricao, ativo,
                       DATE_FORMAT(criado_em, '%d/%m/%Y %H:%i') as criado_em_fmt,
                       DATE_FORMAT(atualizado_em, '%d/%m/%Y %H:%i') as atualizado_em_fmt
                FROM frota_departamentos";
        if ($apenas_ativos) {
            $sql .= " WHERE ativo = 1";
        }
        $sql .= " ORDER BY nome ASC";
        $result = $conn->query($sql);

        $departamentos = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = intval($row['id']);
            $row['ativo'] = intval($row['ativo']);
            $departamentos[] = $row;
        }

        echo json_encode(['status' => 'ok', 'departamentos' => $departamentos]);

    } elseif ($method === 'POST') {
        if (!MenuPermissaoService::podeAcessar('frota_departamentos')) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso não autorizado']);
            exit;
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? intval($_POST['ativo']) : 1;

        if (empty($nome)) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'O nome do departamento é obrigatório']);
            exit;
        }

        $check_sql = "SELECT id FROM frota_departamentos WHERE nome = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $nome, $id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe um departamento com este nome']);
            exit;
        }

        if ($id > 0) {
            $sql = "UPDATE frota_departamentos SET nome = ?, descricao = ?, ativo = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $nome, $descricao, $ativo, $id);
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'mensagem' => 'Departamento atualizado com sucesso!']);
        } else {
            $sql = "INSERT INTO frota_departamentos (nome, descricao, ativo) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $nome, $descricao, $ativo);
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'mensagem' => 'Departamento cadastrado com sucesso!', 'id' => $conn->insert_id]);
        }

    } elseif ($method === 'DELETE') {
        if (!MenuPermissaoService::podeAcessar('frota_departamentos')) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso não autorizado']);
            exit;
        }

        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'ID não informado']);
            exit;
        }

        $check = $conn->prepare("SELECT COUNT(*) as total FROM frota_utilizacoes WHERE id_departamento = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $uso = $check->get_result()->fetch_assoc();

        if ($uso['total'] > 0) {
            $stmt = $conn->prepare("UPDATE frota_departamentos SET ativo = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'mensagem' => 'Departamento desativado (possui utilizações vinculadas)']);
        } else {
            $stmt = $conn->prepare("DELETE FROM frota_departamentos WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'mensagem' => 'Departamento excluído com sucesso!']);
        }

    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro: ' . $e->getMessage()]);
}
?>
