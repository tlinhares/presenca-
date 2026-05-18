<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('estoque_config_unidades');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unidades de Medida - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(79, 172, 254, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .table th { background: #f8f9fa; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; }
        .badge-ativo { background: #38a169; }
        .badge-inativo { background: #e53e3e; }
        .btn-action { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; }
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
                        <h5 class="mb-0"><i class="bi bi-rulers me-2"></i>Unidades de Medida</h5>
                        <small class="opacity-75">UN, KG, M, L...</small>
                    </div>
                </div>
                <button class="btn btn-light btn-sm" onclick="abrirModal()">
                    <i class="bi bi-plus-lg me-1"></i><span class="hide-mobile">Nova Unidade</span>
                </button>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <div class="card-main">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Sigla</th>
                            <th>Nome</th>
                            <th class="hide-mobile">Descrição</th>
                            <th class="text-center">Produtos</th>
                            <th class="text-center">Status</th>
                            <th style="width: 100px;" class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabela-unidades">
                        <tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modalUnidade" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                    <h6 class="modal-title"><i class="bi bi-rulers me-2"></i>Unidade</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formUnidade">
                        <input type="hidden" id="un_id">
                        <div class="mb-3">
                            <label class="form-label">Sigla <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="un_sigla" maxlength="10" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="un_nome" maxlength="50" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <input type="text" class="form-control" id="un_descricao">
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="un_ativo" checked>
                            <label class="form-check-label" for="un_ativo">Ativa</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="salvar()"><i class="bi bi-check-lg me-1"></i>Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';
        let unidades = [];
        
        $(document).ready(function() { carregar(); });
        
        function carregar() {
            $.getJSON(baseUrl + '/api/estoque/unidades/listar.php?todos=true', function(data) {
                if (data.status === 'ok') {
                    unidades = data.unidades;
                    renderizar();
                }
            });
        }
        
        function renderizar() {
            let html = '';
            if (unidades.length === 0) {
                html = '<tr><td colspan="6" class="text-center py-4 text-muted">Nenhuma unidade cadastrada</td></tr>';
            } else {
                unidades.forEach(u => {
                    html += `<tr class="${!u.ativo ? 'table-secondary' : ''}">
                        <td><span class="badge bg-primary fs-6">${u.sigla}</span></td>
                        <td><strong>${u.nome}</strong></td>
                        <td class="hide-mobile text-muted">${u.descricao || '-'}</td>
                        <td class="text-center"><span class="badge bg-secondary">${u.total_produtos}</span></td>
                        <td class="text-center"><span class="badge ${u.ativo ? 'badge-ativo' : 'badge-inativo'}">${u.ativo ? 'Ativa' : 'Inativa'}</span></td>
                        <td class="text-center">
                            <button class="btn btn-outline-primary btn-action" onclick="editar(${u.id})"><i class="bi bi-pencil"></i></button>
                        </td>
                    </tr>`;
                });
            }
            $('#tabela-unidades').html(html);
        }
        
        function abrirModal() {
            $('#formUnidade')[0].reset();
            $('#un_id').val('');
            $('#un_ativo').prop('checked', true);
            new bootstrap.Modal('#modalUnidade').show();
        }
        
        function editar(id) {
            const u = unidades.find(x => x.id === id);
            if (!u) return;
            $('#un_id').val(u.id);
            $('#un_sigla').val(u.sigla);
            $('#un_nome').val(u.nome);
            $('#un_descricao').val(u.descricao);
            $('#un_ativo').prop('checked', u.ativo);
            new bootstrap.Modal('#modalUnidade').show();
        }
        
        function salvar() {
            const sigla = $('#un_sigla').val().trim();
            const nome = $('#un_nome').val().trim();
            if (!sigla || !nome) { exibirToast('Sigla e Nome são obrigatórios', 'warning'); return; }
            
            $.post(baseUrl + '/api/estoque/unidades/salvar.php', {
                id: $('#un_id').val(),
                sigla: sigla,
                nome: nome,
                descricao: $('#un_descricao').val(),
                ativo: $('#un_ativo').is(':checked') ? 1 : 0
            }, function(data) {
                if (data.status === 'ok') {
                    bootstrap.Modal.getInstance('#modalUnidade').hide();
                    carregar();
                    exibirToast(data.mensagem, 'success');
                } else {
                    exibirToast(data.mensagem, 'danger');
                }
            }, 'json');
        }
    </script>
</body>
</html>

