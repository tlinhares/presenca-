<?php
/**
 * GET /api/dispositivos/listar_usuarios_antena.php?id_dispositivo=N
 *
 * Lista os usuários cadastrados no dispositivo facial em tempo real.
 * Usa IntelbrasDriver::listUsers() (mesmo motor do projeto `acesso`).
 *
 * NOTE: o nome "antena" é histórico — esse projeto só tem facial, então
 * "antena" aqui é só "dispositivo facial".
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('dispositivos_faciais');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../utils/intelbras_driver.php';

try {
    if (empty($_GET['id_dispositivo'])) {
        throw new Exception('ID do dispositivo não fornecido');
    }
    $id_dispositivo = (int) $_GET['id_dispositivo'];

    $stmt = $conn->prepare("SELECT id, nome, ip, porta, usuario, senha, modelo FROM dispositivos_faciais WHERE id = ?");
    $stmt->bind_param("i", $id_dispositivo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Dispositivo não encontrado');
    }
    $dispositivo = $result->fetch_assoc();
    $stmt->close();

    $driver = new IntelbrasDriver(
        (string) $dispositivo['ip'],
        (int) ($dispositivo['porta'] ?? 80),
        (string) ($dispositivo['usuario'] ?? ''),
        (string) ($dispositivo['senha'] ?? ''),
        (string) ($dispositivo['modelo'] ?? '')
    );

    $r = $driver->listUsers();

    if (empty($r['ok'])) {
        // Dá feedback útil pro admin — qual endpoint tentou, qual auth, qual HTTP.
        $detalhe = sprintf(
            'HTTP %d via %s [auth=%s]%s',
            (int) ($r['http'] ?? 0),
            (string) ($r['path'] ?? '—'),
            (string) ($r['auth'] ?? '—'),
            !empty($r['err']) ? ' — erro: ' . $r['err'] : ''
        );
        if ((int) ($r['http'] ?? 0) === 401) {
            throw new Exception('Falha de autenticação no dispositivo (HTTP 401). Confira usuário/senha. ' . $detalhe);
        }
        throw new Exception('Não foi possível obter a lista de usuários do dispositivo. ' . $detalhe);
    }

    $usuarios = $r['usuarios'] ?? [];

    echo json_encode([
        'status' => 'sucesso',
        'dispositivo' => [
            'id' => (int) $dispositivo['id'],
            'nome' => $dispositivo['nome'],
            'ip' => $dispositivo['ip'],
            'modelo' => $dispositivo['modelo'] ?? '',
        ],
        'usuarios' => $usuarios,
        'total' => count($usuarios),
        'total_dispositivo' => count($usuarios),
        // Diagnóstico para o admin (qual endpoint/auth funcionou)
        '_diag' => [
            'path' => $r['path'] ?? '',
            'mode' => $r['mode'] ?? '',
            'auth' => $r['auth'] ?? '',
            'http' => (int) ($r['http'] ?? 0),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro interno do servidor: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
