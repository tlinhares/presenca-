<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('estoque_inventario');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$inventarioId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($inventarioId <= 0) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Contagem de Inventário - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(250, 112, 154, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .produto-item { background: #f8f9fa; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; border-left: 4px solid #4299e1; }
        .produto-item.contado { border-left-color: #48bb78; }
        .produto-item.diferenca { border-left-color: #e53e3e; }
        .quantidade-input { max-width: 150px; }
        .diferenca-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .diferenca-badge.positiva { background: #c6f6d5; color: #22543d; }
        .diferenca-badge.negativa { background: #fed7d7; color: #742a2a; }
        .diferenca-badge.zero { background: #e2e8f0; color: #4a5568; }
        .resumo-card { background: var(--primary-gradient); color: white; border-radius: 12px; padding: 1.5rem; position: sticky; top: 80px; }
        @media (max-width: 768px) { .hide-mobile { display: none !important; } }
    </style>
</head>
<body>
    <div class="header-page">
        <div class="container-fluid px-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h5 class="mb-0"><i class="bi bi-ui-checks me-2"></i>Contagem de Inventário</h5>
                        <small class="opacity-75" id="header-info">Carregando...</small>
                    </div>
                </div>
                <button class="btn btn-light btn-sm" onclick="finalizarInventario()" id="btn-finalizar" disabled>
                    <i class="bi bi-check-lg me-1"></i><span class="hide-mobile">Finalizar</span>
                </button>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <div class="row">
            <div class="col-lg-8">
                <div class="card-main p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>Produtos para Contagem</h6>
                        <div class="input-group" style="max-width: 300px;">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="busca-produto" placeholder="Buscar produto...">
                        </div>
                    </div>
                    
                    <div id="lista-produtos">
                        <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="resumo-card mb-4">
                    <h6 class="mb-3"><i class="bi bi-clipboard-check me-2"></i>Resumo</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total de produtos:</span>
                        <strong id="total-produtos">0</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Contados:</span>
                        <strong id="total-contados">0</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Com diferença:</span>
                        <strong id="total-diferencas">0</strong>
                    </div>
                    <hr style="border-color: rgba(255,255,255,0.3);">
                    <div class="small opacity-75">
                        <i class="bi bi-info-circle me-1"></i>
                        Informe a quantidade física encontrada para cada produto
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Confirmação Finalização -->
    <div class="modal fade" id="modalFinalizar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Finalizar Inventário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Deseja finalizar este inventário?</p>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Após finalizar, não será possível editar as contagens.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" id="btn-confirmar-finalizar">
                        <i class="bi bi-check-lg me-2"></i>Finalizar
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
        const inventarioId = <?= $inventarioId ?>;
        let produtos = [];
        let itensContados = {};
        
        $(document).ready(function() {
            carregarInventario();
            carregarProdutos();
            
            $('#busca-produto').on('input', function() {
                filtrarProdutos();
            });
        });
        
        function carregarInventario() {
            $.getJSON(baseUrl + '/api/estoque/inventarios/buscar.php', { id: inventarioId }, function(data) {
                if (data.status === 'ok') {
                    const inv = data.inventario;
                    $('#header-info').text(`#${inv.id} - ${inv.departamento_nome}`);
                    
                    // Carregar itens já contados
                    if (inv.itens) {
                        inv.itens.forEach(item => {
                            itensContados[item.id_produto] = {
                                quantidade_contada: parseFloat(item.quantidade_contada) || null,
                                diferenca: parseFloat(item.diferenca) || 0
                            };
                        });
                    }
                }
            });
        }
        
        function carregarProdutos() {
            $.getJSON(baseUrl + '/api/estoque/inventarios/produtos.php', { id: inventarioId }, function(data) {
                if (data.status === 'ok') {
                    produtos = data.produtos;
                    renderizarProdutos();
                } else {
                    exibirToast(data.mensagem || 'Erro ao carregar produtos', 'danger');
                }
            }).fail(function() {
                exibirToast('Erro ao carregar produtos', 'danger');
            });
        }
        
        function filtrarProdutos() {
            renderizarProdutos();
        }
        
        function renderizarProdutos() {
            const busca = $('#busca-produto').val().toLowerCase();
            const container = $('#lista-produtos');
            
            let produtosFiltrados = produtos;
            if (busca) {
                produtosFiltrados = produtos.filter(p => 
                    p.nome.toLowerCase().includes(busca) ||
                    (p.codigo && p.codigo.toLowerCase().includes(busca))
                );
            }
            
            if (produtosFiltrados.length === 0) {
                container.html(`
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-search fs-1 d-block mb-3"></i>
                        <p>Nenhum produto encontrado</p>
                    </div>
                `);
                atualizarResumo();
                return;
            }
            
            let html = '';
            produtosFiltrados.forEach(produto => {
                const contado = itensContados[produto.id];
                const quantidadeSistema = parseFloat(produto.quantidade_atual) || 0;
                const quantidadeContada = contado ? (contado.quantidade_contada !== null ? contado.quantidade_contada : '') : '';
                const diferenca = contado ? contado.diferenca : 0;
                
                let classeItem = '';
                let badgeDiferenca = '';
                
                if (quantidadeContada !== '') {
                    classeItem = diferenca === 0 ? 'contado' : 'diferenca';
                    const classeBadge = diferenca > 0 ? 'positiva' : (diferenca < 0 ? 'negativa' : 'zero');
                    const sinal = diferenca > 0 ? '+' : '';
                    badgeDiferenca = `<span class="diferenca-badge ${classeBadge} ms-2">${sinal}${diferenca.toFixed(2)}</span>`;
                }
                
                html += `
                    <div class="produto-item ${classeItem}">
                        <div class="row align-items-center">
                            <div class="col-md-6 mb-2 mb-md-0">
                                <strong>${produto.nome}</strong>
                                <div class="small text-muted">
                                    ${produto.codigo ? 'Código: ' + produto.codigo + ' | ' : ''}
                                    Sistema: <strong>${quantidadeSistema.toFixed(2)}</strong> ${produto.unidade_sigla}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Quantidade Contada</label>
                                <div class="input-group">
                                    <input type="number" 
                                           class="form-control quantidade-input" 
                                           step="0.01" 
                                           min="0"
                                           value="${quantidadeContada}"
                                           data-produto-id="${produto.id}"
                                           data-quantidade-sistema="${quantidadeSistema}"
                                           onchange="registrarContagem(${produto.id}, this.value)">
                                    <span class="input-group-text">${produto.unidade_sigla}</span>
                                </div>
                            </div>
                            <div class="col-md-2 text-end">
                                ${badgeDiferenca}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.html(html);
            atualizarResumo();
        }
        
        function registrarContagem(produtoId, quantidade) {
            const quantidadeContada = parseFloat(quantidade) || 0;
            const produto = produtos.find(p => p.id === produtoId);
            const quantidadeSistema = parseFloat(produto.quantidade_atual) || 0;
            const diferenca = quantidadeContada - quantidadeSistema;
            
            itensContados[produtoId] = {
                quantidade_contada: quantidadeContada,
                diferenca: diferenca
            };
            
            // Salvar no servidor
            $.post(baseUrl + '/api/estoque/inventarios/registrar_contagem.php', {
                id_inventario: inventarioId,
                id_produto: produtoId,
                quantidade_sistema: quantidadeSistema,
                quantidade_contada: quantidadeContada,
                diferenca: diferenca
            }, function(data) {
                if (data.status !== 'ok') {
                    exibirToast('Erro ao salvar contagem', 'warning');
                }
            }, 'json');
            
            renderizarProdutos();
        }
        
        function atualizarResumo() {
            const total = produtos.length;
            const contados = Object.keys(itensContados).filter(id => {
                const item = itensContados[id];
                return item && item.quantidade_contada !== null && item.quantidade_contada !== '';
            }).length;
            const comDiferenca = Object.keys(itensContados).filter(id => {
                const item = itensContados[id];
                return item && item.diferenca !== 0;
            }).length;
            
            $('#total-produtos').text(total);
            $('#total-contados').text(contados);
            $('#total-diferencas').text(comDiferenca);
            
            // Habilitar botão finalizar se todos foram contados
            if (contados === total && total > 0) {
                $('#btn-finalizar').prop('disabled', false);
            }
        }
        
        function finalizarInventario() {
            const modal = new bootstrap.Modal(document.getElementById('modalFinalizar'));
            modal.show();
        }
        
        $('#btn-confirmar-finalizar').click(function() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalFinalizar'));
            modal.hide();
            
            processarFinalizacao();
        });
        
        function processarFinalizacao() {
            $('#btn-finalizar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Finalizando...');
            
            $.post(baseUrl + '/api/estoque/inventarios/finalizar.php', {
                id: inventarioId
            }, function(data) {
                if (data.status === 'ok') {
                    exibirToast('Inventário finalizado com sucesso!', 'success');
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1500);
                } else {
                    exibirToast(data.mensagem || 'Erro ao finalizar inventário', 'danger');
                    $('#btn-finalizar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i><span class="hide-mobile">Finalizar</span>');
                }
            }, 'json').fail(function() {
                exibirToast('Erro ao finalizar inventário', 'danger');
                $('#btn-finalizar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i><span class="hide-mobile">Finalizar</span>');
            });
        }
    </script>
</body>
</html>

