<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('estoque_config_departamentos');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Departamentos - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        .header-page {
            background: var(--primary-gradient);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .card-main {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-responsive {
            border-radius: 0 0 16px 16px;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .dept-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }
        
        .badge-ativo { background: #38a169; }
        .badge-inativo { background: #e53e3e; }
        
        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
        
        .empty-state {
            padding: 3rem;
            text-align: center;
            color: #718096;
        }
        
        .empty-state i {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }
        
        .color-preview {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            border: 2px solid #e2e8f0;
        }
        
        .icon-selector {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 8px;
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .icon-option {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
        }
        
        .icon-option:hover {
            background: #e2e8f0;
        }
        
        .icon-option.selected {
            background: #667eea;
            color: white;
            border-color: #5a67d8;
        }
        
        @media (max-width: 768px) {
            .hide-mobile { display: none !important; }
            .table td, .table th { font-size: 0.85rem; padding: 0.5rem; }
        }
    </style>
</head>
<body>
    <div class="header-page">
        <div class="container-fluid px-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <a href="../dashboard.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div>
                        <h5 class="mb-0"><i class="bi bi-building me-2"></i>Departamentos</h5>
                        <small class="opacity-75">Gerenciamento de setores</small>
                    </div>
                </div>
                <button class="btn btn-light btn-sm" onclick="abrirModalNovo()">
                    <i class="bi bi-plus-lg me-1"></i>
                    <span class="hide-mobile">Novo Departamento</span>
                </button>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <div class="card-main">
            <div class="card-header bg-white border-0 py-3 px-4">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="busca" placeholder="Buscar departamento...">
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="mostrar-inativos">
                            <label class="form-check-label small" for="mostrar-inativos">Mostrar inativos</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 60px;"></th>
                            <th>Nome</th>
                            <th class="hide-mobile">Código</th>
                            <th class="text-center">Produtos</th>
                            <th class="text-center">Status</th>
                            <th style="width: 120px;" class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabela-departamentos">
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Cadastro/Edição -->
    <div class="modal fade" id="modalDepartamento" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                    <h5 class="modal-title" id="modalTitulo">
                        <i class="bi bi-building me-2"></i>Novo Departamento
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formDepartamento">
                        <input type="hidden" id="dept_id" name="id">
                        
                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dept_nome" name="nome" required maxlength="100">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control" id="dept_codigo" name="codigo" maxlength="20" placeholder="Ex: ADM">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Cor</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="dept_cor" name="cor" value="#667eea">
                                    <input type="text" class="form-control" id="dept_cor_texto" value="#667eea" maxlength="7">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ícone</label>
                            <input type="hidden" id="dept_icone" name="icone" value="bi-box">
                            <div class="icon-selector" id="seletorIcones"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" id="dept_descricao" name="descricao" rows="2"></textarea>
                        </div>
                        
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="dept_ativo" name="ativo" value="1" checked>
                            <label class="form-check-label" for="dept_ativo">Departamento ativo</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarDepartamento()">
                        <i class="bi bi-check-lg me-1"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Confirmação Exclusão -->
    <div class="modal fade" id="modalExcluir" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title"><i class="bi bi-trash me-2"></i>Excluir Departamento</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <p>Deseja realmente excluir o departamento <strong id="excluir-nome"></strong>?</p>
                    <small class="text-muted">Se houver dados vinculados, o departamento será apenas desativado.</small>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmarExclusao()">
                        <i class="bi bi-trash me-1"></i>Excluir
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
        let departamentos = [];
        let excluirId = null;
        
        const icones = [
            'bi-box', 'bi-building', 'bi-house', 'bi-shop', 'bi-hospital', 'bi-bank',
            'bi-gear', 'bi-tools', 'bi-wrench', 'bi-hammer', 'bi-brush',
            'bi-truck', 'bi-car-front', 'bi-bicycle', 'bi-bus-front',
            'bi-cup-hot', 'bi-basket', 'bi-cart', 'bi-bag', 'bi-gift',
            'bi-book', 'bi-journal', 'bi-file-text', 'bi-folder', 'bi-archive',
            'bi-people', 'bi-person', 'bi-person-badge', 'bi-person-gear',
            'bi-calendar', 'bi-clock', 'bi-alarm', 'bi-stopwatch',
            'bi-camera', 'bi-printer', 'bi-laptop', 'bi-phone', 'bi-tv',
            'bi-heart', 'bi-star', 'bi-flag', 'bi-trophy', 'bi-award',
            'bi-lightning', 'bi-water', 'bi-fire', 'bi-snow', 'bi-sun',
            'bi-tree', 'bi-flower1', 'bi-bug', 'bi-virus',
            'bi-shield', 'bi-lock', 'bi-key', 'bi-eye'
        ];
        
        $(document).ready(function() {
            carregarDepartamentos();
            renderizarIcones();
            
            $('#busca').on('input', filtrarTabela);
            $('#mostrar-inativos').change(carregarDepartamentos);
            
            // Sincronizar cor
            $('#dept_cor').on('input', function() {
                $('#dept_cor_texto').val($(this).val());
            });
            $('#dept_cor_texto').on('input', function() {
                $('#dept_cor').val($(this).val());
            });
        });
        
        function renderizarIcones() {
            let html = '';
            icones.forEach(icone => {
                html += `<div class="icon-option" data-icone="${icone}" onclick="selecionarIcone('${icone}')">
                    <i class="bi ${icone}"></i>
                </div>`;
            });
            $('#seletorIcones').html(html);
        }
        
        function selecionarIcone(icone) {
            $('.icon-option').removeClass('selected');
            $(`.icon-option[data-icone="${icone}"]`).addClass('selected');
            $('#dept_icone').val(icone);
        }
        
        function carregarDepartamentos() {
            const mostrarInativos = $('#mostrar-inativos').is(':checked');
            
            $.ajax({
                url: baseUrl + '/api/estoque/departamentos/listar.php',
                data: { todos: mostrarInativos ? 'true' : 'false' },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        departamentos = data.departamentos;
                        renderizarTabela();
                    }
                }
            });
        }
        
        function renderizarTabela() {
            const tbody = $('#tabela-departamentos');
            const busca = $('#busca').val().toLowerCase();
            
            const filtrados = departamentos.filter(d => 
                d.nome.toLowerCase().includes(busca) || 
                (d.codigo && d.codigo.toLowerCase().includes(busca))
            );
            
            if (filtrados.length === 0) {
                tbody.html(`
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-building"></i>
                                <h5>Nenhum departamento encontrado</h5>
                                <p>Clique em "Novo Departamento" para criar</p>
                            </div>
                        </td>
                    </tr>
                `);
                return;
            }
            
            let html = '';
            filtrados.forEach(d => {
                html += `
                    <tr class="${!d.ativo ? 'table-secondary' : ''}">
                        <td>
                            <div class="dept-icon" style="background: ${d.cor};">
                                <i class="bi ${d.icone}"></i>
                            </div>
                        </td>
                        <td>
                            <strong>${d.nome}</strong>
                            ${d.descricao ? `<br><small class="text-muted">${d.descricao}</small>` : ''}
                        </td>
                        <td class="hide-mobile">
                            <span class="badge bg-secondary">${d.codigo || '-'}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary">${d.total_produtos}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge ${d.ativo ? 'badge-ativo' : 'badge-inativo'}">
                                ${d.ativo ? 'Ativo' : 'Inativo'}
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-outline-primary btn-action me-1" onclick="editarDepartamento(${d.id})" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-action" onclick="excluirDepartamento(${d.id}, '${d.nome}')" title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.html(html);
        }
        
        function filtrarTabela() {
            renderizarTabela();
        }
        
        function abrirModalNovo() {
            $('#formDepartamento')[0].reset();
            $('#dept_id').val('');
            $('#dept_cor').val('#667eea');
            $('#dept_cor_texto').val('#667eea');
            $('#dept_ativo').prop('checked', true);
            selecionarIcone('bi-box');
            $('#modalTitulo').html('<i class="bi bi-building me-2"></i>Novo Departamento');
            new bootstrap.Modal('#modalDepartamento').show();
        }
        
        function editarDepartamento(id) {
            const dept = departamentos.find(d => d.id === id);
            if (!dept) return;
            
            $('#dept_id').val(dept.id);
            $('#dept_nome').val(dept.nome);
            $('#dept_codigo').val(dept.codigo);
            $('#dept_cor').val(dept.cor);
            $('#dept_cor_texto').val(dept.cor);
            $('#dept_descricao').val(dept.descricao);
            $('#dept_ativo').prop('checked', dept.ativo);
            selecionarIcone(dept.icone);
            
            $('#modalTitulo').html('<i class="bi bi-pencil me-2"></i>Editar Departamento');
            new bootstrap.Modal('#modalDepartamento').show();
        }
        
        function salvarDepartamento() {
            const form = $('#formDepartamento');
            const nome = $('#dept_nome').val().trim();
            
            if (!nome) {
                exibirToast('Nome é obrigatório', 'warning');
                return;
            }
            
            const dados = {
                id: $('#dept_id').val(),
                nome: nome,
                codigo: $('#dept_codigo').val().trim(),
                cor: $('#dept_cor').val(),
                icone: $('#dept_icone').val(),
                descricao: $('#dept_descricao').val().trim(),
                ativo: $('#dept_ativo').is(':checked') ? 1 : 0
            };
            
            $.ajax({
                url: baseUrl + '/api/estoque/departamentos/salvar.php',
                method: 'POST',
                data: dados,
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        bootstrap.Modal.getInstance('#modalDepartamento').hide();
                        carregarDepartamentos();
                        exibirToast(data.mensagem, 'success');
                    } else {
                        exibirToast(data.mensagem, 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro ao salvar departamento', 'danger');
                }
            });
        }
        
        function excluirDepartamento(id, nome) {
            excluirId = id;
            $('#excluir-nome').text(nome);
            new bootstrap.Modal('#modalExcluir').show();
        }
        
        function confirmarExclusao() {
            if (!excluirId) return;
            
            $.ajax({
                url: baseUrl + '/api/estoque/departamentos/excluir.php',
                method: 'POST',
                data: { id: excluirId },
                dataType: 'json',
                success: function(data) {
                    bootstrap.Modal.getInstance('#modalExcluir').hide();
                    if (data.status === 'ok') {
                        carregarDepartamentos();
                        exibirToast(data.mensagem, 'success');
                    } else {
                        exibirToast(data.mensagem, 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro ao excluir departamento', 'danger');
                }
            });
        }
    </script>
</body>
</html>

