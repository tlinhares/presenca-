<?php
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

// Verificar permissão de admin
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('gerenciar_notificacoes_enviadas');

require_once __DIR__ . '/../api/conexao.php';

// Filtros
$tipo_notificacao = $_GET['tipo_notificacao'] ?? '';
$status = $_GET['status'] ?? '';
$tipo_mensagem = $_GET['tipo_mensagem'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$pagina = intval($_GET['pagina'] ?? 1);
$por_pagina = 50;
$offset = ($pagina - 1) * $por_pagina;

// Construir query
$where = [];
$params = [];
$types = '';

if ($tipo_notificacao) {
    $where[] = "tipo_notificacao = ?";
    $params[] = $tipo_notificacao;
    $types .= 's';
}

if ($status) {
    $where[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($tipo_mensagem) {
    $where[] = "tipo_mensagem = ?";
    $params[] = $tipo_mensagem;
    $types .= 's';
}

$where[] = "DATE(data_envio) BETWEEN ? AND ?";
$params[] = $data_inicio;
$params[] = $data_fim;
$types .= 'ss';

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Contar total
$sql_count = "SELECT COUNT(*) as total FROM notificacoes_enviadas $where_sql";
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count) {
    if ($types && !empty($params)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $total_registros = $result_count ? $result_count->fetch_assoc()['total'] : 0;
    $stmt_count->close();
} else {
    $total_registros = 0;
    error_log("Erro ao preparar query COUNT: " . $conn->error);
}

$total_paginas = ceil($total_registros / $por_pagina);

// Buscar notificações
$sql = "SELECT n.*, u.nome as nome_usuario 
        FROM notificacoes_enviadas n 
        LEFT JOIN usuarios u ON u.id = n.usuario_id 
        $where_sql 
        ORDER BY n.data_envio DESC 
        LIMIT ? OFFSET ?";
$types .= 'ii';
$params[] = $por_pagina;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$notificacoes = [];
if ($stmt) {
    if ($types && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $notificacoes[] = $row;
        }
    }
    $stmt->close();
} else {
    error_log("Erro ao preparar query principal: " . $conn->error);
}

// Buscar tipos de mensagem únicos
$tipos_mensagem = [];
$result = $conn->query("SELECT DISTINCT tipo_mensagem FROM notificacoes_enviadas WHERE tipo_mensagem IS NOT NULL ORDER BY tipo_mensagem");
while ($row = $result->fetch_assoc()) {
    $tipos_mensagem[] = $row['tipo_mensagem'];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Notificações Enviadas - Sistema de Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; }
        .header-page {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }
        .badge-whatsapp { background-color: #25D366; }
        .badge-email { background-color: #007bff; }
        .table-responsive { max-height: 600px; overflow-y: auto; }
        .mensagem-cell { max-width: 300px; word-break: break-word; }
        .filtros-card { background: white; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-page">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="mb-1"><i class="bi bi-bell me-2"></i>Gerenciar Notificações Enviadas</h3>
                    <small class="opacity-75">Histórico completo de todas as notificações (WhatsApp e Email)</small>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 pb-5">
        <!-- Filtros -->
        <div class="card filtros-card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filtros</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" name="tipo_notificacao">
                                <option value="">Todos</option>
                                <option value="whatsapp" <?= $tipo_notificacao === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                                <option value="email" <?= $tipo_notificacao === 'email' ? 'selected' : '' ?>>Email</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">Todos</option>
                                <option value="sucesso" <?= $status === 'sucesso' ? 'selected' : '' ?>>Sucesso</option>
                                <option value="falha" <?= $status === 'falha' ? 'selected' : '' ?>>Falha</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tipo Mensagem</label>
                            <select class="form-select" name="tipo_mensagem">
                                <option value="">Todos</option>
                                <?php foreach ($tipos_mensagem as $tm): ?>
                                    <option value="<?= htmlspecialchars($tm) ?>" <?= $tipo_mensagem === $tm ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tm) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Início</label>
                            <input type="date" class="form-control" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Fim</label>
                            <input type="date" class="form-control" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-1"></i>Filtrar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-muted mb-0">Total</h5>
                        <h2 class="mb-0"><?= number_format($total_registros) ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-muted mb-0">WhatsApp</h5>
                        <h2 class="mb-0 text-success"><?php
                            $sql_whatsapp = "SELECT COUNT(*) as total FROM notificacoes_enviadas WHERE tipo_notificacao = 'whatsapp' $where_sql";
                            $stmt_whatsapp = $conn->prepare($sql_whatsapp);
                            if ($stmt_whatsapp) {
                                if ($types && !empty($params)) {
                                    $stmt_whatsapp->bind_param($types, ...$params);
                                }
                                $stmt_whatsapp->execute();
                                $result_whatsapp = $stmt_whatsapp->get_result();
                                $row_whatsapp = $result_whatsapp ? $result_whatsapp->fetch_assoc() : null;
                                echo number_format($row_whatsapp['total'] ?? 0);
                                $stmt_whatsapp->close();
                            } else {
                                echo '0';
                                error_log("Erro ao preparar query WhatsApp: " . $conn->error);
                            }
                        ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-muted mb-0">Email</h5>
                        <h2 class="mb-0 text-primary"><?php
                            $sql_email = "SELECT COUNT(*) as total FROM notificacoes_enviadas WHERE tipo_notificacao = 'email' $where_sql";
                            $stmt_email = $conn->prepare($sql_email);
                            if ($stmt_email) {
                                if ($types && !empty($params)) {
                                    $stmt_email->bind_param($types, ...$params);
                                }
                                $stmt_email->execute();
                                $result_email = $stmt_email->get_result();
                                $row_email = $result_email ? $result_email->fetch_assoc() : null;
                                echo number_format($row_email['total'] ?? 0);
                                $stmt_email->close();
                            } else {
                                echo '0';
                                error_log("Erro ao preparar query Email: " . $conn->error);
                            }
                        ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-muted mb-0">Taxa Sucesso</h5>
                        <h2 class="mb-0 text-info">
                            <?php
                            $sql_sucessos = "SELECT COUNT(*) as total FROM notificacoes_enviadas WHERE status = 'sucesso' $where_sql";
                            $stmt_sucessos = $conn->prepare($sql_sucessos);
                            if ($stmt_sucessos) {
                                if ($types && !empty($params)) {
                                    $stmt_sucessos->bind_param($types, ...$params);
                                }
                                $stmt_sucessos->execute();
                                $result_sucessos = $stmt_sucessos->get_result();
                                $row_sucessos = $result_sucessos ? $result_sucessos->fetch_assoc() : null;
                                $sucessos = $row_sucessos['total'] ?? 0;
                                $stmt_sucessos->close();
                            } else {
                                $sucessos = 0;
                                error_log("Erro ao preparar query Sucessos: " . $conn->error);
                            }
                            $taxa = $total_registros > 0 ? round(($sucessos / $total_registros) * 100, 1) : 0;
                            echo $taxa . '%';
                            ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Notificações (<?= number_format($total_registros) ?> registros)</h5>
                <small class="text-muted">Página <?= $pagina ?> de <?= $total_paginas ?></small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Data/Hora</th>
                                <th>Tipo</th>
                                <th>Destinatário</th>
                                <th>Nome</th>
                                <th>Tipo Mensagem</th>
                                <th>Status</th>
                                <th>Mensagem</th>
                                <th>Erro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($notificacoes)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                        Nenhuma notificação encontrada com os filtros aplicados
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($notificacoes as $notif): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i:s', strtotime($notif['data_envio'])) ?></td>
                                        <td>
                                            <?php if ($notif['tipo_notificacao'] === 'whatsapp'): ?>
                                                <span class="badge badge-whatsapp">WhatsApp</span>
                                            <?php else: ?>
                                                <span class="badge badge-email">Email</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?= htmlspecialchars($notif['destinatario']) ?></small></td>
                                        <td><?= htmlspecialchars($notif['nome_destinatario'] ?? $notif['nome_usuario'] ?? '-') ?></td>
                                        <td><small><?= htmlspecialchars($notif['tipo_mensagem'] ?? '-') ?></small></td>
                                        <td>
                                            <?php if ($notif['status'] === 'sucesso'): ?>
                                                <span class="badge bg-success">Sucesso</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Falha</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="mensagem-cell">
                                            <small><?= htmlspecialchars(substr($notif['mensagem'] ?? '', 0, 100)) ?><?= strlen($notif['mensagem'] ?? '') > 100 ? '...' : '' ?></small>
                                        </td>
                                        <td>
                                            <?php if ($notif['status'] === 'falha' && $notif['mensagem_erro']): ?>
                                                <small class="text-danger"><?= htmlspecialchars(substr($notif['mensagem_erro'], 0, 50)) ?><?= strlen($notif['mensagem_erro']) > 50 ? '...' : '' ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($total_paginas > 1): ?>
                <div class="card-footer">
                    <nav>
                        <ul class="pagination pagination-sm mb-0 justify-content-center">
                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

