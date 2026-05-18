<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('estoque_nova_requisicao');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuarioId = $_SESSION['usuario_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Nova Requisição - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(240, 147, 251, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }

        .resumo-requisicao {
            background: var(--primary-gradient);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            position: sticky;
            top: 80px;
        }

        .prioridade-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
            color: white;
            user-select: none;
        }
        .prioridade-badge:hover { opacity: 0.85; }
        .prioridade-badge.selected { border-color: white; transform: scale(1.05); }
        .prioridade-badge.baixa { background: #718096; }
        .prioridade-badge.normal { background: #3182ce; }
        .prioridade-badge.alta { background: #dd6b20; }
        .prioridade-badge.urgente { background: #e53e3e; }

        .alert-info-custom {
            background: linear-gradient(135deg, #ebf4ff 0%, #e6f7ff 100%);
            border-left: 4px solid #3182ce;
            color: #2c5282;
            border-radius: 8px;
        }

        textarea#solicitacao-texto {
            min-height: 180px;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 12px;
        }

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
                        <h5 class="mb-0"><i class="bi bi-clipboard-plus me-2"></i>Nova Requisição</h5>
                        <small class="opacity-75">Descreva o que precisa — o almoxarife lança os produtos</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <div class="row">
            <div class="col-lg-8">
                <div class="card-main p-4 mb-4">
                    <div class="alert alert-info-custom mb-4">
                        <i class="bi bi-info-circle me-2"></i>
                        Descreva os materiais que precisa em texto livre (ex.: <em>"2 pilhas AA, 1 resma de papel A4 e 3 canetas azuis"</em>). O almoxarife vai analisar a solicitação, lançar os produtos no sistema e responder com o atendimento possível.
                    </div>

                    <h6 class="mb-3"><i class="bi bi-info-circle me-2"></i>Dados da Requisição</h6>

                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Departamento de Origem (onde você está)</label>
                            <select class="form-select" id="departamento-origem">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Finalidade</label>
                            <select class="form-select" id="finalidade">
                                <option value="consumo_interno">Consumo Interno</option>
                                <option value="evento">Evento</option>
                                <option value="construcao">Construção</option>
                                <option value="reforma">Reforma</option>
                                <option value="manutencao">Manutenção</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data de Necessidade</label>
                            <input type="date" class="form-control" id="data-necessidade">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Prioridade</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <span class="prioridade-badge baixa" data-prioridade="baixa" onclick="selecionarPrioridade('baixa')">Baixa</span>
                            <span class="prioridade-badge normal selected" data-prioridade="normal" onclick="selecionarPrioridade('normal')">Normal</span>
                            <span class="prioridade-badge alta" data-prioridade="alta" onclick="selecionarPrioridade('alta')">Alta</span>
                            <span class="prioridade-badge urgente" data-prioridade="urgente" onclick="selecionarPrioridade('urgente')">Urgente</span>
                        </div>
                        <input type="hidden" id="prioridade" value="normal">
                    </div>

                    <hr>

                    <h6 class="mb-3"><i class="bi bi-pencil-square me-2"></i>O que você precisa <span class="text-danger">*</span></h6>
                    <textarea class="form-control" id="solicitacao-texto"
                              placeholder="Ex.:&#10;- 2 pilhas AA&#10;- 1 resma de papel A4&#10;- 3 canetas azuis&#10;- 1 grampeador médio"></textarea>
                    <small class="text-muted d-block mt-2">Liste os itens com quantidades e detalhes (marca, modelo, tamanho) sempre que possível.</small>

                    <div class="mt-3">
                        <label class="form-label small">Motivo/Justificativa <span class="text-muted">(opcional)</span></label>
                        <input type="text" class="form-control" id="motivo" placeholder="Para que serão usados estes materiais?">
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="resumo-requisicao">
                    <h5 class="mb-3"><i class="bi bi-clipboard-check me-2"></i>Resumo</h5>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Prioridade:</span>
                        <strong id="prioridade-texto">Normal</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Status inicial:</span>
                        <strong>Aguardando almoxarife</strong>
                    </div>

                    <small class="d-block mb-3 opacity-75">
                        Depois de enviar, o almoxarife vai lançar os produtos correspondentes à sua descrição e encaminhar para aprovação.
                    </small>

                    <button class="btn btn-light btn-lg w-100" onclick="enviarRequisicao()" id="btn-enviar">
                        <i class="bi bi-send me-2"></i>Enviar Solicitação
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
            carregarDepartamentos();
        });

        function carregarDepartamentos() {
            $.getJSON(baseUrl + '/api/estoque/departamentos/listar.php', function(data) {
                if (data.status === 'ok') {
                    let html = '<option value="">Selecione...</option>';
                    data.departamentos.forEach(d => {
                        html += `<option value="${d.id}">${d.nome}</option>`;
                    });
                    $('#departamento-origem').html(html);
                }
            });
        }

        function selecionarPrioridade(prioridade) {
            $('.prioridade-badge').removeClass('selected');
            $(`.prioridade-badge[data-prioridade="${prioridade}"]`).addClass('selected');
            $('#prioridade').val(prioridade);
            $('#prioridade-texto').text(prioridade.charAt(0).toUpperCase() + prioridade.slice(1));
        }

        function enviarRequisicao() {
            const solicitacaoTexto = $('#solicitacao-texto').val().trim();

            if (solicitacaoTexto.length < 5) {
                exibirToast('Descreva sua solicitação com um pouco mais de detalhe', 'warning');
                return;
            }

            $('#btn-enviar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Enviando...');

            $.ajax({
                url: baseUrl + '/api/estoque/requisicoes/criar.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    id_departamento_origem: $('#departamento-origem').val() || null,
                    finalidade: $('#finalidade').val(),
                    prioridade: $('#prioridade').val(),
                    data_necessidade: $('#data-necessidade').val() || null,
                    motivo: $('#motivo').val(),
                    solicitacao_texto: solicitacaoTexto
                }),
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        exibirToast(data.mensagem, 'success');
                        setTimeout(function() { window.location.href = 'index.php'; }, 1500);
                    } else {
                        exibirToast(data.mensagem, 'danger');
                        $('#btn-enviar').prop('disabled', false).html('<i class="bi bi-send me-2"></i>Enviar Solicitação');
                    }
                },
                error: function() {
                    exibirToast('Erro ao enviar requisição', 'danger');
                    $('#btn-enviar').prop('disabled', false).html('<i class="bi bi-send me-2"></i>Enviar Solicitação');
                }
            });
        }
    </script>
</body>
</html>
