<?php
/**
 * GET /api/painel/push_envios/listar_agendamentos.php?status=<pendente|concluido|todos>
 * Lista agendamentos criados no sistema. Default: só pendentes (fila).
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAdmin();

try {
    $filtro = (string) ($_GET['status'] ?? 'pendente');
    $where = '';
    if ($filtro === 'pendente') {
        $where = "WHERE a.status IN ('pendente','processando')";
    } elseif ($filtro === 'concluido') {
        $where = "WHERE a.status IN ('concluido','falha','cancelado')";
    } elseif ($filtro !== 'todos') {
        $filtro = 'pendente';
        $where = "WHERE a.status IN ('pendente','processando')";
    }

    $sql = "SELECT a.id, a.titulo, a.corpo, a.destinatarios_tipo, a.destinatarios_ids,
                   a.agendado_para, a.status, a.criado_em, a.executado_em, a.resultado_json,
                   a.criado_por, u.nome AS criado_por_nome
              FROM notificacoes_push_agendadas a
              LEFT JOIN usuarios u ON u.id = a.criado_por
              $where
              ORDER BY (a.status = 'pendente') DESC, a.agendado_para ASC
              LIMIT 100";
    $r = $conn->query($sql);
    $itens = [];
    while ($x = $r->fetch_assoc()) {
        $ids   = $x['destinatarios_ids'] ? json_decode($x['destinatarios_ids'], true) : [];
        $resumo_dest = '';
        if ($x['destinatarios_tipo'] === 'todos') {
            $resumo_dest = 'Todos os usuários com dispositivo';
        } elseif ($x['destinatarios_tipo'] === 'usuario') {
            $resumo_dest = '1 usuário';
        } else {
            $resumo_dest = count($ids) . ' usuários';
        }
        $itens[] = [
            'id'                 => (int) $x['id'],
            'titulo'             => $x['titulo'],
            'corpo'              => mb_substr((string) $x['corpo'], 0, 200),
            'destinatarios_tipo' => $x['destinatarios_tipo'],
            'destinatarios_ids'  => $ids,
            'destinatarios_resumo' => $resumo_dest,
            'agendado_para'      => $x['agendado_para'],
            'status'             => $x['status'],
            'criado_em'          => $x['criado_em'],
            'criado_por'         => (int) $x['criado_por'],
            'criado_por_nome'    => $x['criado_por_nome'],
            'executado_em'       => $x['executado_em'],
            'resultado'          => $x['resultado_json'] ? json_decode($x['resultado_json'], true) : null,
        ];
    }

    echo json_encode(['status' => 'ok', 'agendamentos' => $itens]);
} catch (Throwable $e) {
    error_log('Erro em push_envios/listar_agendamentos.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
