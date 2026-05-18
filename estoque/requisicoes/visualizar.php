<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../api/conexao.php';
require_once __DIR__ . '/../../api/estoque/almoxarife_helper.php';

MenuPermissaoService::exigirAcesso('estoque_requisicoes');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
$isAdmin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
$podeAutorizar = $isAdmin || MenuPermissaoService::podeAcessar('estoque_autorizar_requisicoes');
$ehAlmoxarife = eh_almoxarife($conn, $usuarioId, $isAdmin);
$requisicaoId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($requisicaoId <= 0) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Requisição #<?= $requisicaoId ?> - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(240, 147, 251, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .status-badge { padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.875rem; font-weight: 600; }
        .status-badge.pendente { background: #fefcbf; color: #744210; }
        .status-badge.aguardando_lancamento { background: #e9d8fd; color: #553c9a; }
        .status-badge.aprovada { background: #c6f6d5; color: #22543d; }
        .status-badge.parcial { background: #bee3f8; color: #2a4365; }
        .status-badge.entregue { background: #9ae6b4; color: #1c4532; }
        .status-badge.cancelada, .status-badge.rejeitada { background: #fed7d7; color: #742a2a; }
        .status-badge.rascunho { background: #e2e8f0; color: #4a5568; }
        .bloco-solicitacao {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border-left: 4px solid #f093fb;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            white-space: pre-wrap;
            font-size: 0.95rem;
            color: #2d3748;
        }
        .bloco-resposta {
            background: linear-gradient(135deg, #fffaf0 0%, #fff5e7 100%);
            border-left: 4px solid #dd6b20;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            white-space: pre-wrap;
            font-size: 0.95rem;
            color: #744210;
        }
        .item-lancado {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid #f093fb;
        }
        .priority-badge { padding: 0.25rem 0.75rem; border-radius: 10px; font-size: 0.75rem; font-weight: 600; }
        .priority-badge.baixa { background: #718096; color: white; }
        .priority-badge.normal { background: #3182ce; color: white; }
        .priority-badge.alta { background: #dd6b20; color: white; }
        .priority-badge.urgente { background: #e53e3e; color: white; }
        .item-row { padding: 1rem; border-bottom: 1px solid #e2e8f0; }
        .item-row:last-child { border-bottom: none; }
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
                        <h5 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Requisição #<span id="req-numero"><?= $requisicaoId ?></span></h5>
                        <small class="opacity-75" id="req-status-header">Carregando...</small>
                    </div>
                </div>
                <div id="acoes-header"></div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <div class="row g-4">
            <!-- Info Principal -->
            <div class="col-lg-8">
                <div class="card-main p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <h4 class="mb-1">Requisição #<span id="req-numero-2"><?= $requisicaoId ?></span></h4>
                            <span class="status-badge" id="req-status">-</span>
                            <span class="priority-badge ms-2" id="req-prioridade">-</span>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small">Data da Solicitação</div>
                            <div class="fw-bold" id="req-data">-</div>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="text-muted small">Solicitante</div>
                            <div class="fw-bold" id="req-solicitante">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Departamento</div>
                            <div class="fw-bold" id="req-departamento">-</div>
                        </div>
                        <div class="col-md-6" id="finalidade-container" style="display: none;">
                            <div class="text-muted small">Finalidade</div>
                            <div class="fw-bold" id="req-finalidade">-</div>
                        </div>
                        <div class="col-md-6" id="motivo-container" style="display: none;">
                            <div class="text-muted small">Motivo</div>
                            <div class="fw-bold" id="req-motivo">-</div>
                        </div>
                    </div>
                    
                    <div class="mb-4" id="obs-container" style="display: none;">
                        <div class="text-muted small">Observações</div>
                        <div id="req-observacoes" class="p-3 bg-light rounded">-</div>
                    </div>

                    <!-- Texto livre da solicitação (modo novo) -->
                    <div class="mb-4" id="solicitacao-container" style="display: none;">
                        <div class="text-muted small mb-2"><i class="bi bi-pencil-square me-1"></i>Solicitação do usuário</div>
                        <div id="req-solicitacao-texto" class="bloco-solicitacao">-</div>
                    </div>

                    <!-- Resposta do almoxarife (quando preenchida) -->
                    <div class="mb-4" id="resposta-container" style="display: none;">
                        <div class="text-muted small mb-2"><i class="bi bi-chat-left-text me-1"></i>Resposta do almoxarife <span id="resposta-quem-quando" class="ms-2 small fst-italic"></span></div>
                        <div id="req-resposta-almoxarife" class="bloco-resposta">-</div>
                    </div>

                    <hr>

                    <h5 class="mb-3"><i class="bi bi-box-seam me-2"></i>Itens da Requisição</h5>
                    <div id="lista-itens">
                        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
                    </div>
                </div>

                <!-- Painel de lançamento (somente almoxarife, status=aguardando_lancamento) -->
                <?php if ($ehAlmoxarife): ?>
                <div class="card-main p-4 mb-4" id="painel-lancamento" style="display: none;">
                    <h5 class="mb-3"><i class="bi bi-clipboard-data me-2"></i>Lançar itens (modo almoxarife)</h5>
                    <p class="small text-muted mb-3">Adicione os produtos correspondentes à solicitação. Se algum item não estiver disponível, descreva no campo "Resposta ao solicitante".</p>

                    <div class="mb-3">
                        <label class="form-label small">Almoxarifado de origem <span class="text-danger">*</span></label>
                        <select class="form-select" id="departamento-destino-lanc">
                            <option value="">Selecione de qual almoxarifado os produtos sairão...</option>
                        </select>
                        <small class="text-muted">Departamento de estoque que vai atender esta requisição.</small>
                    </div>

                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="busca-produto-lanc" placeholder="Buscar produto (mín. 2 caracteres)...">
                        <button class="btn btn-outline-secondary" type="button" onclick="buscarProdutosLanc()">Buscar</button>
                    </div>
                    <div id="resultado-busca-lanc" style="display: none;"></div>

                    <div id="itens-lancados-wrapper" class="mb-3"></div>

                    <div class="mb-3">
                        <label class="form-label small">Resposta ao solicitante <span class="text-muted">(opcional)</span></label>
                        <textarea class="form-control" id="resposta-almoxarife" rows="3" placeholder="Ex.: 'Vou pedir as pilhas AA junto com o próximo abastecimento. Por enquanto, atendo apenas o papel e as canetas.'"></textarea>
                    </div>

                    <button class="btn btn-success btn-lg w-100" id="btn-lancar" onclick="lancarItens()">
                        <i class="bi bi-send-check me-2"></i>Lançar e enviar para aprovação
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Ações -->
                <div class="card-main p-4 mb-4" id="card-acoes" style="display: none;">
                    <h6 class="mb-3"><i class="bi bi-gear me-2"></i>Ações</h6>
                    <div class="d-grid gap-2" id="botoes-acoes"></div>
                </div>
                
                <!-- Histórico -->
                <div class="card-main p-4">
                    <h6 class="mb-3"><i class="bi bi-clock-history me-2"></i>Histórico</h6>
                    <div id="historico">
                        <div class="text-muted small">Carregando...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Aprovar/Rejeitar -->
    <div class="modal fade" id="modalDecisao" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDecisaoTitulo">Decisão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="decisao-tipo">
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" id="decisao-obs" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-confirmar-decisao">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';
        const requisicaoId = <?= $requisicaoId ?>;
        const podeAutorizar = <?= $podeAutorizar ? 'true' : 'false' ?>;
        const ehAlmoxarife = <?= $ehAlmoxarife ? 'true' : 'false' ?>;
        let requisicao = null;
        let itensLancados = [];
        let produtosBuscados = [];
        
        $(document).ready(function() {
            carregarRequisicao();
            if (ehAlmoxarife) {
                carregarDepartamentosLanc();
            }
        });

        function carregarDepartamentosLanc() {
            $.getJSON(baseUrl + '/api/estoque/departamentos/listar.php', function(data) {
                if (data.status === 'ok') {
                    let html = '<option value="">Selecione de qual almoxarifado os produtos sairão...</option>';
                    data.departamentos.forEach(d => {
                        html += `<option value="${d.id}">${d.nome}</option>`;
                    });
                    $('#departamento-destino-lanc').html(html);
                }
            });
        }
        
        function carregarRequisicao() {
            $.getJSON(baseUrl + '/api/estoque/requisicoes/buscar.php', { id: requisicaoId }, function(data) {
                if (data.status === 'ok') {
                    requisicao = data.requisicao;
                    exibirRequisicao();
                } else {
                    exibirToast(data.mensagem || 'Requisição não encontrada', 'danger');
                    setTimeout(() => window.location.href = 'index.php', 2000);
                }
            }).fail(function() {
                exibirToast('Erro ao carregar requisição', 'danger');
            });
        }
        
        function exibirRequisicao() {
            const r = requisicao;

            // Reset visual antes de renderizar (evita estado misto entre recargas)
            $('#solicitacao-container, #resposta-container, #obs-container, #motivo-container, #finalidade-container').hide();
            $('#painel-lancamento').hide();
            $('#card-acoes').hide();
            $('#botoes-acoes').empty();
            $('#acoes-header').empty();
            $('#btn-lancar').prop('disabled', false).html('<i class="bi bi-send-check me-2"></i>Lançar e enviar para aprovação');
            // Limpa o form de lançamento entre recargas
            itensLancados = [];
            $('#itens-lancados-wrapper').empty();
            $('#resultado-busca-lanc').hide().empty();
            $('#busca-produto-lanc').val('');

            const statusLabel = r.status === 'aguardando_lancamento' ? 'Aguardando lançamento' : r.status;
            $('#req-numero, #req-numero-2').text(r.numero || 'REQ-' + r.id);
            $('#req-status-header').text(statusLabel);
            $('#req-status').text(statusLabel).attr('class', 'status-badge ' + r.status);
            $('#req-prioridade').text(r.prioridade || '—').attr('class', 'priority-badge ' + (r.prioridade || ''));
            $('#req-data').text(r.data_formatada || '—');
            $('#req-solicitante').text(r.solicitante || '—');
            $('#req-departamento').text(r.departamento_destino || '—');
            
            if (r.observacoes_solicitante) {
                $('#req-observacoes').text(r.observacoes_solicitante);
                $('#obs-container').show();
            }

            // Texto livre do solicitante (modo novo)
            if (r.solicitacao_texto && r.solicitacao_texto.trim() !== '') {
                $('#req-solicitacao-texto').text(r.solicitacao_texto);
                $('#solicitacao-container').show();
            }

            // Resposta do almoxarife
            if (r.resposta_almoxarife && r.resposta_almoxarife.trim() !== '') {
                $('#req-resposta-almoxarife').text(r.resposta_almoxarife);
                if (r.almoxarife_lancamento) {
                    $('#resposta-quem-quando').text(`(${r.almoxarife_lancamento}${r.data_lancamento_formatada ? ' — ' + r.data_lancamento_formatada : ''})`);
                }
                $('#resposta-container').show();
            }

            // Painel de lançamento (modo almoxarife em requisição aguardando_lancamento)
            if (ehAlmoxarife && r.status === 'aguardando_lancamento') {
                $('#painel-lancamento').show();
                if (r.resposta_almoxarife) {
                    $('#resposta-almoxarife').val(r.resposta_almoxarife);
                }
            }
            
            if (r.motivo) {
                $('#req-motivo').text(r.motivo);
                $('#motivo-container').show();
            }
            
            if (r.finalidade) {
                $('#req-finalidade').text(r.finalidade);
                $('#finalidade-container').show();
            }
            
            // Itens
            let html = '';
            if (r.itens && r.itens.length > 0) {
                r.itens.forEach(item => {
                    const solicitado = parseFloat(item.quantidade_solicitada) || 0;
                    const aprovado = parseFloat(item.quantidade_aprovada) || 0;
                    const entregue = parseFloat(item.quantidade_entregue) || 0;
                    const percentual = solicitado > 0 ? Math.round((entregue / solicitado) * 100) : 0;
                    
                    html += `
                        <div class="item-row">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${item.produto_nome}</strong>
                                    <div class="small text-muted">${item.categoria_nome || ''} | ${item.produto_codigo || ''}</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold">${solicitado} ${item.unidade_sigla}</div>
                                    ${aprovado > 0 ? `<div class="small text-info">Aprovado: ${aprovado}</div>` : ''}
                                    ${entregue > 0 ? `<div class="small text-success">Entregue: ${entregue}</div>` : ''}
                                </div>
                            </div>
                            ${entregue > 0 ? `
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar ${percentual >= 100 ? 'bg-success' : 'bg-info'}" style="width: ${percentual}%"></div>
                                </div>
                            ` : ''}
                        </div>
                    `;
                });
            } else {
                html = '<div class="text-muted text-center py-4">Nenhum item</div>';
            }
            $('#lista-itens').html(html);
            
            // Ações - Mostra botões se tiver permissão de autorização E requisição estiver pendente
            if (podeAutorizar && r.status === 'pendente') {
                $('#card-acoes').show();
                $('#botoes-acoes').html(`
                    <button class="btn btn-success" onclick="abrirDecisao('aprovar')">
                        <i class="bi bi-check-lg me-2"></i>Aprovar
                    </button>
                    <button class="btn btn-danger" onclick="abrirDecisao('rejeitar')">
                        <i class="bi bi-x-lg me-2"></i>Rejeitar
                    </button>
                `);
            }
            
            // Botão de impressão para requisições aprovadas
            if (r.status === 'aprovada' || r.status === 'parcial' || r.status === 'entregue') {
                // Botão no card de ações
                $('#card-acoes').show();
                let botoesHtml = $('#botoes-acoes').html() || '';
                if (!botoesHtml.includes('btn-print')) {
                    $('#botoes-acoes').html(botoesHtml + `
                        <button class="btn btn-primary" onclick="imprimirRequisicao()">
                            <i class="bi bi-printer me-2"></i>Imprimir Requisição
                        </button>
                    `);
                }
                
                // Botão no header
                $('#acoes-header').html(`
                    <button class="btn btn-outline-light btn-sm" onclick="imprimirRequisicao()">
                        <i class="bi bi-printer me-1"></i>Imprimir
                    </button>
                `);
            }
            
            // Histórico simplificado
            let historico = `
                <div class="small mb-2">
                    <i class="bi bi-plus-circle text-success me-2"></i>
                    Criada em ${r.data_formatada}
                </div>`;
            if (r.almoxarife_lancamento && r.data_lancamento_formatada) {
                historico += `
                <div class="small mb-2">
                    <i class="bi bi-clipboard-data text-warning me-2"></i>
                    Itens lançados por ${r.almoxarife_lancamento} em ${r.data_lancamento_formatada}
                </div>`;
            }
            if (r.aprovador) {
                historico += `
                <div class="small mb-2">
                    <i class="bi bi-check-circle text-primary me-2"></i>
                    ${r.status === 'aprovada' ? 'Aprovada' : (r.status === 'rejeitada' ? 'Rejeitada' : 'Decisão registrada')} por ${r.aprovador}
                </div>`;
            }
            $('#historico').html(historico);
        }

        // ============ MODO ALMOXARIFE: lançamento de itens ============
        function buscarProdutosLanc() {
            const busca = $('#busca-produto-lanc').val().trim();
            const departamento = requisicao && requisicao.departamento_destino ? null : null; // listagem usa o depto do produto
            if (busca.length < 2) {
                exibirToast('Digite pelo menos 2 caracteres', 'warning');
                return;
            }
            $.getJSON(baseUrl + '/api/estoque/produtos/listar.php', {
                busca: busca,
                limite: 10
            }, function(data) {
                if (data.status === 'ok' && data.produtos && data.produtos.length > 0) {
                    produtosBuscados = data.produtos;
                    let html = '<div class="list-group mb-3">';
                    data.produtos.forEach(p => {
                        const jaAdd = itensLancados.find(i => i.id_produto === p.id);
                        const semEstoque = parseFloat(p.quantidade_atual) <= 0;
                        html += `
                            <button class="list-group-item list-group-item-action d-flex justify-content-between ${jaAdd ? 'disabled' : ''}"
                                    onclick="adicionarItemLancamento(${p.id})" ${jaAdd ? 'disabled' : ''}>
                                <div>
                                    <strong>${p.nome}</strong>
                                    <div class="small text-muted">Disponível: ${p.quantidade_atual} ${p.unidade} ${semEstoque ? '— sem estoque' : ''}</div>
                                </div>
                                ${jaAdd
                                    ? '<span class="badge bg-secondary align-self-center">Adicionado</span>'
                                    : '<i class="bi bi-plus-circle text-success fs-5 align-self-center"></i>'}
                            </button>`;
                    });
                    html += '</div>';
                    $('#resultado-busca-lanc').html(html).show();
                } else {
                    $('#resultado-busca-lanc').html('<div class="alert alert-warning">Nenhum produto encontrado</div>').show();
                }
            });
        }

        function adicionarItemLancamento(idProduto) {
            const p = produtosBuscados.find(x => x.id === idProduto);
            if (!p) return;
            if (itensLancados.find(i => i.id_produto === idProduto)) return;
            itensLancados.push({
                id_produto: p.id,
                nome: p.nome,
                unidade: p.unidade,
                disponivel: p.quantidade_atual,
                quantidade_solicitada: 1,
                observacoes: ''
            });
            renderizarItensLancados();
            $('#resultado-busca-lanc').hide();
            $('#busca-produto-lanc').val('');
        }

        function renderizarItensLancados() {
            if (itensLancados.length === 0) {
                $('#itens-lancados-wrapper').html('<div class="text-muted text-center small py-2">Nenhum item adicionado ainda</div>');
                return;
            }
            let html = '<div class="small text-muted mb-2">Itens a lançar:</div>';
            itensLancados.forEach((item, idx) => {
                html += `
                    <div class="item-lancado">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong>${item.nome}</strong>
                                <div class="small text-muted">Disponível: ${item.disponivel} ${item.unidade}</div>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="removerItemLancamento(${idx})"><i class="bi bi-x"></i></button>
                        </div>
                        <div class="row g-2">
                            <div class="col-5">
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control" value="${item.quantidade_solicitada}"
                                           min="0.01" step="0.01"
                                           onchange="atualizarItemLanc(${idx}, 'quantidade_solicitada', this.value)">
                                    <span class="input-group-text">${item.unidade}</span>
                                </div>
                            </div>
                            <div class="col-7">
                                <input type="text" class="form-control form-control-sm" placeholder="Observação (opcional)"
                                       value="${item.observacoes}"
                                       onchange="atualizarItemLanc(${idx}, 'observacoes', this.value)">
                            </div>
                        </div>
                    </div>`;
            });
            $('#itens-lancados-wrapper').html(html);
        }

        function atualizarItemLanc(idx, campo, valor) {
            if (campo === 'quantidade_solicitada') {
                valor = parseFloat(valor) || 0;
            }
            itensLancados[idx][campo] = valor;
        }

        function removerItemLancamento(idx) {
            itensLancados.splice(idx, 1);
            renderizarItensLancados();
        }

        function lancarItens() {
            const resposta = $('#resposta-almoxarife').val().trim();
            const departamentoDestino = $('#departamento-destino-lanc').val();
            if (itensLancados.length === 0 && resposta === '') {
                exibirToast('Adicione pelo menos um item ou registre uma resposta ao solicitante', 'warning');
                return;
            }
            if (itensLancados.length > 0 && !departamentoDestino) {
                exibirToast('Selecione o almoxarifado de onde os produtos sairão', 'warning');
                return;
            }
            // valida quantidades
            for (let i = 0; i < itensLancados.length; i++) {
                if (!itensLancados[i].quantidade_solicitada || itensLancados[i].quantidade_solicitada <= 0) {
                    exibirToast(`Item "${itensLancados[i].nome}": quantidade inválida`, 'warning');
                    return;
                }
            }

            $('#btn-lancar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Lançando...');

            $.ajax({
                url: baseUrl + '/api/estoque/requisicoes/lancar_itens.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    id: requisicaoId,
                    itens: itensLancados,
                    resposta_almoxarife: resposta,
                    id_departamento_destino: departamentoDestino || null
                }),
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        exibirToast(data.mensagem, 'success');
                        setTimeout(() => carregarRequisicao(), 800);
                    } else {
                        exibirToast(data.mensagem, 'danger');
                        $('#btn-lancar').prop('disabled', false).html('<i class="bi bi-send-check me-2"></i>Lançar e enviar para aprovação');
                    }
                },
                error: function() {
                    exibirToast('Erro ao lançar itens', 'danger');
                    $('#btn-lancar').prop('disabled', false).html('<i class="bi bi-send-check me-2"></i>Lançar e enviar para aprovação');
                }
            });
        }

        $(document).on('keypress', '#busca-produto-lanc', function(e) {
            if (e.which === 13) { e.preventDefault(); buscarProdutosLanc(); }
        });
        
        function abrirDecisao(tipo) {
            $('#decisao-tipo').val(tipo);
            $('#modalDecisaoTitulo').text(tipo === 'aprovar' ? 'Aprovar Requisição' : 'Rejeitar Requisição');
            $('#btn-confirmar-decisao').removeClass('btn-success btn-danger').addClass(tipo === 'aprovar' ? 'btn-success' : 'btn-danger');
            new bootstrap.Modal('#modalDecisao').show();
        }
        
        $('#btn-confirmar-decisao').click(function() {
            const tipo = $('#decisao-tipo').val();
            const obs = $('#decisao-obs').val();
            
            $.post(baseUrl + '/api/estoque/requisicoes/decidir.php', {
                id: requisicaoId,
                decisao: tipo,
                observacoes: obs
            }, function(data) {
                if (data.status === 'ok') {
                    exibirToast(data.mensagem, 'success');
                    bootstrap.Modal.getInstance('#modalDecisao').hide();
                    carregarRequisicao();
                } else {
                    exibirToast(data.mensagem || 'Erro', 'danger');
                }
            }, 'json').fail(function() {
                exibirToast('Erro ao processar', 'danger');
            });
        });
        
        function imprimirRequisicao() {
            const url = baseUrl + '/api/estoque/requisicoes/pdf.php?id=' + requisicaoId;
            window.open(url, '_blank');
            exibirToast('Gerando PDF da requisição...', 'info');
        }
    </script>
</body>
</html>

