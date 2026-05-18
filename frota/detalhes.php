<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../api/conexao.php';

// Verificar se tem permissão básica de frota
if (!MenuPermissaoService::podeAcessar('frota_dashboard')) {
    header('Location: ' . MenuPermissaoService::ajustarUrl('/resumo.php'));
    exit;
}

$utilizacaoId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$usuarioId = $_SESSION['usuario_id'] ?? 0;
$isAdmin = MenuPermissaoService::podeAcessar('frota_admin_veiculos');

if (!$utilizacaoId) {
    header('Location: ' . MenuPermissaoService::ajustarUrl('/frota/dashboard.php'));
    exit;
}

// Buscar dados da utilização
$sql = "SELECT fu.*, 
               v.placa, v.modelo, v.marca, v.cor, v.ano,
               u.nome as usuario_nome, u.email as usuario_email, u.foto_base64 as usuario_foto,
               fd.nome as departamento_nome,
               DATE_FORMAT(fu.data_saida, '%d/%m/%Y %H:%i') as data_saida_fmt,
               DATE_FORMAT(fu.data_entrada, '%d/%m/%Y %H:%i') as data_entrada_fmt
        FROM frota_utilizacoes fu
        JOIN frota_veiculos v ON fu.id_veiculo = v.id
        JOIN usuarios u ON fu.id_usuario = u.id
        LEFT JOIN frota_departamentos fd ON fu.id_departamento = fd.id
        WHERE fu.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $utilizacaoId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ' . MenuPermissaoService::ajustarUrl('/frota/dashboard.php'));
    exit;
}

$utilizacao = $result->fetch_assoc();

// Verificar se é o próprio usuário ou admin
if ($utilizacao['id_usuario'] != $usuarioId && !$isAdmin) {
    header('Location: ' . MenuPermissaoService::ajustarUrl('/frota/dashboard.php'));
    exit;
}

// Buscar checklist de saída
$checklist_saida = null;
$sql_check = "SELECT * FROM frota_checklist WHERE id_utilizacao = ? AND tipo = 'saida'";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $utilizacaoId);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
if ($result_check->num_rows > 0) {
    $checklist_saida = $result_check->fetch_assoc();
}

// Buscar checklist de entrada
$checklist_entrada = null;
$sql_check_e = "SELECT * FROM frota_checklist WHERE id_utilizacao = ? AND tipo = 'entrada'";
$stmt_check_e = $conn->prepare($sql_check_e);
$stmt_check_e->bind_param("i", $utilizacaoId);
$stmt_check_e->execute();
$result_check_e = $stmt_check_e->get_result();
if ($result_check_e->num_rows > 0) {
    $checklist_entrada = $result_check_e->fetch_assoc();
}

// Calcular tempo de uso
$tempo_formatado = '-';
if ($utilizacao['tempo_utilizacao']) {
    $min = intval($utilizacao['tempo_utilizacao']);
    $h = floor($min / 60);
    $m = $min % 60;
    $tempo_formatado = $h > 0 ? "{$h}h {$m}min" : "{$m}min";
}

