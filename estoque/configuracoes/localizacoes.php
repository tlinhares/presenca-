<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('estoque_config_localizacoes');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Localizações - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #434343 0%, #000000 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(0,0,0,0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; }
        .table th { background: #f8f9fa; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: #4a5568; }
        .badge-ativo { background: #38a169; }
        .badge-inativo { background: #e53e3e; }
        .btn-action { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; }
        .loc-icon { width: 40px; height: 40px; border-radius: 10px; background: #4a5568; display: flex; align-items: center; justify-content: center; color: white; }
        .empty-state { padding: 3rem; text-align: center; color: #718096; }
        .empty-state i { font-size: 4rem; opacity: 0.3; }
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
                        <h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Localizações</h5>
                        <small class="opacity-75">Prateleiras, armários, etc.</small>
                    </div>
                </div>
                <button class="btn btn-light btn-sm" onclick="abrirModalNovo()">
                    <i class="bi bi-plus-lg me-1"></i><span class="hide-mobile">Nova Localização</span>
                </button>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <div class="card-main">
            <div class="card-header bg-white border-0 py-3 px-4">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <select class="form-select" id="filtro-departamento">
                            <option value="">Todos os departamentos</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="busca" placeholder="Buscar localização...">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;"></th>
                            <th>Nome</th>
                            <th class="hide-mobile">Código</th>
                            <th>Departamento</th>
                            <th class="text-center">Status</th>
                            <th style="width: 100px;" class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabela-localizacoes">
                        <tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modalLocalizacao" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                    <h5 class="modal-title"><i class="bi bi-geo-alt me-2"></i>Nova Localização</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formLocalizacao">
                        <input type="hidden" id="loc_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Departamento <span class="text-danger">*</span></label>
                            <select class="form-select" id="loc_departamento" required></select>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-8">
                                <label class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="loc_nome" required maxlength="100" placeholder="Ex: Prateleira A1">
                            </div>
                            <div class="col-4">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control" id="loc_codigo" maxlength="20" placeholder="Ex: A1">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" id="loc_descricao" rows="2" placeholder="Detalhes da localização..."></textarea>
                        </div>
                        
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="loc_ativo" checked>
                            <label class="form-check-label" for="loc_ativo">Localização ativa</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarLocalizacao()">
                        <i class="bi bi-check-lg me-1"></i>Salvar
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
        let localizacoes = [];
        let departamentos = [];
        
        $(document).ready(function() {
            carregarDados();
            $('#busca').on('input', renderizarTabela);
            $('#filtro-departamento').change(carregarLocalizacoes);
        });
        
        function carregarDados() {
            $.getJSON(baseUrl + '/api/estoque/departamentos/listar.php', function(data) {
                departamentos = data.departamentos || [];
                
                let html = '<option value="">Todos os departamentos</option>';
                departamentos.forEach(d => html += `<option value="${d.id}">${d.nome}</option>`);
                $('#filtro-departamento').html(html);
                
                html = '<option value="">Selecione...</option>';
                departamentos.forEach(d => html += `<option value="${d.id}">${d.nome}</option>`);
                $('#loc_departamento').html(html);
                
                carregarLocalizacoes();
            });
        }
        
        function carregarLocalizacoes() {
            const departamento = $('#filtro-departamento').val();
            
            $.getJSON(baseUrl + '/api/estoque/localizacoes/listar.php', { departamento: departamento, todos: 'true' }, function(data) {
                if (data.status === 'ok') {
                    localizacoes = data.localizacoes;
                    renderizarTabela();
                }
            });
        }
        
        function renderizarTabela() {
            const tbody = $('#tabela-localizacoes');
            const busca = $('#busca').val().toLowerCase();
            
            const filtrados = localizacoes.filter(l => 
                l.nome.toLowerCase().includes(busca) ||
                (l.codigo && l.codigo.toLowerCase().includes(busca))
            );
            
            if (filtrados.length === 0) {
                tbody.html(`<tr><td colspan="6"><div class="empty-state"><i class="bi bi-geo-alt"></i><h5>Nenhuma localização encontrada</h5></div></td></tr>`);
                return;
            }
            
            let html = '';
            filtrados.forEach(l => {
                html += `
                    <tr class="${!l.ativo ? 'table-secondary' : ''}">
                        <td><div class="loc-icon"><i class="bi bi-geo-alt"></i></div></td>
                        <td>
                            <strong>${l.nome}</strong>
                            ${l.descricao ? `<br><small class="text-muted">${l.descricao}</small>` : ''}
                        </td>
                        <td class="hide-mobile"><span class="badge bg-secondary">${l.codigo || '-'}</span></td>
                        <td>${l.departamento_nome}</td>
                        <td class="text-center">
                            <span class="badge ${l.ativo ? 'badge-ativo' : 'badge-inativo'}">
                                ${l.ativo ? 'Ativo' : 'Inativo'}
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-outline-primary btn-action me-1" onclick="editarLocalizacao(${l.id})"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-outline-danger btn-action" onclick="excluirLocalizacao(${l.id})"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                `;
            });
            tbody.html(html);
        }
        
        function abrirModalNovo() {
            $('#formLocalizacao')[0].reset();
            $('#loc_id').val('');
            $('#loc_ativo').prop('checked', true);
            new bootstrap.Modal('#modalLocalizacao').show();
        }
        
        function editarLocalizacao(id) {
            const l = localizacoes.find(x => x.id === id);
            if (!l) return;
            
            $('#loc_id').val(l.id);
            $('#loc_departamento').val(l.id_departamento);
            $('#loc_nome').val(l.nome);
            $('#loc_codigo').val(l.codigo);
            $('#loc_descricao').val(l.descricao);
            $('#loc_ativo').prop('checked', l.ativo);
            
            new bootstrap.Modal('#modalLocalizacao').show();
        }
        
        function salvarLocalizacao() {
            const departamento = $('#loc_departamento').val();
            const nome = $('#loc_nome').val().trim();
            
            if (!departamento || !nome) {
                exibirToast('Departamento e Nome são obrigatórios', 'warning');
                return;
            }
            
            $.post(baseUrl + '/api/estoque/localizacoes/salvar.php', {
                id: $('#loc_id').val(),
                id_departamento: departamento,
                nome: nome,
                codigo: $('#loc_codigo').val().trim(),
                descricao: $('#loc_descricao').val().trim(),
                ativo: $('#loc_ativo').is(':checked') ? 1 : 0
            }, function(data) {
                if (data.status === 'ok') {
                    bootstrap.Modal.getInstance('#modalLocalizacao').hide();
                    carregarLocalizacoes();
                    exibirToast(data.mensagem, 'success');
                } else {
                    exibirToast(data.mensagem, 'danger');
                }
            }, 'json');
        }
        
        function excluirLocalizacao(id) {
            if (!confirm('Excluir esta localização?')) return;
            
            $.post(baseUrl + '/api/estoque/localizacoes/excluir.php', { id: id }, function(data) {
                if (data.status === 'ok') {
                    carregarLocalizacoes();
                    exibirToast(data.mensagem, 'success');
                } else {
                    exibirToast(data.mensagem, 'danger');
                }
            }, 'json');
        }
    </script>
</body>
</html>



