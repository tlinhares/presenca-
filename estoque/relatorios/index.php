<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('estoque_relatorios');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Relatórios - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #434343 0%, #000000 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(0,0,0,0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .report-card { background: white; border-radius: 16px; padding: 1.5rem; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.08); transition: all 0.3s; cursor: pointer; height: 100%; }
        .report-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .report-card .icon-wrapper { width: 64px; height: 64px; border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.75rem; color: white; }
        .report-card h6 { font-weight: 600; margin-bottom: 0.5rem; }
        .report-card p { color: #718096; font-size: 0.875rem; margin: 0; }
    </style>
</head>
<body>
    <div class="header-page">
        <div class="container-fluid px-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <a href="../dashboard.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h5 class="mb-0"><i class="bi bi-file-earmark-bar-graph me-2"></i>Relatórios</h5>
                        <small class="opacity-75">Análises e exportações</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <div class="row g-4">
            <!-- Posição de Estoque -->
            <div class="col-md-4 col-lg-3">
                <div class="report-card" onclick="gerarRelatorio('posicao_estoque')">
                    <div class="icon-wrapper" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <h6>Posição de Estoque</h6>
                    <p>Quantidade atual de todos os produtos</p>
                </div>
            </div>
            
            <!-- Estoque Baixo -->
            <div class="col-md-4 col-lg-3">
                <div class="report-card" onclick="gerarRelatorio('estoque_baixo')">
                    <div class="icon-wrapper" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <h6>Estoque Baixo</h6>
                    <p>Produtos abaixo do mínimo</p>
                </div>
            </div>
            
            <!-- Movimentações -->
            <div class="col-md-4 col-lg-3">
                <div class="report-card" onclick="gerarRelatorio('movimentacoes')">
                    <div class="icon-wrapper" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <i class="bi bi-arrow-left-right"></i>
                    </div>
                    <h6>Movimentações</h6>
                    <p>Entradas e saídas por período</p>
                </div>
            </div>
            
            <!-- Requisições -->
            <div class="col-md-4 col-lg-3">
                <div class="report-card" onclick="gerarRelatorio('requisicoes')">
                    <div class="icon-wrapper" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <h6>Requisições</h6>
                    <p>Solicitações por departamento</p>
                </div>
            </div>
            
            <!-- Por Categoria -->
            <div class="col-md-4 col-lg-3">
                <div class="report-card" onclick="gerarRelatorio('por_categoria')">
                    <div class="icon-wrapper" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <i class="bi bi-tags"></i>
                    </div>
                    <h6>Por Categoria</h6>
                    <p>Produtos agrupados por categoria</p>
                </div>
            </div>
            
            <!-- Por Departamento -->
            <div class="col-md-4 col-lg-3">
                <div class="report-card" onclick="gerarRelatorio('por_departamento')">
                    <div class="icon-wrapper" style="background: linear-gradient(135deg, #5ee7df 0%, #b490ca 100%);">
                        <i class="bi bi-building"></i>
                    </div>
                    <h6>Por Departamento</h6>
                    <p>Estoque por setor</p>
                </div>
            </div>
            
            <!-- Valorização -->
            <div class="col-md-4 col-lg-3">
                <div class="report-card" onclick="gerarRelatorio('valorizacao')">
                    <div class="icon-wrapper" style="background: linear-gradient(135deg, #f5af19 0%, #f12711 100%);">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                    <h6>Valorização</h6>
                    <p>Valor total do estoque</p>
                </div>
            </div>
            
            <!-- Curva ABC -->
            <div class="col-md-4 col-lg-3">
                <div class="report-card" onclick="gerarRelatorio('curva_abc')">
                    <div class="icon-wrapper" style="background: linear-gradient(135deg, #434343 0%, #000000 100%);">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <h6>Curva ABC</h6>
                    <p>Análise de itens por valor</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Filtros -->
    <div class="modal fade" id="modalFiltros" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                    <h5 class="modal-title"><i class="bi bi-funnel me-2"></i>Filtros do Relatório</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="tipo-relatorio">
                    
                    <div class="mb-3" id="filtro-periodo">
                        <label class="form-label">Período</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="date" class="form-control" id="data-inicio">
                            </div>
                            <div class="col-6">
                                <input type="date" class="form-control" id="data-fim">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Departamento</label>
                        <select class="form-select" id="filtro-departamento">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Categoria</label>
                        <select class="form-select" id="filtro-categoria">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        O relatório será gerado em formato PDF
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="executarRelatorio()">
                        <i class="bi bi-file-earmark-text me-1"></i>Gerar Relatório
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
            carregarFiltros();
            
            // Datas padrão
            const hoje = new Date();
            const mesPassado = new Date();
            mesPassado.setMonth(mesPassado.getMonth() - 1);
            $('#data-fim').val(hoje.toISOString().split('T')[0]);
            $('#data-inicio').val(mesPassado.toISOString().split('T')[0]);
        });
        
        function carregarFiltros() {
            $.getJSON(baseUrl + '/api/estoque/departamentos/listar.php', function(data) {
                if (data.status === 'ok') {
                    let html = '<option value="">Todos</option>';
                    data.departamentos.forEach(d => html += `<option value="${d.id}">${d.nome}</option>`);
                    $('#filtro-departamento').html(html);
                }
            });
            
            $.getJSON(baseUrl + '/api/estoque/categorias/listar.php', function(data) {
                if (data.status === 'ok') {
                    let html = '<option value="">Todas</option>';
                    data.categorias.forEach(c => html += `<option value="${c.id}">${c.nome}</option>`);
                    $('#filtro-categoria').html(html);
                }
            });
        }
        
        function gerarRelatorio(tipo) {
            $('#tipo-relatorio').val(tipo);
            
            // Mostrar/ocultar filtro de período conforme relatório
            const relatoriosComPeriodo = ['movimentacoes', 'requisicoes'];
            $('#filtro-periodo').toggle(relatoriosComPeriodo.includes(tipo));
            
            new bootstrap.Modal('#modalFiltros').show();
        }
        
        function executarRelatorio() {
            const tipo = $('#tipo-relatorio').val();
            
            const params = new URLSearchParams({
                tipo: tipo,
                formato: 'pdf', // Sempre PDF
                data_inicio: $('#data-inicio').val(),
                data_fim: $('#data-fim').val(),
                departamento: $('#filtro-departamento').val(),
                categoria: $('#filtro-categoria').val()
            });
            
            const url = baseUrl + '/api/estoque/relatorios/gerar.php?' + params.toString();
            
            // Sempre abre em nova aba (PDF será gerado)
            window.open(url, '_blank');
            
            bootstrap.Modal.getInstance('#modalFiltros').hide();
            exibirToast('Gerando relatório em PDF...', 'info');
        }
    </script>
</body>
</html>



