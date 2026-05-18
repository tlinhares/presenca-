<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('gerenciar_valores_refeicoes');

require_once __DIR__ . '/../api/conexao.php';

$isAdmin = $_SESSION['usuario_categoria'] === 'admin';
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valores de Refeições - Sistema de Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/feedback-system.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .header-page {
            background: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        .badge-status {
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
        }
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 1.5rem;
        }
        .valor-destaque {
            font-size: 1.1rem;
            font-weight: 600;
            color: #198754;
        }
    </style>
</head>
<body>
    <div class="header-page">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="mb-1"><i class="bi bi-cash-coin me-2"></i>Valores de Refeições</h3>
                    <small class="opacity-75">Gerencie os valores cobrados por grupo/tipo de refeição</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">Lista de Valores</h5>
                <button class="btn btn-primary" onclick="abrirModalNovo()">
                    <i class="bi bi-plus-circle me-1"></i> Novo Valor
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="tabelaGrupoValor">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Descrição</th>
                            <th>Valor (R$)</th>
                            <th>Criado em</th>
                            <th>Atualizado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyGrupoValor">
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Novo/Editar -->
    <div class="modal fade" id="modalGrupoValor" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Novo Valor de Refeição</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formGrupoValor">
                        <input type="hidden" id="id" name="id">
                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="descricao" name="descricao" placeholder="Ex: Escritório, Colégio, Evento" required>
                        </div>
                        <div class="mb-3">
                            <label for="valor" class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" class="form-control" id="valor" name="valor" step="0.01" min="0" placeholder="0,00" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarGrupoValor()">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação -->
    <div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Confirmar Ação
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-question-circle-fill text-warning" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-0" id="modalConfirmacaoTexto">
                                Tem certeza que deseja realizar esta ação?
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarAcao">
                        <i class="bi bi-check-circle me-1"></i>Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/feedback-system.js?v=<?php echo time(); ?>"></script>
    <script>
        const modal = new bootstrap.Modal(document.getElementById('modalGrupoValor'));

        $(document).ready(function() {
            carregarGrupoValor();
        });

        function carregarGrupoValor() {
            $.ajax({
                url: '../api/grupo_valor/listar.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        renderizarTabela(response.itens);
                    } else {
                        exibirToast('Erro ao carregar valores: ' + (response.mensagem || 'Erro desconhecido'), 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro de conexão ao carregar valores', 'danger');
                }
            });
        }

        function renderizarTabela(itens) {
            const tbody = $('#tbodyGrupoValor');
            tbody.empty();

            if (itens.length === 0) {
                tbody.html('<tr><td colspan="6" class="text-center py-4 text-muted">Nenhum valor cadastrado</td></tr>');
                return;
            }

            itens.forEach(function(item) {
                const valorFormatado = parseFloat(item.valor).toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                const criadoEm = item.criado_em
                    ? new Date(item.criado_em).toLocaleDateString('pt-BR') + ' ' + new Date(item.criado_em).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})
                    : '-';

                const atualizadoEm = item.atualizado_em
                    ? new Date(item.atualizado_em).toLocaleDateString('pt-BR') + ' ' + new Date(item.atualizado_em).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})
                    : '-';

                const tr = `
                    <tr>
                        <td>${item.id}</td>
                        <td><strong>${item.descricao}</strong></td>
                        <td><span class="valor-destaque">R$ ${valorFormatado}</span></td>
                        <td><small class="text-muted">${criadoEm}</small></td>
                        <td><small class="text-muted">${atualizadoEm}</small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editarGrupoValor(${item.id})" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="excluirGrupoValor(${item.id}, '${item.descricao.replace(/'/g, "\\'")}')" title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(tr);
            });
        }

        function abrirModalNovo() {
            $('#formGrupoValor')[0].reset();
            $('#id').val('');
            $('#modalTitulo').text('Novo Valor de Refeição');
            modal.show();
        }

        function editarGrupoValor(id) {
            $.ajax({
                url: '../api/grupo_valor/buscar.php',
                method: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        const item = response.item;
                        $('#id').val(item.id);
                        $('#descricao').val(item.descricao);
                        $('#valor').val(parseFloat(item.valor));
                        $('#modalTitulo').text('Editar Valor de Refeição');
                        modal.show();
                    } else {
                        exibirToast('Erro ao buscar registro: ' + (response.mensagem || 'Erro desconhecido'), 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro de conexão ao buscar registro', 'danger');
                }
            });
        }

        function salvarGrupoValor() {
            const formData = {
                id: $('#id').val(),
                descricao: $('#descricao').val().trim(),
                valor: parseFloat($('#valor').val()) || 0
            };

            if (!formData.descricao) {
                exibirToast('Por favor, informe a descrição', 'warning');
                return;
            }

            if (formData.valor < 0) {
                exibirToast('O valor não pode ser negativo', 'warning');
                return;
            }

            $.ajax({
                url: '../api/grupo_valor/salvar.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(formData),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        modal.hide();
                        carregarGrupoValor();
                        exibirToast(response.mensagem || 'Salvo com sucesso!', 'success');
                    } else {
                        exibirToast('Erro ao salvar: ' + (response.mensagem || 'Erro desconhecido'), 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro de conexão ao salvar', 'danger');
                }
            });
        }

        function excluirGrupoValor(id, descricao) {
            $('#modalConfirmacaoTexto').text('Tem certeza que deseja excluir o valor "' + descricao + '"? Esta ação não pode ser desfeita.');
            $('#modalConfirmacao').modal('show');

            $('#btnConfirmarAcao').off('click').on('click', function() {
                $('#modalConfirmacao').modal('hide');

                $.ajax({
                    url: '../api/grupo_valor/excluir.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ id: id }),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'ok') {
                            carregarGrupoValor();
                            exibirToast(response.mensagem || 'Excluído com sucesso!', 'success');
                        } else {
                            exibirToast('Erro ao excluir: ' + (response.mensagem || 'Erro desconhecido'), 'danger');
                        }
                    },
                    error: function() {
                        exibirToast('Erro de conexão ao excluir', 'danger');
                    }
                });
            });
        }

        function exibirToast(mensagem, tipo = 'success') {
            if (typeof window.feedbackSystem !== 'undefined') {
                window.feedbackSystem.show(mensagem, tipo, { duration: 4000 });
            } else if (typeof window.exibirToast !== 'undefined') {
                window.exibirToast(mensagem, tipo);
            } else {
                const alertClass = tipo === 'success' ? 'alert-success' :
                                 tipo === 'danger' ? 'alert-danger' :
                                 tipo === 'warning' ? 'alert-warning' : 'alert-info';

                const toast = $(`
                    <div class="alert ${alertClass} alert-dismissible fade show position-fixed"
                         style="top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                        <div class="d-flex align-items-center">
                            <i class="bi ${tipo === 'success' ? 'bi-check-circle-fill' :
                                          tipo === 'danger' ? 'bi-exclamation-triangle-fill' :
                                          tipo === 'warning' ? 'bi-exclamation-triangle-fill' :
                                          'bi-info-circle-fill'} me-2"></i>
                            ${mensagem}
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);

                $('body').append(toast);

                setTimeout(() => {
                    toast.alert('close');
                }, 4000);
            }
        }
    </script>
</body>
</html>
