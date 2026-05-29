<?php
/**
 * POST /api/estoque/config_alertas/salvar.php
 * Body JSON: { ativo: 0|1, intervalo_horas: int>=1, telefone_fallback: string }
 * Salva a configuração de alertas de estoque.
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../../core/services/WhatsAppService.php';

MenuPermissaoService::exigirAcessoAPI('estoque_config_alertas');

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $ativo = !empty($input['ativo']) ? 1 : 0;
    $intervalo = (int) ($input['intervalo_horas'] ?? 24);
    if ($intervalo < 1)   $intervalo = 1;
    if ($intervalo > 8760) $intervalo = 8760; // teto: 1 ano

    $telefone_raw = trim($input['telefone_fallback'] ?? '');
    $telefone = '';
    if ($telefone_raw !== '') {
        $normalizado = WhatsAppService::normalizarTelefone($telefone_raw);
        if (empty($normalizado)) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Telefone de fallback inválido. Informe DDD + número.']);
            exit;
        }
        $telefone = $normalizado;
    }

    $usuario_id = (int) ($_SESSION['usuario_id'] ?? 0);

    $stmt = $conn->prepare(
        "INSERT INTO estoque_config_alertas (id, ativo, intervalo_horas, telefone_fallback, atualizado_por)
         VALUES (1, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            ativo = VALUES(ativo),
            intervalo_horas = VALUES(intervalo_horas),
            telefone_fallback = VALUES(telefone_fallback),
            atualizado_por = VALUES(atualizado_por)"
    );
    $tel_param = $telefone === '' ? null : $telefone;
    $stmt->bind_param('iisi', $ativo, $intervalo, $tel_param, $usuario_id);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Erro ao salvar configuração: ' . $conn->error);
    }
    $stmt->close();

    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Configuração de alertas salva com sucesso',
        'config' => [
            'ativo' => $ativo,
            'intervalo_horas' => $intervalo,
            'telefone_fallback' => $telefone,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Erro em config_alertas/salvar.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
