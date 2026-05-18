<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('estoque_movimentacoes');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Movimentações - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #5ee7df 0%, #b490ca 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(94, 231, 223, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .mov-card { background: white; border-radius: 12px; padding: 1rem; margin-bottom: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border-left: 4px solid #718096; }
        .mov-card.entrada { border-left-color: #48bb78; }
        .mov-card.saida { border-left-color: #e53e3e; }
        .mov-card.ajuste { border-left-color: #ecc94b; }
        .tipo-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .tipo-badge.entrada { background: #c6f6d5; color: #22543d; }
        .tipo-badge.saida { background: #fed7d7; color: #742a2a; }
        .tipo-badge.ajuste { background: #fefcbf; color: #744210; }
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
                        <h5 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Movimentações</h5>
                        <small class="opacity-75">Histórico de entradas e saídas</small>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="entrada.php" class="btn btn-light btn-sm"><i class="bi bi-box-arrow-in-down me-1"></i><span class="hide-mobile">Entrada</span></a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <div class="card-main p-3 mb-4">
            <div class="row g-2">
                <div class="col-md-3">
                    <input type="date" class="form-control" id="data-inicio" placeholder="Data início">
                </div>
                <div class="col-md-3">
                    <input type="date" class="form-control" id="data-fim" placeholder="Data fim">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filtro-tipo">
                        <option value="">Todos os tipos</option>
                        <option value="entrada">Entradas</option>
                        <option value="saida">Saídas</option>
                        <option value="ajuste">Ajustes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filtro-departamento">
                        <option value="">Todos os departamentos</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100" onclick="carregarMovimentacoes()"><i class="bi bi-search"></i></button>
                </div>
            </div>
        </div>

        <div id="lista-movimentacoes">
            <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';
        
        $(document).ready(function() {
            // Definir datas padrão (último mês)
            const hoje = new Date();
            const mesPassado = new Date();
            mesPassado.setMonth(mesPassado.getMonth() - 1);
            $('#data-fim').val(hoje.toISOString().split('T')[0]);
            $('#data-inicio').val(mesPassado.toISOString().split('T')[0]);
            
            carregarDepartamentos();
            carregarMovimentacoes();
        });
        
        function carregarDepartamentos() {
            $.getJSON(baseUrl + '/api/estoque/departamentos/listar.php', function(data) {
                if (data.status === 'ok') {
                    let html = '<option value="">Todos os departamentos</option>';
                    data.departamentos.forEach(d => html += `<option value="${d.id}">${d.nome}</option>`);
                    $('#filtro-departamento').html(html);
                }
            });
        }
        
        function carregarMovimentacoes() {
            const params = {
                data_inicio: $('#data-inicio').val(),
                data_fim: $('#data-fim').val(),
                tipo: $('#filtro-tipo').val(),
                departamento: $('#filtro-departamento').val()
            };
            
            $.getJSON(baseUrl + '/api/estoque/movimentacoes/listar.php', params, function(data) {
                const container = $('#lista-movimentacoes');
                
                if (data.status === 'ok' && data.movimentacoes && data.movimentacoes.length > 0) {
                    let html = '';
                    data.movimentacoes.forEach(m => {
                        const sinal = m.tipo === 'entrada' ? '+' : (m.tipo === 'saida' ? '-' : '±');
                        html += `
                            <div class="mov-card ${m.tipo}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>${m.produto_nome}</strong>
                                        <span class="tipo-badge ${m.tipo} ms-2">${m.tipo}</span>
                                        <div class="text-muted small">${m.departamento_nome || 'Geral'}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fs-5 ${m.tipo === 'entrada' ? 'text-success' : (m.tipo === 'saida' ? 'text-danger' : 'text-warning')}">
                                            ${sinal}${parseFloat(m.quantidade).toFixed(2)} ${m.unidade_sigla}
                                        </div>
                                        <small class="text-muted">${m.data_formatada}</small>
                                    </div>
                                </div>
                                ${m.observacoes ? `<div class="small text-muted mt-2"><i class="bi bi-chat-left-text me-1"></i>${m.observacoes}</div>` : ''}
                                <div class="small text-muted mt-1"><i class="bi bi-person me-1"></i>${m.usuario_nome}</div>
                            </div>
                        `;
                    });
                    container.html(html);
                } else {
                    container.html(`
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-arrow-left-right fs-1 d-block mb-3"></i>
                            <h5>Nenhuma movimentação encontrada</h5>
                            <p>Ajuste os filtros ou registre uma nova entrada</p>
                        </div>
                    `);
                }
            }).fail(function() {
                exibirToast('Erro ao carregar movimentações', 'danger');
            });
        }
    </script>
</body>
</html>



