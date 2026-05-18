<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('estoque_config_categorias');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Categorias - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(240, 147, 251, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; }
        .table th { background: #f8f9fa; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: #4a5568; border-bottom: 2px solid #e2e8f0; }
        .table td { vertical-align: middle; }
        .cat-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1rem; }
        .badge-ativo { background: #38a169; }
        .badge-inativo { background: #e53e3e; }
        .btn-action { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; }
        .subcategoria { padding-left: 2rem; }
        .subcategoria::before { content: '↳ '; color: #a0aec0; }
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
                        <h5 class="mb-0"><i class="bi bi-tags me-2"></i>Categorias</h5>
                        <small class="opacity-75">Classificação de produtos</small>
                    </div>
                </div>
                <button class="btn btn-light btn-sm" onclick="abrirModalNovo()">
                    <i class="bi bi-plus-lg me-1"></i><span class="hide-mobile">Nova Categoria</span>
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
                            <input type="text" class="form-control" id="busca" placeholder="Buscar categoria...">
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
                            <th class="hide-mobile">Categoria Pai</th>
                            <th class="text-center">Produtos</th>
                            <th class="text-center">Status</th>
                            <th style="width: 120px;" class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabela-categorias">
                        <tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modalCategoria" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                    <h5 class="modal-title" id="modalTitulo"><i class="bi bi-tags me-2"></i>Nova Categoria</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formCategoria">
                        <input type="hidden" id="cat_id" name="id">
                        
                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cat_nome" name="nome" required maxlength="100">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Categoria Pai</label>
                            <select class="form-select" id="cat_pai" name="id_categoria_pai">
                                <option value="">Nenhuma (Categoria principal)</option>
                            </select>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Cor</label>
                                <input type="color" class="form-control form-control-color w-100" id="cat_cor" name="cor" value="#6c757d">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Ordem</label>
                                <input type="number" class="form-control" id="cat_ordem" name="ordem" value="0" min="0">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" id="cat_descricao" name="descricao" rows="2"></textarea>
                        </div>
                        
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="cat_ativo" name="ativo" checked>
                            <label class="form-check-label" for="cat_ativo">Categoria ativa</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarCategoria()">
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
        let categorias = [];
        
        $(document).ready(function() {
            carregarCategorias();
            $('#busca').on('input', renderizarTabela);
        });
        
        function carregarCategorias() {
            $.ajax({
                url: baseUrl + '/api/estoque/categorias/listar.php',
                data: { todos: 'true' },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        categorias = data.categorias;
                        renderizarTabela();
                        atualizarSelectPais();
                    }
                }
            });
        }
        
        function renderizarTabela() {
            const tbody = $('#tabela-categorias');
            const busca = $('#busca').val().toLowerCase();
            
            const filtrados = categorias.filter(c => c.nome.toLowerCase().includes(busca));
            
            if (filtrados.length === 0) {
                tbody.html('<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-tags fs-1 d-block mb-2"></i>Nenhuma categoria encontrada</td></tr>');
                return;
            }
            
            let html = '';
            filtrados.forEach(c => {
                html += `
                    <tr class="${!c.ativo ? 'table-secondary' : ''}">
                        <td><div class="cat-icon" style="background: ${c.cor};"><i class="bi ${c.icone}"></i></div></td>
                        <td class="${c.id_categoria_pai ? 'subcategoria' : ''}"><strong>${c.nome}</strong></td>
                        <td class="hide-mobile">${c.categoria_pai_nome || '-'}</td>
                        <td class="text-center"><span class="badge bg-primary">${c.total_produtos}</span></td>
                        <td class="text-center"><span class="badge ${c.ativo ? 'badge-ativo' : 'badge-inativo'}">${c.ativo ? 'Ativo' : 'Inativo'}</span></td>
                        <td class="text-center">
                            <button class="btn btn-outline-primary btn-action me-1" onclick="editarCategoria(${c.id})"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-outline-danger btn-action" onclick="excluirCategoria(${c.id})"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                `;
            });
            tbody.html(html);
        }
        
        function atualizarSelectPais() {
            const select = $('#cat_pai');
            select.find('option:not(:first)').remove();
            categorias.filter(c => !c.id_categoria_pai && c.ativo).forEach(c => {
                select.append(`<option value="${c.id}">${c.nome}</option>`);
            });
        }
        
        function abrirModalNovo() {
            $('#formCategoria')[0].reset();
            $('#cat_id').val('');
            $('#cat_ativo').prop('checked', true);
            $('#modalTitulo').html('<i class="bi bi-tags me-2"></i>Nova Categoria');
            new bootstrap.Modal('#modalCategoria').show();
        }
        
        function editarCategoria(id) {
            const cat = categorias.find(c => c.id === id);
            if (!cat) return;
            
            $('#cat_id').val(cat.id);
            $('#cat_nome').val(cat.nome);
            $('#cat_pai').val(cat.id_categoria_pai || '');
            $('#cat_cor').val(cat.cor);
            $('#cat_ordem').val(cat.ordem);
            $('#cat_descricao').val(cat.descricao);
            $('#cat_ativo').prop('checked', cat.ativo);
            
            $('#modalTitulo').html('<i class="bi bi-pencil me-2"></i>Editar Categoria');
            new bootstrap.Modal('#modalCategoria').show();
        }
        
        function salvarCategoria() {
            const nome = $('#cat_nome').val().trim();
            if (!nome) { exibirToast('Nome é obrigatório', 'warning'); return; }
            
            $.ajax({
                url: baseUrl + '/api/estoque/categorias/salvar.php',
                method: 'POST',
                data: {
                    id: $('#cat_id').val(),
                    nome: nome,
                    id_categoria_pai: $('#cat_pai').val(),
                    cor: $('#cat_cor').val(),
                    ordem: $('#cat_ordem').val(),
                    descricao: $('#cat_descricao').val(),
                    ativo: $('#cat_ativo').is(':checked') ? 1 : 0
                },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        bootstrap.Modal.getInstance('#modalCategoria').hide();
                        carregarCategorias();
                        exibirToast(data.mensagem, 'success');
                    } else {
                        exibirToast(data.mensagem, 'danger');
                    }
                }
            });
        }
        
        function excluirCategoria(id) {
            if (!confirm('Deseja excluir esta categoria?')) return;
            
            $.ajax({
                url: baseUrl + '/api/estoque/categorias/excluir.php',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        carregarCategorias();
                        exibirToast(data.mensagem, 'success');
                    } else {
                        exibirToast(data.mensagem, 'danger');
                    }
                }
            });
        }
    </script>
</body>
</html>

