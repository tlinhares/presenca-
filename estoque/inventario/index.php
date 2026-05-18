<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('estoque_inventario');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Inventário - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(250, 112, 154, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .inv-card { background: white; border-radius: 12px; padding: 1rem; margin-bottom: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); cursor: pointer; transition: all 0.2s; }
        .inv-card:hover { transform: translateX(5px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .inv-card.em_andamento { border-left: 4px solid #4299e1; }
        .inv-card.finalizado { border-left: 4px solid #48bb78; }
        .inv-card.cancelado { border-left: 4px solid #e53e3e; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .status-badge.em_andamento { background: #bee3f8; color: #2a4365; }
        .status-badge.finalizado { background: #c6f6d5; color: #22543d; }
        .status-badge.cancelado { background: #fed7d7; color: #742a2a; }
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
                        <h5 class="mb-0"><i class="bi bi-ui-checks me-2"></i>Inventário</h5>
                        <small class="opacity-75">Contagem física de estoque</small>
                    </div>
                </div>
                <button class="btn btn-light btn-sm" onclick="novoInventario()">
                    <i class="bi bi-plus-lg me-1"></i><span class="hide-mobile">Novo Inventário</span>
                </button>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <div class="card-main p-3 mb-4">
            <div class="row g-2">
                <div class="col-md-4">
                    <select class="form-select" id="filtro-departamento">
                        <option value="">Todos os departamentos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filtro-status">
                        <option value="">Todos os status</option>
                        <option value="em_andamento">Em andamento</option>
                        <option value="finalizado">Finalizado</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
            </div>
        </div>

        <div id="lista-inventarios">
            <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
        </div>
    </div>

    <!-- Modal Novo Inventário -->
    <div class="modal fade" id="modalNovoInventario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Novo Inventário</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Departamento <span class="text-danger">*</span></label>
                        <select class="form-select" id="inv-departamento" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data do Inventário</label>
                        <input type="date" class="form-control" id="inv-data" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" id="inv-obs" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="criarInventario()">
                        <i class="bi bi-check-lg me-1"></i>Criar Inventário
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';
        
        $(document).ready(function() {
            carregarDepartamentos();
            carregarInventarios();
            
            $('#filtro-departamento, #filtro-status').change(carregarInventarios);
        });
        
        function carregarDepartamentos() {
            $.getJSON(baseUrl + '/api/estoque/departamentos/listar.php', function(data) {
                if (data.status === 'ok') {
                    let html = '<option value="">Todos os departamentos</option>';
                    let htmlModal = '<option value="">Selecione...</option>';
                    data.departamentos.forEach(d => {
                        html += `<option value="${d.id}">${d.nome}</option>`;
                        htmlModal += `<option value="${d.id}">${d.nome}</option>`;
                    });
                    $('#filtro-departamento').html(html);
                    $('#inv-departamento').html(htmlModal);
                }
            });
        }
        
        function carregarInventarios() {
            const params = {
                departamento: $('#filtro-departamento').val(),
                status: $('#filtro-status').val()
            };
            
            $.getJSON(baseUrl + '/api/estoque/inventarios/listar.php', params, function(data) {
                const container = $('#lista-inventarios');
                
                if (data.status === 'ok' && data.inventarios && data.inventarios.length > 0) {
                    let html = '';
                    data.inventarios.forEach(inv => {
                        // Se finalizado, abre PDF em nova aba. Caso contrário, abre página de contagem
                        const url = inv.status === 'finalizado' 
                            ? baseUrl + '/api/estoque/inventarios/pdf.php?id=' + inv.id
                            : 'contar.php?id=' + inv.id;
                        
                        const onclick = inv.status === 'finalizado'
                            ? `window.open('${url}', '_blank')`
                            : `window.location='${url}'`;
                        
                        html += `
                            <div class="inv-card ${inv.status}" onclick="${onclick}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>#${inv.id} - ${inv.departamento_nome}</strong>
                                        <span class="status-badge ${inv.status} ms-2">${inv.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                                        <div class="text-muted small">${inv.data_formatada}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="small">${inv.total_itens || 0} itens</div>
                                        <div class="small text-muted">${inv.responsavel}</div>
                                        ${inv.status === 'finalizado' ? '<div class="small text-info"><i class="bi bi-file-pdf"></i> PDF</div>' : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    container.html(html);
                } else {
                    container.html(`
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-ui-checks fs-1 d-block mb-3"></i>
                            <h5>Nenhum inventário encontrado</h5>
                            <button class="btn btn-primary mt-3" onclick="novoInventario()">
                                <i class="bi bi-plus-lg me-2"></i>Criar Inventário
                            </button>
                        </div>
                    `);
                }
            }).fail(function() {
                exibirToast('Erro ao carregar inventários', 'danger');
            });
        }
        
        function novoInventario() {
            new bootstrap.Modal('#modalNovoInventario').show();
        }
        
        function criarInventario() {
            const departamento = $('#inv-departamento').val();
            if (!departamento) {
                exibirToast('Selecione o departamento', 'warning');
                return;
            }
            
            $.post(baseUrl + '/api/estoque/inventarios/criar.php', {
                departamento: departamento,
                data: $('#inv-data').val(),
                observacoes: $('#inv-obs').val()
            }, function(data) {
                if (data.status === 'ok') {
                    exibirToast('Inventário criado com sucesso!', 'success');
                    bootstrap.Modal.getInstance('#modalNovoInventario').hide();
                    window.location.href = 'contar.php?id=' + data.id;
                } else {
                    exibirToast(data.mensagem || 'Erro ao criar inventário', 'danger');
                }
            }, 'json').fail(function() {
                exibirToast('Erro ao criar inventário', 'danger');
            });
        }
    </script>
</body>
</html>