// Buscar valor do KM
$valor_km = 0;
$sql_config = "SELECT valor FROM frota_configuracoes WHERE chave = 'valor_km' LIMIT 1";
$result_config = $conn->query($sql_config);
if ($result_config && $row_config = $result_config->fetch_assoc()) {
    $valor_km = floatval($row_config['valor']);
}
$valor_locacao = $utilizacao['km_percorrido'] ? intval($utilizacao['km_percorrido']) * $valor_km : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Utilização - Frota</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; }
        .header-detalhes {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }
        .card-info {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        .card-info .card-header {
            background: #f8f9fa;
            border-radius: 12px 12px 0 0;
            padding: 1rem 1.5rem;
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
        }
        .card-info .card-body { padding: 1.5rem; }
        
        .placa-grande {
            background-color: #ffc107;
            color: #212529;
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: bold;
            font-family: monospace;
            font-size: 1.8rem;
            display: inline-block;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child { border-bottom: none; }
        .info-row .label { color: #6c757d; }
        .info-row .value { font-weight: 600; }
        
        .foto-thumb {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s;
            border: 2px solid #e9ecef;
        }
        .foto-thumb:hover {
            transform: scale(1.02);
            border-color: #17a2b8;
        }
        
        .foto-placeholder {
            width: 100%;
            height: 180px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            border: 2px dashed #dee2e6;
        }
        
        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-finalizado { background-color: #d4edda; color: #155724; }
        .status-em_andamento { background-color: #fff3cd; color: #856404; }
        .status-cancelado { background-color: #f8d7da; color: #721c24; }
        
        .checklist-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        .checklist-item:last-child { border-bottom: none; }
        .check-ok { color: #28a745; }
        .check-nao { color: #dc3545; }
        
        .section-divider {
            position: relative;
            text-align: center;
            margin: 2rem 0;
        }
        .section-divider::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            border-top: 2px dashed #dee2e6;
        }
        .section-divider span {
            background: #f0f2f5;
            padding: 0 1rem;
            position: relative;
            color: #6c757d;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-detalhes">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1"><i class="bi bi-file-text me-2"></i>Detalhes da Utilização</h3>
                    <small class="opacity-75">Registro #<?= $utilizacaoId ?></small>
                </div>
                <div>
                    <a href="<?= MenuPermissaoService::ajustarUrl('/frota/historico.php') ?>" class="btn btn-outline-light btn-sm me-2">
                        <i class="bi bi-arrow-left me-1"></i>Voltar
                    </a>
                    <a href="<?= MenuPermissaoService::ajustarUrl('/api/frota/comprovante_pdf.php?id=' . $utilizacaoId) ?>" 
                       target="_blank" class="btn btn-light btn-sm">
                        <i class="bi bi-file-pdf me-1"></i>Gerar Comprovante
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- Coluna Principal -->
            <div class="col-lg-8">
                <!-- Info do Veículo -->
                <div class="card-info">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-truck me-2"></i>Veículo</span>
                        <span class="status-badge status-<?= $utilizacao['status'] ?>">
                            <?= $utilizacao['status'] === 'finalizado' ? 'Finalizado' : 
                               ($utilizacao['status'] === 'em_andamento' ? 'Em Andamento' : 'Cancelado') ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="placa-grande"><?= $utilizacao['placa'] ?></span>
                            </div>
                            <div class="col">
                                <h4 class="mb-1"><?= htmlspecialchars($utilizacao['modelo']) ?></h4>
                                <p class="text-muted mb-1">
                                    <?= htmlspecialchars($utilizacao['marca']) ?>
                                    <?= $utilizacao['ano'] ? ' • ' . $utilizacao['ano'] : '' ?>
                                    <?= $utilizacao['cor'] ? ' • ' . $utilizacao['cor'] : '' ?>
                                </p>
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-currency-dollar"></i> Valor KM: R$ <?= number_format($valor_km, 2, ',', '.') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dados da Saída -->
                <div class="card-info">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-box-arrow-right me-2"></i>Saída do Veículo
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="info-row">
                                    <span class="label">Data/Hora Saída</span>
                                    <span class="value"><?= $utilizacao['data_saida_fmt'] ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">KM Saída</span>
                                    <span class="value"><?= number_format($utilizacao['km_saida'], 0, ',', '.') ?> km</span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Departamento</span>
                                    <span class="value"><?= htmlspecialchars($utilizacao['departamento_nome'] ?? '-') ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Destino</span>
                                    <span class="value"><?= htmlspecialchars($utilizacao['destino'] ?? '-') ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Motivo</span>
                                    <span class="value"><?= htmlspecialchars($utilizacao['motivo'] ?? '-') ?></span>
                                </div>
                                <?php if ($utilizacao['observacoes_saida']): ?>
                                <div class="info-row">
                                    <span class="label">Observações</span>
                                    <span class="value"><?= nl2br(htmlspecialchars($utilizacao['observacoes_saida'])) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <!-- Fotos de Saída -->
                                <h6 class="mb-3"><i class="bi bi-camera me-2"></i>Fotos de Saída</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <small class="text-muted d-block mb-1">Selfie</small>
                                        <?php if (!empty($utilizacao['foto_selfie_saida'])): ?>
                                        <img src="<?= MenuPermissaoService::ajustarUrl('/uploads/frota/' . $utilizacao['foto_selfie_saida']) ?>" 
                                             class="foto-thumb" onclick="ampliarFoto(this.src)" alt="Selfie">
                                        <?php else: ?>
                                        <div class="foto-placeholder"><i class="bi bi-person-bounding-box"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block mb-1">KM Painel</small>
                                        <?php if ($utilizacao['foto_km_saida']): ?>
                                        <img src="<?= MenuPermissaoService::ajustarUrl('/uploads/frota/' . $utilizacao['foto_km_saida']) ?>" 
                                             class="foto-thumb" onclick="ampliarFoto(this.src)" alt="KM">
                                        <?php else: ?>
                                        <div class="foto-placeholder"><i class="bi bi-speedometer2"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php for ($i = 1; $i <= 3; $i++): 
                                        $campo = 'foto_veiculo_saida_' . $i;
                                        if ($utilizacao[$campo]): ?>
                                    <div class="col-4">
                                        <small class="text-muted d-block mb-1">Veículo <?= $i ?></small>
                                        <img src="<?= MenuPermissaoService::ajustarUrl('/uploads/frota/' . $utilizacao[$campo]) ?>" 
                                             class="foto-thumb" onclick="ampliarFoto(this.src)" alt="Veículo <?= $i ?>">
                                    </div>
                                    <?php endif; endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($utilizacao['status'] === 'finalizado'): ?>
                <!-- Divider -->
                <div class="section-divider">
                    <span><i class="bi bi-arrow-down-circle me-2"></i>DEVOLUÇÃO</span>
                </div>

                <!-- Dados da Entrada -->
                <div class="card-info">
                    <div class="card-header bg-warning text-dark">
                        <i class="bi bi-box-arrow-in-left me-2"></i>Devolução do Veículo
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="info-row">
                                    <span class="label">Data/Hora Entrada</span>
                                    <span class="value"><?= $utilizacao['data_entrada_fmt'] ?? '-' ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">KM Entrada</span>
                                    <span class="value"><?= $utilizacao['km_entrada'] ? number_format($utilizacao['km_entrada'], 0, ',', '.') . ' km' : '-' ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">KM Percorrido</span>
                                    <span class="value text-success">
                                        <strong><?= $utilizacao['km_percorrido'] ? number_format($utilizacao['km_percorrido'], 0, ',', '.') . ' km' : '-' ?></strong>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Tempo de Uso</span>
                                    <span class="value text-info"><strong><?= $tempo_formatado ?></strong></span>
                                </div>
                                <?php if ($valor_km > 0 && $utilizacao['km_percorrido']): ?>
                                <div class="info-row" style="background: #f0fff4; padding: 0.75rem; border-radius: 8px; margin-top: 0.5rem;">
                                    <span class="label"><i class="bi bi-calculator me-1"></i>Valor da Locação</span>
                                    <span class="value text-success" style="font-size: 1.1rem;">
                                        <strong>R$ <?= number_format($valor_locacao, 2, ',', '.') ?></strong>
                                    </span>
                                </div>
                                <div class="info-row" style="padding-top: 0.25rem;">
                                    <span class="label" style="font-size: 0.8rem;">Cálculo</span>
                                    <span class="value" style="font-size: 0.8rem; color: #6c757d;">
                                        <?= number_format($utilizacao['km_percorrido'], 0, ',', '.') ?> km × R$ <?= number_format($valor_km, 2, ',', '.') ?>/km
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if ($utilizacao['observacoes_entrada']): ?>
                                <div class="info-row">
                                    <span class="label">Observações</span>
                                    <span class="value"><?= nl2br(htmlspecialchars($utilizacao['observacoes_entrada'])) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <!-- Fotos de Entrada -->
                                <h6 class="mb-3"><i class="bi bi-camera me-2"></i>Fotos de Devolução</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <small class="text-muted d-block mb-1">Selfie</small>
                                        <?php if ($utilizacao['foto_selfie_entrada']): ?>
                                        <img src="<?= MenuPermissaoService::ajustarUrl('/uploads/frota/' . $utilizacao['foto_selfie_entrada']) ?>" 
                                             class="foto-thumb" onclick="ampliarFoto(this.src)" alt="Selfie">
                                        <?php else: ?>
                                        <div class="foto-placeholder"><i class="bi bi-person-bounding-box"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block mb-1">KM Painel</small>
                                        <?php if ($utilizacao['foto_km_entrada']): ?>
                                        <img src="<?= MenuPermissaoService::ajustarUrl('/uploads/frota/' . $utilizacao['foto_km_entrada']) ?>" 
                                             class="foto-thumb" onclick="ampliarFoto(this.src)" alt="KM">
                                        <?php else: ?>
                                        <div class="foto-placeholder"><i class="bi bi-speedometer2"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php for ($i = 1; $i <= 3; $i++): 
                                        $campo = 'foto_veiculo_entrada_' . $i;
                                        if ($utilizacao[$campo]): ?>
                                    <div class="col-4">
                                        <small class="text-muted d-block mb-1">Veículo <?= $i ?></small>
                                        <img src="<?= MenuPermissaoService::ajustarUrl('/uploads/frota/' . $utilizacao[$campo]) ?>" 
                                             class="foto-thumb" onclick="ampliarFoto(this.src)" alt="Veículo <?= $i ?>">
                                    </div>
                                    <?php endif; endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Coluna Lateral -->
            <div class="col-lg-4">
                <!-- Usuário -->
                <div class="card-info">
                    <div class="card-header">
                        <i class="bi bi-person me-2"></i>Usuário
                    </div>
                    <div class="card-body text-center">
                        <?php if (!empty($utilizacao['usuario_foto'])): 
                            // Verificar se já tem o prefixo data:image, senão adicionar
                            $foto_src = $utilizacao['usuario_foto'];
                            if (strpos($foto_src, 'data:image') === false) {
                                $foto_src = 'data:image/jpeg;base64,' . $foto_src;
                            }
                        ?>
                            <img src="<?= $foto_src ?>" 
                                 alt="Foto do usuário" 
                                 class="rounded-circle mb-2" 
                                 style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #e9ecef;">
                        <?php else: ?>
                            <i class="bi bi-person-circle display-4 text-muted"></i>
                        <?php endif; ?>
                        <h5 class="mt-2 mb-1"><?= htmlspecialchars($utilizacao['usuario_nome']) ?></h5>
                        <small class="text-muted"><?= htmlspecialchars($utilizacao['usuario_email']) ?></small>
                    </div>
                </div>

                <!-- Resumo -->
                <div class="card-info">
                    <div class="card-header">
                        <i class="bi bi-clipboard-data me-2"></i>Resumo
                    </div>
                    <div class="card-body">
                        <div class="row g-3 text-center">
                            <div class="col-6">
                                <div class="p-3 bg-light rounded">
                                    <div class="h4 text-success mb-0">
                                        <?= $utilizacao['km_percorrido'] ? number_format($utilizacao['km_percorrido'], 0, ',', '.') : '0' ?>
                                    </div>
                                    <small class="text-muted">KM Percorrido</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 bg-light rounded">
                                    <div class="h4 text-info mb-0"><?= $tempo_formatado ?></div>
                                    <small class="text-muted">Tempo de Uso</small>
                                </div>
                            </div>
                        </div>
                        <?php if ($valor_km > 0): ?>
                        <hr>
                        <div class="text-center">
                            <small class="text-muted d-block mb-1">Valor do KM: R$ <?= number_format($valor_km, 2, ',', '.') ?></small>
                            <div class="p-3 rounded" style="background: linear-gradient(135deg, #d4edda, #c3e6cb);">
                                <div class="h3 text-success mb-0">
                                    R$ <?= number_format($valor_locacao, 2, ',', '.') ?>
                                </div>
                                <small class="text-muted"><strong>Valor Total da Locação</strong></small>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <?= $utilizacao['km_percorrido'] ? number_format($utilizacao['km_percorrido'], 0, ',', '.') : '0' ?> km × R$ <?= number_format($valor_km, 2, ',', '.') ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Checklist Saída -->
                <?php if ($checklist_saida): ?>
                <div class="card-info">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-check2-square me-2"></i>Checklist Saída
                    </div>
                    <div class="card-body p-0">
                        <div class="checklist-item px-3 pt-3">
                            <span>Pneus</span>
                            <span class="<?= $checklist_saida['pneus_ok'] ? 'check-ok' : 'check-nao' ?>">
                                <i class="bi bi-<?= $checklist_saida['pneus_ok'] ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                            </span>
                        </div>
                        <div class="checklist-item px-3">
                            <span>Faróis</span>
                            <span class="<?= $checklist_saida['farois_ok'] ? 'check-ok' : 'check-nao' ?>">
                                <i class="bi bi-<?= $checklist_saida['farois_ok'] ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                            </span>
                        </div>
                        <div class="checklist-item px-3">
                            <span>Documentos</span>
                            <span class="<?= $checklist_saida['documentos_ok'] ? 'check-ok' : 'check-nao' ?>">
                                <i class="bi bi-<?= $checklist_saida['documentos_ok'] ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                            </span>
                        </div>
                        <div class="checklist-item px-3">
                            <span>Limpeza</span>
                            <span class="<?= $checklist_saida['limpeza_ok'] ? 'check-ok' : 'check-nao' ?>">
                                <i class="bi bi-<?= $checklist_saida['limpeza_ok'] ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                            </span>
                        </div>
                        <?php if ($checklist_saida['nivel_combustivel']): ?>
                        <div class="checklist-item px-3 pb-3">
                            <span>Combustível</span>
                            <span class="badge bg-warning text-dark"><?= $checklist_saida['nivel_combustivel'] ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($checklist_saida['avarias_encontradas']): ?>
                        <div class="p-3 bg-light">
                            <small class="text-muted">Avarias:</small>
                            <p class="mb-0 small"><?= nl2br(htmlspecialchars($checklist_saida['avarias_encontradas'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Checklist Entrada -->
                <?php if ($checklist_entrada): ?>
                <div class="card-info">
                    <div class="card-header bg-warning text-dark">
                        <i class="bi bi-check2-square me-2"></i>Checklist Devolução
                    </div>
                    <div class="card-body p-0">
                        <div class="checklist-item px-3 pt-3">
                            <span>Limpeza</span>
                            <span class="<?= $checklist_entrada['limpeza_ok'] ? 'check-ok' : 'check-nao' ?>">
                                <i class="bi bi-<?= $checklist_entrada['limpeza_ok'] ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                            </span>
                        </div>
                        <?php if ($checklist_entrada['nivel_combustivel']): ?>
                        <div class="checklist-item px-3 pb-3">
                            <span>Combustível</span>
                            <span class="badge bg-warning text-dark"><?= $checklist_entrada['nivel_combustivel'] ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($checklist_entrada['avarias_encontradas']): ?>
                        <div class="p-3 bg-light">
                            <small class="text-muted">Avarias:</small>
                            <p class="mb-0 small"><?= nl2br(htmlspecialchars($checklist_entrada['avarias_encontradas'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Ampliar Foto -->
    <div class="modal fade" id="modalFoto" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-image me-2"></i>Visualizar Foto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img id="fotoAmpliada" src="" class="img-fluid" style="max-height: 70vh;">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modalFoto = new bootstrap.Modal(document.getElementById('modalFoto'));
        
        function ampliarFoto(src) {
            document.getElementById('fotoAmpliada').src = src;
            modalFoto.show();
        }
    </script>
</body>
</html>

