<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();

// Verificação de login
require_once __DIR__ . '/auth/verifica_sessao.php';

// Verificação de permissão de menu
require_once __DIR__ . '/core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('visualiza_leituras');

require_once __DIR__ . '/api/conexao.php';
require_once __DIR__ . '/config/timezone.php';

// Parâmetros de busca
$data_filtro = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

// Consulta SQL
$where_conditions = [];
$params = [];
$types = '';

// Sempre filtrar por data (dia atual por padrão)
$where_conditions[] = "DATE(lf.createtime) = ?";
$params[] = $data_filtro;
$types .= 's';

// Busca adicional (por nome do cartão, IP ou userid)
if (!empty($busca)) {
    $where_conditions[] = "(lf.cardname LIKE ? OR lf.remote_ip LIKE ? OR lf.userid = ?)";
    $busca_param = "%{$busca}%";
    $params[] = $busca_param;
    $params[] = $busca_param;
    $params[] = is_numeric($busca) ? (int)$busca : 0;
    $types .= 'ssi';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Contar total de registros
$sql_count = "SELECT COUNT(*) as total FROM leitura_faciais lf $where_clause";
$stmt_count = $conn->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_registros = $result_count->fetch_assoc()['total'];
$stmt_count->close();

// Buscar leituras com JOIN condicional: se userid começa com 222, busca em usuarios, senão em dependentes
$sql = "SELECT 
    lf.id,
    lf.remote_ip,
    lf.createtime,
    lf.userid,
    lf.cardname,
    lf.foto_base64,
    CASE 
        WHEN CAST(lf.userid AS CHAR) LIKE '222%' THEN u.nome
        ELSE d.nome
    END as usuario_nome,
    CASE 
        WHEN CAST(lf.userid AS CHAR) LIKE '222%' THEN u.email
        ELSE NULL
    END as usuario_email,
    CASE 
        WHEN CAST(lf.userid AS CHAR) LIKE '222%' THEN 'usuario'
        ELSE 'dependente'
    END as tipo_pessoa
FROM leitura_faciais lf
LEFT JOIN usuarios u ON CAST(lf.userid AS CHAR) LIKE '222%' AND lf.userid = u.id
LEFT JOIN dependentes d ON CAST(lf.userid AS CHAR) NOT LIKE '222%' AND lf.userid = d.id
$where_clause
ORDER BY lf.createtime DESC
LIMIT 100";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$leituras = [];
while ($row = $result->fetch_assoc()) {
    $leituras[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Leituras Faciais</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .header-page {
            background: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 12px rgba(13, 110, 253, 0.25);
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .card-header {
            border-radius: 12px 12px 0 0 !important;
        }
        .table thead th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
        }
        .table tbody tr {
            vertical-align: middle;
        }
        .foto-leitura {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #dee2e6;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .foto-leitura:hover {
            transform: scale(1.5);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10;
            position: relative;
        }
        .modal-foto {
            max-width: 90vw;
            max-height: 90vh;
        }
        .table-responsive {
            max-height: 70vh;
            overflow-y: auto;
        }
        .badge-dispositivo {
            font-size: 0.85em;
        }
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
            color: #718096;
        }
        .empty-state i {
            font-size: 4rem;
            opacity: 0.25;
            display: block;
            margin-bottom: 1rem;
        }
        .badge-pill-info {
            font-size: 0.85rem;
            padding: 0.5rem 0.85rem;
            border-radius: 999px;
        }
        .placeholder-foto {
            width: 60px;
            height: 60px;
            background: #f1f3f5;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-page">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="mb-1"><i class="bi bi-camera-video me-2"></i>Visualizar Leituras Faciais</h3>
                    <small class="opacity-75">Histórico de leituras com fotos e identificação de usuário/dependente</small>
                </div>
                <div>
                    <a href="painel/dashboard.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Voltar ao Painel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 pb-5">
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtros de Busca</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label for="data" class="form-label">Data</label>
                        <input type="date" class="form-control" id="data" name="data" 
                               value="<?= htmlspecialchars($data_filtro) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="busca" class="form-label">Buscar (Nome, IP ou ID do Usuário)</label>
                        <input type="text" class="form-control" id="busca" name="busca" 
                               value="<?= htmlspecialchars($busca) ?>" 
                               placeholder="Digite para buscar...">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                    </div>
                </form>
                <div class="mt-3 d-flex align-items-center flex-wrap gap-2">
                    <span class="badge bg-info badge-pill-info"><i class="bi bi-collection me-1"></i><?= $total_registros ?> registro<?= $total_registros == 1 ? '' : 's' ?> encontrado<?= $total_registros == 1 ? '' : 's' ?></span>
                    <?php if (!empty($busca)): ?>
                        <a href="?data=<?= htmlspecialchars($data_filtro) ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i>Limpar Busca
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tabela de Leituras -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul"></i> Leituras do Dia: <?= date('d/m/Y', strtotime($data_filtro)) ?>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th style="width: 80px;">Foto</th>
                                <th>Data/Hora</th>
                                <th>Usuário</th>
                                <th>ID Usuário</th>
                                <th>Nome do Cartão</th>
                                <th>IP do Dispositivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leituras)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="bi bi-inbox"></i>
                                            <h5 class="mb-1">Nenhuma leitura encontrada</h5>
                                            <p class="mb-0 small">Tente outra data ou ajuste o filtro de busca.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leituras as $leitura): ?>
                                    <tr>
                                        <td class="text-center">
                                            <?php if (!empty($leitura['foto_base64'])): ?>
                                                <img src="data:image/jpeg;base64,<?= htmlspecialchars($leitura['foto_base64']) ?>" 
                                                     alt="Foto da leitura" 
                                                     class="foto-leitura"
                                                     data-bs-toggle="modal" 
                                                     data-bs-target="#modalFoto<?= $leitura['id'] ?>">
                                            <?php else: ?>
                                                <div class="placeholder-foto">
                                                    <i class="bi bi-image" style="font-size: 1.6rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= date('d/m/Y H:i:s', strtotime($leitura['createtime'])) ?>
                                        </td>
                                        <td>
                                            <?php if ($leitura['usuario_nome']): ?>
                                                <strong><?= htmlspecialchars($leitura['usuario_nome']) ?></strong>
                                                <?php if ($leitura['usuario_email']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($leitura['usuario_email']) ?></small>
                                                <?php endif; ?>
                                                <?php if (isset($leitura['tipo_pessoa'])): ?>
                                                    <br><small class="badge bg-<?= $leitura['tipo_pessoa'] === 'usuario' ? 'success' : 'info' ?>">
                                                        <?= $leitura['tipo_pessoa'] === 'usuario' ? 'Usuário' : 'Dependente' ?>
                                                    </small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Pessoa não encontrada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($leitura['userid']): ?>
                                                <span class="badge bg-primary"><?= $leitura['userid'] ?></span>
                                                <?php if (isset($leitura['tipo_pessoa'])): ?>
                                                    <br><small class="text-muted">
                                                        <?= $leitura['tipo_pessoa'] === 'usuario' ? 'ID Usuário' : 'ID Dependente' ?>
                                                    </small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($leitura['cardname']): ?>
                                                <?= htmlspecialchars($leitura['cardname']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary badge-dispositivo">
                                                <i class="bi bi-router"></i> <?= htmlspecialchars($leitura['remote_ip']) ?>
                                            </span>
                                        </td>
                                    </tr>

                                    <!-- Modal para foto ampliada -->
                                    <?php if (!empty($leitura['foto_base64'])): ?>
                                    <div class="modal fade" id="modalFoto<?= $leitura['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        Foto da Leitura - <?= date('d/m/Y H:i:s', strtotime($leitura['createtime'])) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                                </div>
                                                <div class="modal-body text-center">
                                                    <img src="data:image/jpeg;base64,<?= htmlspecialchars($leitura['foto_base64']) ?>" 
                                                         alt="Foto ampliada" 
                                                         class="img-fluid modal-foto">
                                                    <div class="mt-3">
                                                        <p class="mb-1"><strong>Usuário:</strong> <?= htmlspecialchars($leitura['usuario_nome'] ?: 'Não identificado') ?></p>
                                                        <p class="mb-1"><strong>IP:</strong> <?= htmlspecialchars($leitura['remote_ip']) ?></p>
                                                        <p class="mb-0"><strong>Data/Hora:</strong> <?= date('d/m/Y H:i:s', strtotime($leitura['createtime'])) ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>

