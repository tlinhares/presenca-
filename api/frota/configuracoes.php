<?php
/**
 * API para Gerenciar Configurações da Frota
 * GET  - Listar configurações
 * POST - Salvar configuração
 */
header('Content-Type: application/json; charset=UTF-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../conexao.php';

if (!MenuPermissaoService::podeAcessar('frota_configuracoes')) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso não autorizado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $sql = "SELECT id, chave, valor, descricao, atualizado_por, 
                       DATE_FORMAT(atualizado_em, '%d/%m/%Y %H:%i') as atualizado_em_fmt
                FROM frota_configuracoes 
                ORDER BY chave ASC";
        $result = $conn->query($sql);

        $configs = [];
        while ($row = $result->fetch_assoc()) {
            $configs[] = $row;
        }

        $valor_km = '0.00';
        foreach ($configs as $c) {
            if ($c['chave'] === 'valor_km') {
                $valor_km = $c['valor'];
                break;
            }
        }

        echo json_encode([
            'status' => 'ok',
            'configuracoes' => $configs,
            'valor_km' => floatval($valor_km)
        ]);

    } elseif ($method === 'POST') {
        $chave = $_POST['chave'] ?? '';
        $valor = $_POST['valor'] ?? '';

        if (empty($chave) || $valor === '') {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Chave e valor são obrigatórios']);
            exit;
        }

        $usuario_id = $_SESSION['usuario_id'] ?? 0;

        $sql = "INSERT INTO frota_configuracoes (chave, valor, atualizado_por) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_por = VALUES(atualizado_por)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $chave, $valor, $usuario_id);
        $stmt->execute();

        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Configuração salva com sucesso!'
        ]);

    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro: ' . $e->getMessage()
    ]);
}
?>
