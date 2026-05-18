<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../api/conexao.php';
require_once __DIR__ . '/../../api/estoque/almoxarife_helper.php';

MenuPermissaoService::exigirAcesso('estoque_requisicoes');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
$isAdmin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
// Quem tem visão completa: admin do sistema ou cadastrado em estoque_responsaveis.
// Demais usuários só veem as próprias requisições (filtros ocultos).
$temVisaoCompleta = eh_almoxarife($conn, $usuarioId, $isAdmin);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Requisições - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(240, 147, 251, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        
        .req-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-left: 4px solid #718096;
            cursor: pointer;
            transition: all 0.2s;
        }
        .req-card:hover { transform: translateX(5px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .req-card.pendente { border-left-color: #ecc94b; }
        .req-card.aguardando_lancamento { border-left-color: #9f7aea; }
        .req-card.aprovada { border-left-color: #48bb78; }
        .req-card.parcial { border-left-color: #4299e1; }
        .req-card.entregue { border-left-color: #38a169; }
        .req-card.cancelada, .req-card.rejeitada { border-left-color: #e53e3e; }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-badge.rascunho { background: #e2e8f0; color: #4a5568; }
        .status-badge.pendente { background: #fefcbf; color: #744210; }
        .status-badge.aguardando_lancamento { background: #e9d8fd; color: #553c9a; }
        .status-badge.aprovada { background: #c6f6d5; color: #22543d; }
        .status-badge.parcial { background: #bee3f8; color: #2a4365; }
        .status-badge.entregue { background: #9ae6b4; color: #1c4532; }
        .status-badge.cancelada, .status-badge.rejeitada { background: #fed7d7; color: #742a2a; }
        
        .priority-badge {
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .priority-badge.baixa { background: #718096; color: white; }
        .priority-badge.normal { background: #3182ce; color: white; }
        .priority-badge.alta { background: #dd6b20; color: white; }
        .priority-badge.urgente { background: #e53e3e; color: white; }
        
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }
        .filter-tab {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            background: #e2e8f0;
            color: #4a5568;
            border: none;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-tab:hover, .filter-tab.active { background: #f093fb; color: white; }
        
        @media (max-width: 768px) { .hide-mobile { display: none !important; } }
    </style>
</head>
<body>
    <div class="header-page">
        <div class="container-fluid px-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <a href="../dashboard.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h5 class="mb-0"><i class="bi bi-clipboard-check me-2"></i><?= $temVisaoCompleta ? 'Requisições' : 'Minhas Requisições' ?></h5>
                        <small class="opacity-75" id="subtitulo"><?= $temVisaoCompleta ? 'Solicitações de materiais' : 'Suas solicitações de materiais' ?></small>
                    </div>
                </div>
                <a href="nova.php" class="btn btn-light btn-sm">
                    <i class="bi bi-plus-lg me-1"></i><span class="hide-mobile">Nova Requisição</span>
                </a>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <?php if ($temVisaoCompleta): ?>
        <!-- Filtros completos (modo almoxarife/admin) -->
        <div class="card-main p-3 mb-4">
            <div class="filter-tabs mb-3">
                <button class="filter-tab active" data-status="">Todas</button>
                <button class="filter-tab" data-status="aguardando_lancamento">Aguardando lançamento</button>
                <button class="filter-tab" data-status="pendente">Pendentes</button>
                <button class="filter-tab" data-status="aprovada">Aprovadas</button>
                <button class="filter-tab" data-status="parcial">Parciais</button>
                <button class="filter-tab" data-status="entregue">Entregues</button>
                <button class="filter-tab" data-status="cancelada">Canceladas</button>
            </div>

            <div class="row g-2">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="busca" placeholder="Buscar por número...">
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filtro-departamento">
                        <option value="">Todos os departamentos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="minhas-requisicoes">
                        <label class="form-check-label" for="minhas-requisicoes">Apenas minhas</label>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Filtros enxutos (modo solicitante: vê só as próprias) -->
        <div class="card-main p-3 mb-4">
            <div class="row g-2">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="busca" placeholder="Buscar por número...">
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Lista -->
        <div id="lista-requisicoes">
            <div class="text-center py-5">
                <div class="spinner-border text-primary"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';
        const temVisaoCompleta = <?= $temVisaoCompleta ? 'true' : 'false' ?>;
        let requisicoes = [];
        let statusFiltro = '';

        $(document).ready(function() {
            if (temVisaoCompleta) {
                carregarDepartamentos();
            }
            carregarRequisicoes();

            $('.filter-tab').click(function() {
                $('.filter-tab').removeClass('active');
                $(this).addClass('active');
                statusFiltro = $(this).data('status');
                carregarRequisicoes();
            });

            $('#busca').on('input', debounce(carregarRequisicoes, 300));
            $('#filtro-departamento, #minhas-requisicoes').change(carregarRequisicoes);
        });
        
        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }
        
        function carregarDepartamentos() {
            $.getJSON(baseUrl + '/api/estoque/departamentos/listar.php', function(data) {
                if (data.status === 'ok') {
                    let html = '<option value="">Todos os departamentos</option>';
                    data.departamentos.forEach(d => {
                        html += `<option value="${d.id}">${d.nome}</option>`;
                    });
                    $('#filtro-departamento').html(html);
                }
            });
        }
        
        function carregarRequisicoes() {
            const params = {
                status: statusFiltro,
                departamento: $('#filtro-departamento').val() || '',
                minhas: ($('#minhas-requisicoes').is(':checked') || !temVisaoCompleta) ? 'true' : 'false',
                busca: $('#busca').val() || ''
            };

            $.getJSON(baseUrl + '/api/estoque/requisicoes/listar.php', params, function(data) {
                if (data.status === 'ok') {
                    requisicoes = data.requisicoes;
                    renderizarRequisicoes();
                    const sufixo = data.total === 1 ? 'requisição' : 'requisições';
                    $('#subtitulo').text(data.total + ' ' + sufixo);
                }
            });
        }
        
        function renderizarRequisicoes() {
            const container = $('#lista-requisicoes');
            
            if (requisicoes.length === 0) {
                container.html(`
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-clipboard fs-1 d-block mb-3"></i>
                        <h5>Nenhuma requisição encontrada</h5>
                        <a href="nova.php" class="btn btn-primary mt-3">
                            <i class="bi bi-plus-lg me-2"></i>Nova Requisição
                        </a>
                    </div>
                `);
                return;
            }
            
            let html = '';
            requisicoes.forEach(r => {
                const statusLabel = r.status === 'aguardando_lancamento' ? 'Aguardando lançamento' : r.status;
                html += `
                    <div class="req-card ${r.status}" onclick="window.location='visualizar.php?id=${r.id}'">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong class="fs-5">#${r.numero}</strong>
                                <span class="priority-badge ${r.prioridade} ms-2">${r.prioridade}</span>
                            </div>
                            <span class="status-badge ${r.status}">${statusLabel}</span>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <small class="text-muted d-block">Solicitante</small>
                                <span>${r.solicitante || '—'}</span>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">Departamento</small>
                                <span>${r.departamento_origem ? r.departamento_origem : '<span class="text-muted fst-italic">—</span>'}</span>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted d-block">Itens</small>
                                <span>${r.total_itens || 0}</span>
                            </div>
                            <div class="col-md-2 text-end">
                                <small class="text-muted d-block">Data</small>
                                <span>${r.data_formatada || '—'}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.html(html);
        }
    </script>
</body>
</html>

