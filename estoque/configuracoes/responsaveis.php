<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('estoque_config_responsaveis');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Responsáveis - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(250, 112, 154, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; }
        .table th { background: #f8f9fa; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: #4a5568; }
        .badge-responsavel { background: #38a169; }
        .badge-auxiliar { background: #3182ce; }
        .badge-inativo { background: #e53e3e; }
        .btn-action { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 10px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #4a5568; }
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
                        <h5 class="mb-0"><i class="bi bi-people me-2"></i>Responsáveis</h5>
                        <small class="opacity-75">Gestores por departamento</small>
                    </div>
                </div>
                <button class="btn btn-light btn-sm" onclick="abrirModalNovo()">
                    <i class="bi bi-plus-lg me-1"></i><span class="hide-mobile">Adicionar</span>
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
                            <input type="text" class="form-control" id="busca" placeholder="Buscar usuário...">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;"></th>
                            <th>Usuário</th>
                            <th>Departamento</th>
                            <th class="text-center">Tipo</th>
                            <th class="text-center">Status</th>
                            <th style="width: 100px;" class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabela-responsaveis">
                        <tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modalResponsavel" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Adicionar Responsável</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formResponsavel">
                        <input type="hidden" id="resp_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Departamento <span class="text-danger">*</span></label>
                            <select class="form-select" id="resp_departamento" required></select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Usuário <span class="text-danger">*</span></label>
                            <select class="form-select" id="resp_usuario" required></select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" id="resp_tipo">
                                <option value="responsavel">Responsável (Gerente)</option>
                                <option value="auxiliar">Auxiliar</option>
                            </select>
                        </div>
                        
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="resp_ativo" checked>
                            <label class="form-check-label" for="resp_ativo">Ativo</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarResponsavel()">
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
        let responsaveis = [];
        let departamentos = [];
        let usuarios = [];
        
        $(document).ready(function() {
            carregarDados();
            $('#busca').on('input', renderizarTabela);
            $('#filtro-departamento').change(carregarResponsaveis);
        });
        
        function carregarDados() {
            Promise.all([
                $.getJSON(baseUrl + '/api/estoque/departamentos/listar.php'),
                $.getJSON(baseUrl + '/api/usuarios/listar.php')
            ]).then(function([deptData, usrData]) {
                departamentos = deptData.departamentos || [];
                usuarios = usrData.dados || [];
                
                let html = '<option value="">Todos os departamentos</option>';
                departamentos.forEach(d => html += `<option value="${d.id}">${d.nome}</option>`);
                $('#filtro-departamento').html(html);
                
                html = '<option value="">Selecione...</option>';
                departamentos.forEach(d => html += `<option value="${d.id}">${d.nome}</option>`);
                $('#resp_departamento').html(html);
                
                html = '<option value="">Selecione...</option>';
                usuarios.forEach(u => html += `<option value="${u.id}">${u.nome}</option>`);
                $('#resp_usuario').html(html);
                
                carregarResponsaveis();
            });
        }
        
        function carregarResponsaveis() {
            const departamento = $('#filtro-departamento').val();
            
            $.getJSON(baseUrl + '/api/estoque/responsaveis/listar.php', { departamento: departamento }, function(data) {
                if (data.status === 'ok') {
                    responsaveis = data.responsaveis;
                    renderizarTabela();
                }
            });
        }
        
        function renderizarTabela() {
            const tbody = $('#tabela-responsaveis');
            const busca = $('#busca').val().toLowerCase();
            
            const filtrados = responsaveis.filter(r => 
                r.usuario_nome.toLowerCase().includes(busca) ||
                r.departamento_nome.toLowerCase().includes(busca)
            );
            
            if (filtrados.length === 0) {
                tbody.html(`<tr><td colspan="6"><div class="empty-state"><i class="bi bi-people"></i><h5>Nenhum responsável encontrado</h5></div></td></tr>`);
                return;
            }
            
            let html = '';
            filtrados.forEach(r => {
                const iniciais = r.usuario_nome.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
                html += `
                    <tr class="${!r.ativo ? 'table-secondary' : ''}">
                        <td><div class="user-avatar">${iniciais}</div></td>
                        <td><strong>${r.usuario_nome}</strong></td>
                        <td>${r.departamento_nome}</td>
                        <td class="text-center">
                            <span class="badge ${r.tipo === 'responsavel' ? 'badge-responsavel' : 'badge-auxiliar'}">
                                ${r.tipo === 'responsavel' ? 'Responsável' : 'Auxiliar'}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge ${r.ativo ? 'bg-success' : 'badge-inativo'}">
                                ${r.ativo ? 'Ativo' : 'Inativo'}
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-outline-primary btn-action me-1" onclick="editarResponsavel(${r.id})"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-outline-danger btn-action" onclick="excluirResponsavel(${r.id})"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                `;
            });
            tbody.html(html);
        }
        
        function abrirModalNovo() {
            $('#formResponsavel')[0].reset();
            $('#resp_id').val('');
            $('#resp_ativo').prop('checked', true);
            new bootstrap.Modal('#modalResponsavel').show();
        }
        
        function editarResponsavel(id) {
            const r = responsaveis.find(x => x.id === id);
            if (!r) return;
            
            $('#resp_id').val(r.id);
            $('#resp_departamento').val(r.id_departamento);
            $('#resp_usuario').val(r.id_usuario);
            $('#resp_tipo').val(r.tipo);
            $('#resp_ativo').prop('checked', r.ativo);
            
            new bootstrap.Modal('#modalResponsavel').show();
        }
        
        function salvarResponsavel() {
            const departamento = $('#resp_departamento').val();
            const usuario = $('#resp_usuario').val();
            
            if (!departamento || !usuario) {
                exibirToast('Departamento e Usuário são obrigatórios', 'warning');
                return;
            }
            
            $.post(baseUrl + '/api/estoque/responsaveis/salvar.php', {
                id: $('#resp_id').val(),
                id_departamento: departamento,
                id_usuario: usuario,
                tipo: $('#resp_tipo').val(),
                ativo: $('#resp_ativo').is(':checked') ? 1 : 0
            }, function(data) {
                if (data.status === 'ok') {
                    bootstrap.Modal.getInstance('#modalResponsavel').hide();
                    carregarResponsaveis();
                    exibirToast(data.mensagem, 'success');
                } else {
                    exibirToast(data.mensagem, 'danger');
                }
            }, 'json');
        }
        
        function excluirResponsavel(id) {
            if (!confirm('Remover este responsável?')) return;
            
            $.post(baseUrl + '/api/estoque/responsaveis/excluir.php', { id: id }, function(data) {
                if (data.status === 'ok') {
                    carregarResponsaveis();
                    exibirToast(data.mensagem, 'success');
                } else {
                    exibirToast(data.mensagem, 'danger');
                }
            }, 'json');
        }
    </script>
</body>
</html>

