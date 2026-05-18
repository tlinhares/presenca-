<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('estoque_produtos');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$departamentoFiltro = isset($_GET['departamento']) ? intval($_GET['departamento']) : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Produtos - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        
        .produto-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.2s;
            cursor: pointer;
        }
        .produto-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        
        .estoque-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .estoque-badge.normal { background: #c6f6d5; color: #22543d; }
        .estoque-badge.baixo { background: #feebc8; color: #744210; }
        .estoque-badge.critico { background: #fed7d7; color: #822727; }
        .estoque-badge.zerado { background: #e53e3e; color: white; }
        
        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            background: #e2e8f0;
            color: #4a5568;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        .filter-chip:hover, .filter-chip.active {
            background: #667eea;
            color: white;
        }
        
        .empty-state { text-align: center; padding: 3rem; color: #718096; }
        .empty-state i { font-size: 4rem; opacity: 0.3; }
        
        @media (max-width: 768px) {
            .hide-mobile { display: none !important; }
            .produto-card { padding: 0.75rem; }
        }
    </style>
</head>
<body>
    <div class="header-page">
        <div class="container-fluid px-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <a href="../dashboard.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Produtos</h5>
                        <small class="opacity-75" id="subtitulo">Catálogo de materiais</small>
                    </div>
                </div>
                <button class="btn btn-light btn-sm" onclick="abrirModalProduto()">
                    <i class="bi bi-plus-lg me-1"></i><span class="hide-mobile">Novo Produto</span>
                </button>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <!-- Filtros -->
        <div class="card-main p-3 mb-4">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="busca" placeholder="Buscar produto...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filtro-departamento">
                        <option value="">Todos os departamentos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filtro-categoria">
                        <option value="">Todas as categorias</option>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <div class="btn-group" role="group">
                        <button class="filter-chip active" data-filtro="">Todos</button>
                        <button class="filter-chip" data-filtro="estoque_baixo">
                            <i class="bi bi-exclamation-triangle"></i>Baixo
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Produtos -->
        <div id="lista-produtos">
            <div class="text-center py-5">
                <div class="spinner-border text-primary"></div>
            </div>
        </div>
    </div>

    <!-- Modal Produto -->
    <div class="modal fade" id="modalProduto" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                    <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Novo Produto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formProduto">
                        <input type="hidden" id="prod_id">
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control" id="prod_codigo" maxlength="50">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Código de Barras</label>
                                <input type="text" class="form-control" id="prod_codigo_barras" maxlength="50">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">NCM</label>
                                <input type="text" class="form-control" id="prod_ncm" maxlength="10">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="prod_nome" required maxlength="200">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Departamento <span class="text-danger">*</span></label>
                                <select class="form-select" id="prod_departamento" required></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Categoria</label>
                                <select class="form-select" id="prod_categoria">
                                    <option value="">Sem categoria</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Localização</label>
                                <select class="form-select" id="prod_localizacao">
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Unidade <span class="text-danger">*</span></label>
                                <select class="form-select" id="prod_unidade" required></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Marca</label>
                                <input type="text" class="form-control" id="prod_marca" maxlength="100">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Modelo</label>
                                <input type="text" class="form-control" id="prod_modelo" maxlength="100">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Qtd. Mínima</label>
                                <input type="number" class="form-control" id="prod_qtd_minima" min="0" step="0.01" value="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Qtd. Ideal</label>
                                <input type="number" class="form-control" id="prod_qtd_ideal" min="0" step="0.01" value="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Qtd. Máxima</label>
                                <input type="number" class="form-control" id="prod_qtd_maxima" min="0" step="0.01">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Valor Unit.</label>
                                <input type="number" class="form-control" id="prod_valor" min="0" step="0.01" value="0">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrição/Observações</label>
                            <textarea class="form-control" id="prod_descricao" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarProduto()">
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
        let produtos = [];
        let departamentos = [];
        let categorias = [];
        let unidades = [];
        let localizacoes = [];
        let filtroAtual = '';
        let departamentoInicial = <?= $departamentoFiltro ?>;
        
        $(document).ready(function() {
            carregarDados();
            
            $('#busca').on('input', debounce(carregarProdutos, 300));
            $('#filtro-departamento, #filtro-categoria').change(carregarProdutos);
            
            $('.filter-chip').click(function() {
                $('.filter-chip').removeClass('active');
                $(this).addClass('active');
                filtroAtual = $(this).data('filtro');
                carregarProdutos();
            });
        });
        
        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }
        
        function carregarDados() {
            return Promise.all([
                $.getJSON(baseUrl + '/api/estoque/departamentos/listar.php'),
                $.getJSON(baseUrl + '/api/estoque/categorias/listar.php'),
                $.getJSON(baseUrl + '/api/estoque/unidades/listar.php'),
                $.getJSON(baseUrl + '/api/estoque/localizacoes/listar.php')
            ]).then(function([deptData, catData, unData, locData]) {
                departamentos = deptData.departamentos || [];
                categorias = catData.categorias || [];
                unidades = unData.unidades || [];
                localizacoes = locData.localizacoes || [];
                
                preencherSelects();
                
                if (departamentoInicial > 0) {
                    $('#filtro-departamento').val(departamentoInicial);
                }
                
                carregarProdutos();
                
                return true; // Retornar para permitir encadeamento
            });
        }
        
        function preencherSelects() {
            // Filtro departamento
            let html = '<option value="">Todos os departamentos</option>';
            departamentos.forEach(d => {
                html += `<option value="${d.id}">${d.nome}</option>`;
            });
            $('#filtro-departamento').html(html);
            
            // Filtro categoria
            html = '<option value="">Todas as categorias</option>';
            categorias.forEach(c => {
                html += `<option value="${c.id}">${c.nome}</option>`;
            });
            $('#filtro-categoria').html(html);
            
            // Modal - Departamento
            html = '<option value="">Selecione...</option>';
            departamentos.forEach(d => {
                html += `<option value="${d.id}">${d.nome}</option>`;
            });
            $('#prod_departamento').html(html);
            
            // Modal - Categoria
            html = '<option value="">Sem categoria</option>';
            categorias.forEach(c => {
                html += `<option value="${c.id}">${c.nome}</option>`;
            });
            $('#prod_categoria').html(html);
            
            // Modal - Unidade
            html = '<option value="">Selecione...</option>';
            unidades.forEach(u => {
                html += `<option value="${u.id}">${u.sigla} - ${u.nome}</option>`;
            });
            $('#prod_unidade').html(html);
            
            // Modal - Localização
            html = '<option value="">Selecione...</option>';
            localizacoes.forEach(l => {
                html += `<option value="${l.id}">${l.nome} (${l.departamento_nome})</option>`;
            });
            $('#prod_localizacao').html(html);
        }
        
        function carregarProdutos() {
            const params = {
                busca: $('#busca').val(),
                departamento: $('#filtro-departamento').val(),
                categoria: $('#filtro-categoria').val(),
                filtro: filtroAtual
            };
            
            $.getJSON(baseUrl + '/api/estoque/produtos/listar.php', params, function(data) {
                if (data.status === 'ok') {
                    produtos = data.produtos;
                    renderizarProdutos();
                    $('#subtitulo').text(data.total + ' produto(s)');
                }
            });
        }
        
        function renderizarProdutos() {
            const container = $('#lista-produtos');
            
            if (produtos.length === 0) {
                container.html(`
                    <div class="empty-state">
                        <i class="bi bi-box-seam d-block mb-3"></i>
                        <h5>Nenhum produto encontrado</h5>
                        <p>Clique em "Novo Produto" para cadastrar</p>
                    </div>
                `);
                return;
            }
            
            let html = '<div class="row">';
            produtos.forEach(p => {
                html += `
                    <div class="col-md-6 col-lg-4">
                        <div class="produto-card" onclick="editarProduto(${p.id})">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1">${p.nome}</h6>
                                    <small class="text-muted">${p.codigo || 'Sem código'} • ${p.departamento}</small>
                                </div>
                                <span class="estoque-badge ${p.nivel_estoque}">
                                    ${p.quantidade_atual} ${p.unidade}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    ${p.categoria ? `<span class="badge" style="background: ${p.categoria_cor}; color: white;">${p.categoria}</span>` : ''}
                                    ${p.marca ? `<small class="text-muted ms-2">${p.marca}</small>` : ''}
                                </div>
                                <small class="text-muted">
                                    Min: ${p.quantidade_minima} | Ideal: ${p.quantidade_ideal}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.html(html);
        }
        
        function abrirModalProduto() {
            $('#formProduto')[0].reset();
            $('#prod_id').val('');
            $('.modal-title').html('<i class="bi bi-box-seam me-2"></i>Novo Produto');
            new bootstrap.Modal('#modalProduto').show();
        }
        
        function editarProduto(id) {
            const p = produtos.find(x => x.id === id);
            if (!p) {
                exibirToast('Produto não encontrado', 'warning');
                return;
            }
            
            // Limpar formulário primeiro
            $('#formProduto')[0].reset();
            
            // Preencher campos básicos
            $('#prod_id').val(p.id || '');
            $('#prod_codigo').val(p.codigo || '');
            $('#prod_codigo_barras').val(p.codigo_barras || '');
            $('#prod_nome').val(p.nome || '');
            $('#prod_marca').val(p.marca || '');
            $('#prod_modelo').val(p.modelo || '');
            $('#prod_ncm').val(p.ncm || '');
            $('#prod_qtd_minima').val(p.quantidade_minima || 0);
            $('#prod_qtd_ideal').val(p.quantidade_ideal || 0);
            $('#prod_qtd_maxima').val(p.quantidade_maxima || '');
            $('#prod_valor').val(p.valor_unitario || 0);
            $('#prod_descricao').val(p.descricao || '');
            
            // Garantir que os selects estejam populados
            if (departamentos.length === 0 || categorias.length === 0 || unidades.length === 0) {
                exibirToast('Carregando dados...', 'info');
                carregarDados().then(() => {
                    definirValoresSelects(p);
                    abrirModal();
                });
            } else {
                definirValoresSelects(p);
                abrirModal();
            }
        }
        
        function definirValoresSelects(p) {
            // Converter para string para garantir correspondência
            const deptId = p.id_departamento ? String(p.id_departamento) : '';
            const catId = p.id_categoria ? String(p.id_categoria) : '';
            const unId = p.id_unidade ? String(p.id_unidade) : '';
            const locId = p.id_localizacao ? String(p.id_localizacao) : '';
            
            // Definir valores dos selects
            $('#prod_departamento').val(deptId);
            $('#prod_categoria').val(catId);
            $('#prod_unidade').val(unId);
            $('#prod_localizacao').val(locId);
            
            // Verificar se os valores foram definidos corretamente
            if (deptId && $('#prod_departamento').val() !== deptId) {
                console.warn('Departamento não encontrado:', deptId);
            }
            if (catId && $('#prod_categoria').val() !== catId) {
                console.warn('Categoria não encontrada:', catId);
            }
            if (unId && $('#prod_unidade').val() !== unId) {
                console.warn('Unidade não encontrada:', unId);
            }
        }
        
        function abrirModal() {
            $('.modal-title').html('<i class="bi bi-pencil me-2"></i>Editar Produto');
            const modal = new bootstrap.Modal('#modalProduto');
            modal.show();
        }
        
        function salvarProduto() {
            const nome = $('#prod_nome').val().trim();
            const departamento = $('#prod_departamento').val();
            const unidade = $('#prod_unidade').val();
            
            if (!nome) { exibirToast('Nome é obrigatório', 'warning'); return; }
            if (!departamento) { exibirToast('Departamento é obrigatório', 'warning'); return; }
            if (!unidade) { exibirToast('Unidade é obrigatória', 'warning'); return; }
            
            $.post(baseUrl + '/api/estoque/produtos/salvar.php', {
                id: $('#prod_id').val(),
                codigo: $('#prod_codigo').val(),
                codigo_barras: $('#prod_codigo_barras').val(),
                nome: nome,
                id_departamento: departamento,
                id_categoria: $('#prod_categoria').val(),
                id_localizacao: $('#prod_localizacao').val(),
                id_unidade: unidade,
                marca: $('#prod_marca').val(),
                modelo: $('#prod_modelo').val(),
                ncm: $('#prod_ncm').val(),
                quantidade_minima: $('#prod_qtd_minima').val(),
                quantidade_ideal: $('#prod_qtd_ideal').val(),
                quantidade_maxima: $('#prod_qtd_maxima').val(),
                valor_unitario: $('#prod_valor').val(),
                descricao: $('#prod_descricao').val()
            }, function(data) {
                if (data.status === 'ok') {
                    bootstrap.Modal.getInstance('#modalProduto').hide();
                    carregarProdutos();
                    exibirToast(data.mensagem, 'success');
                } else {
                    exibirToast(data.mensagem, 'danger');
                }
            }, 'json');
        }
    </script>
</body>
</html>

