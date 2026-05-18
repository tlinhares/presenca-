<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('estoque_entrada');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuarioId = $_SESSION['usuario_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Entrada de Produtos - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(17, 153, 142, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        
        .item-entrada {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .item-entrada .btn-remover {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
        }
        
        .produto-selecionado {
            background: #e6fffa;
            border: 2px solid #38ef7d;
            border-radius: 8px;
            padding: 0.5rem 1rem;
        }
        
        .resumo-entrada {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .hide-mobile { display: none !important; }
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
                        <h5 class="mb-0"><i class="bi bi-box-arrow-in-down me-2"></i>Entrada de Produtos</h5>
                        <small class="opacity-75">Adicionar ao estoque</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <div class="row">
            <div class="col-lg-8">
                <div class="card-main p-4 mb-4">
                    <h6 class="mb-3"><i class="bi bi-building me-2"></i>Departamento</h6>
                    <select class="form-select form-select-lg mb-4" id="departamento" required>
                        <option value="">Selecione o departamento...</option>
                    </select>
                    
                    <h6 class="mb-3"><i class="bi bi-box-seam me-2"></i>Produtos</h6>
                    
                    <!-- Busca de Produto -->
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="busca-produto" placeholder="Buscar produto por nome ou código...">
                        <button class="btn btn-outline-secondary" type="button" id="btn-buscar">Buscar</button>
                    </div>
                    
                    <!-- Resultado da Busca -->
                    <div id="resultado-busca" class="mb-3" style="display: none;"></div>
                    
                    <!-- Lista de Itens -->
                    <div id="lista-itens">
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Nenhum produto adicionado
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <label class="form-label">Observações Gerais</label>
                        <textarea class="form-control" id="observacoes" rows="2" placeholder="Observações sobre esta entrada..."></textarea>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="resumo-entrada mb-4">
                    <h5 class="mb-3"><i class="bi bi-calculator me-2"></i>Resumo</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total de itens:</span>
                        <strong id="total-itens">0</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total de produtos:</span>
                        <strong id="total-produtos">0</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Valor total:</span>
                        <strong id="valor-total">R$ 0,00</strong>
                    </div>
                    <button class="btn btn-light btn-lg w-100" onclick="confirmarEntrada()" id="btn-confirmar" disabled>
                        <i class="bi bi-check-lg me-2"></i>Confirmar Entrada
                    </button>
                </div>
                
                <div class="card-main p-3">
                    <h6 class="mb-3"><i class="bi bi-info-circle me-2"></i>Instruções</h6>
                    <ol class="small text-muted mb-0">
                        <li class="mb-2">Selecione o departamento de destino</li>
                        <li class="mb-2">Busque os produtos pelo nome ou código</li>
                        <li class="mb-2">Informe a quantidade e valor de cada item</li>
                        <li>Clique em "Confirmar Entrada"</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação -->
    <div class="modal fade" id="modalConfirmarEntrada" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Confirmar Entrada</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Deseja confirmar a entrada de <strong id="modal-total-itens">0</strong> produto(s)?</p>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Esta ação irá adicionar os produtos ao estoque do departamento selecionado.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btn-confirmar-modal">
                        <i class="bi bi-check-lg me-2"></i>Confirmar
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
        let itensEntrada = [];
        let produtos = [];
        
        $(document).ready(function() {
            carregarDepartamentos();
            
            $('#departamento').change(function() {
                limparItens();
            });
            
            $('#btn-buscar').click(buscarProdutos);
            $('#busca-produto').keypress(function(e) {
                if (e.which === 13) buscarProdutos();
            });
        });
        
        function carregarDepartamentos() {
            $.getJSON(baseUrl + '/api/estoque/departamentos/listar.php', function(data) {
                if (data.status === 'ok') {
                    let html = '<option value="">Selecione o departamento...</option>';
                    data.departamentos.forEach(d => {
                        html += `<option value="${d.id}">${d.nome}</option>`;
                    });
                    $('#departamento').html(html);
                }
            });
        }
        
        function buscarProdutos() {
            const departamento = $('#departamento').val();
            const busca = $('#busca-produto').val().trim();
            
            if (!departamento) {
                exibirToast('Selecione um departamento primeiro', 'warning');
                return;
            }
            
            if (busca.length < 2) {
                exibirToast('Digite pelo menos 2 caracteres', 'warning');
                return;
            }
            
            $.getJSON(baseUrl + '/api/estoque/produtos/listar.php', {
                departamento: departamento,
                busca: busca,
                limite: 10
            }, function(data) {
                if (data.status === 'ok' && data.produtos.length > 0) {
                    let html = '<div class="list-group">';
                    data.produtos.forEach(p => {
                        const jaAdicionado = itensEntrada.find(i => i.id_produto === p.id);
                        html += `
                            <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ${jaAdicionado ? 'disabled' : ''}"
                                    onclick="adicionarProduto(${p.id})" ${jaAdicionado ? 'disabled' : ''}>
                                <div>
                                    <strong>${p.nome}</strong><br>
                                    <small class="text-muted">${p.codigo || 'Sem código'} • Estoque: ${p.quantidade_atual} ${p.unidade}</small>
                                </div>
                                ${jaAdicionado ? '<span class="badge bg-secondary">Já adicionado</span>' : '<i class="bi bi-plus-circle text-success fs-5"></i>'}
                            </button>
                        `;
                    });
                    html += '</div>';
                    $('#resultado-busca').html(html).show();
                    produtos = data.produtos;
                } else {
                    $('#resultado-busca').html('<div class="alert alert-warning">Nenhum produto encontrado</div>').show();
                }
            });
        }
        
        function adicionarProduto(idProduto) {
            const produto = produtos.find(p => p.id === idProduto);
            if (!produto) return;
            
            itensEntrada.push({
                id_produto: produto.id,
                nome: produto.nome,
                codigo: produto.codigo,
                unidade: produto.unidade,
                estoque_atual: produto.quantidade_atual,
                quantidade: 1,
                valor_unitario: produto.valor_unitario || 0,
                lote: '',
                validade: ''
            });
            
            renderizarItens();
            $('#resultado-busca').hide();
            $('#busca-produto').val('').focus();
        }
        
        function renderizarItens() {
            if (itensEntrada.length === 0) {
                $('#lista-itens').html(`
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        Nenhum produto adicionado
                    </div>
                `);
                $('#btn-confirmar').prop('disabled', true);
                atualizarResumo();
                return;
            }
            
            let html = '';
            itensEntrada.forEach((item, index) => {
                html += `
                    <div class="item-entrada">
                        <button class="btn btn-sm btn-outline-danger btn-remover" onclick="removerItem(${index})">
                            <i class="bi bi-x"></i>
                        </button>
                        <div class="row">
                            <div class="col-12 mb-2">
                                <strong>${item.nome}</strong>
                                <span class="badge bg-secondary ms-2">${item.codigo || 'S/C'}</span>
                                <small class="text-muted ms-2">Estoque atual: ${item.estoque_atual} ${item.unidade}</small>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small">Quantidade</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" value="${item.quantidade}" 
                                           min="0.01" step="0.01" onchange="atualizarItem(${index}, 'quantidade', this.value)">
                                    <span class="input-group-text">${item.unidade}</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small">Valor Unitário</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" value="${item.valor_unitario}" 
                                           min="0" step="0.01" onchange="atualizarItem(${index}, 'valor_unitario', this.value)">
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small">Lote</label>
                                <input type="text" class="form-control" value="${item.lote}" 
                                       onchange="atualizarItem(${index}, 'lote', this.value)" placeholder="Opcional">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small">Validade</label>
                                <input type="date" class="form-control" value="${item.validade}" 
                                       onchange="atualizarItem(${index}, 'validade', this.value)">
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $('#lista-itens').html(html);
            $('#btn-confirmar').prop('disabled', false);
            atualizarResumo();
        }
        
        function atualizarItem(index, campo, valor) {
            if (campo === 'quantidade' || campo === 'valor_unitario') {
                valor = parseFloat(valor) || 0;
            }
            itensEntrada[index][campo] = valor;
            atualizarResumo();
        }
        
        function removerItem(index) {
            itensEntrada.splice(index, 1);
            renderizarItens();
        }
        
        function limparItens() {
            itensEntrada = [];
            renderizarItens();
        }
        
        function limparCampos() {
            // Limpar itens
            itensEntrada = [];
            renderizarItens();
            
            // Limpar campo de busca e resultado
            $('#busca-produto').val('');
            $('#resultado-busca').hide().html('');
            
            // Limpar observações
            $('#observacoes').val('');
            
            // O departamento permanece selecionado (não limpar)
        }
        
        function atualizarResumo() {
            const totalItens = itensEntrada.length;
            const totalProdutos = itensEntrada.reduce((sum, i) => sum + parseFloat(i.quantidade || 0), 0);
            const valorTotal = itensEntrada.reduce((sum, i) => sum + (parseFloat(i.quantidade || 0) * parseFloat(i.valor_unitario || 0)), 0);
            
            $('#total-itens').text(totalItens);
            $('#total-produtos').text(totalProdutos.toFixed(2));
            $('#valor-total').text('R$ ' + valorTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2 }));
        }
        
        function confirmarEntrada() {
            const departamento = $('#departamento').val();
            
            if (!departamento) {
                exibirToast('Selecione um departamento', 'warning');
                return;
            }
            
            if (itensEntrada.length === 0) {
                exibirToast('Adicione pelo menos um produto', 'warning');
                return;
            }
            
            // Validar quantidades
            for (let i = 0; i < itensEntrada.length; i++) {
                if (!itensEntrada[i].quantidade || itensEntrada[i].quantidade <= 0) {
                    exibirToast('Informe a quantidade para todos os produtos', 'warning');
                    return;
                }
            }
            
            // Mostrar modal de confirmação
            $('#modal-total-itens').text(itensEntrada.length);
            const modal = new bootstrap.Modal(document.getElementById('modalConfirmarEntrada'));
            modal.show();
        }
        
        // Confirmar após clicar no botão do modal
        $('#btn-confirmar-modal').click(function() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalConfirmarEntrada'));
            modal.hide();
            
            processarEntrada();
        });
        
        function processarEntrada() {
            const departamento = $('#departamento').val();
            
            $('#btn-confirmar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Processando...');
            $('#btn-confirmar-modal').prop('disabled', true);
            
            $.ajax({
                url: baseUrl + '/api/estoque/movimentacoes/registrar_entrada.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    id_departamento: departamento,
                    itens: itensEntrada,
                    observacoes: $('#observacoes').val()
                }),
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        exibirToast(data.mensagem, 'success');
                        
                        // Limpar campos mas manter o departamento selecionado
                        limparCampos();
                        
                        // Resetar botão
                        $('#btn-confirmar').prop('disabled', true).html('<i class="bi bi-check-lg me-2"></i>Confirmar Entrada');
                        $('#btn-confirmar-modal').prop('disabled', false);
                    } else {
                        exibirToast(data.mensagem, 'danger');
                        $('#btn-confirmar').prop('disabled', false).html('<i class="bi bi-check-lg me-2"></i>Confirmar Entrada');
                        $('#btn-confirmar-modal').prop('disabled', false);
                    }
                },
                error: function() {
                    exibirToast('Erro ao processar entrada', 'danger');
                    $('#btn-confirmar').prop('disabled', false).html('<i class="bi bi-check-lg me-2"></i>Confirmar Entrada');
                    $('#btn-confirmar-modal').prop('disabled', false);
                }
            });
        }
    </script>
</body>
</html>

