<?php
/**
 * POST /api/painel/push_envios/agendar.php
 * Body JSON: {
 *   titulo, corpo, dados?: object,
 *   destinatarios_tipo: "usuario"|"varios"|"todos",
 *   ids?: number[],
 *   agendado_para: "YYYY-MM-DD HH:MM"   // horário local (America/Cuiaba)
 * }
 *
 * Agenda um disparo futuro. Um cron de 1 em 1 minuto processa disparos vencidos.
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAdmin();

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $titulo = trim((string) ($input['titulo'] ?? ''));
    $corpo  = trim((string) ($input['corpo']  ?? ''));
    $tipo   = (string) ($input['destinatarios_tipo'] ?? '');
    $ids    = is_array($input['ids'] ?? null) ? array_values(array_filter(array_map('intval', $input['ids']), fn($v) => $v > 0)) : [];
    $dados  = is_array($input['dados'] ?? null) ? $input['dados'] : [];
    $agendado_para_raw = trim((string) ($input['agendado_para'] ?? ''));

    if ($titulo === '' || $corpo === '')     throw new RuntimeException('Título e corpo são obrigatórios');
    if (!in_array($tipo, ['usuario', 'varios', 'todos'], true)) throw new RuntimeException('destinatarios_tipo inválido');
    if ($tipo !== 'todos' && empty($ids))   throw new RuntimeException('Selecione ao menos um usuário destinatário');
    if ($agendado_para_raw === '')          throw new RuntimeException('Data/hora do agendamento é obrigatória');

    // Aceita "YYYY-MM-DDTHH:MM" (input datetime-local) ou "YYYY-MM-DD HH:MM"
    $agendado_para_raw = str_replace('T', ' ', $agendado_para_raw);
    $dt = DateTime::createFromFormat('Y-m-d H:i', $agendado_para_raw)
       ?: DateTime::createFromFormat('Y-m-d H:i:s', $agendado_para_raw);
    if (!$dt) throw new RuntimeException('Formato de data/hora inválido — use YYYY-MM-DD HH:MM');

    // Precisa estar no futuro (com tolerância de 1 min pra evitar corrida)
    date_default_timezone_set('America/Cuiaba');
    if ($dt->getTimestamp() < (time() - 60)) {
        throw new RuntimeException('O horário agendado deve ser no futuro.');
    }

    // Limita horizonte a 90 dias
    if ($dt->getTimestamp() > time() + (90 * 86400)) {
        throw new RuntimeException('Horizonte máximo de agendamento: 90 dias.');
    }

    $usuario_id = (int) ($_SESSION['usuario_id'] ?? 0);
    $agendado_str = $dt->format('Y-m-d H:i:s');
    $dados_json = json_encode($dados, JSON_UNESCAPED_UNICODE);
    $ids_json   = ($tipo === 'todos') ? null : json_encode($ids);

    $stmt = $conn->prepare(
        "INSERT INTO notificacoes_push_agendadas
            (titulo, corpo, dados_json, destinatarios_tipo, destinatarios_ids, agendado_para, criado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('ssssssi', $titulo, $corpo, $dados_json, $tipo, $ids_json, $agendado_str, $usuario_id);
    if (!$stmt->execute()) {
        throw new RuntimeException('Erro ao gravar agendamento: ' . $conn->error);
    }
    $id_novo = $conn->insert_id;
    $stmt->close();

    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Disparo agendado para ' . $dt->format('d/m/Y \à\s H:i') . ' (será processado automaticamente).',
        'id' => $id_novo,
        'agendado_para' => $agendado_str,
    ]);
} catch (Throwable $e) {
    error_log('Erro em push_envios/agendar.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
