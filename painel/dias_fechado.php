<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('gerenciamento_dias_fechado');

require_once __DIR__ . '/../api/conexao.php';

$isAdmin = $_SESSION['usuario_categoria'] === 'admin';
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dias Fechado do Refeitório - Sistema de Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/feedback-system.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .header-page {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-page">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="mb-1"><i class="bi bi-calendar-x me-2"></i>Dias Fechado do Refeitório</h3>
                    <small class="opacity-75">Gerencie as datas em que o refeitório não funcionará</small>
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
                <h5 class="mb-0">Lista de Dias Fechados</h5>
                <button class="btn btn-primary" onclick="abrirModalNovo()">
                    <i class="bi bi-plus-circle me-1"></i> Novo Dia Fechado
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="tabelaDiasFechado">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Dia da Semana</th>
                            <th>Motivo</th>
                            <th>Observações</th>
                            <th>Status</th>
                            <th>Criado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyDiasFechado">
                        <tr>
                            <td colspan="7" class="text-center py-4">
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
    <div class="modal fade" id="modalDiaFechado" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Novo Dia Fechado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formDiaFechado">
                        <input type="hidden" id="id" name="id">
                        <div class="mb-3">
                            <label for="data" class="form-label">Data <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="data" name="data" required>
                        </div>
                        <div class="mb-3">
                            <label for="motivo" class="form-label">Motivo</label>
                            <input type="text" class="form-control" id="motivo" name="motivo" placeholder="Ex: Feriado, Manutenção">
                        </div>
                        <div class="mb-3">
                            <label for="observacoes" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="ativo" name="ativo" checked>
                                <label class="form-check-label" for="ativo">Ativo</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarDiaFechado()">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação Personalizado -->
    <div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-labelledby="modalConfirmacaoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="modalConfirmacaoLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Confirmar Ação
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
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
                        <i class="bi bi-x-circle me-1"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarAcao">
                        <i class="bi bi-check-circle me-1"></i>
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/feedback-system.js?v=<?php echo time(); ?>"></script>
    <script>
        const modal = new bootstrap.Modal(document.getElementById('modalDiaFechado'));
        
        $(document).ready(function() {
            carregarDiasFechado();
        });

        function carregarDiasFechado() {
            $.ajax({
                url: '../api/dias_fechado/listar.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        renderizarTabela(response.dias);
                    } else {
                        exibirToast('Erro ao carregar dias fechados: ' + (response.mensagem || 'Erro desconhecido'), 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro de conexão ao carregar dias fechados', 'danger');
                }
            });
        }

        function renderizarTabela(dias) {
            const tbody = $('#tbodyDiasFechado');
            tbody.empty();

            if (dias.length === 0) {
                tbody.html('<tr><td colspan="7" class="text-center py-4 text-muted">Nenhum dia fechado cadastrado</td></tr>');
                return;
            }

            const diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

            dias.forEach(function(dia) {
                const dataObj = new Date(dia.data + 'T00:00:00');
                const diaSemana = diasSemana[dataObj.getDay()];
                const dataFormatada = dataObj.toLocaleDateString('pt-BR');
                
                const statusBadge = dia.ativo 
                    ? '<span class="badge bg-success badge-status">Ativo</span>'
                    : '<span class="badge bg-secondary badge-status">Inativo</span>';

                const criadoEm = dia.criado_em 
                    ? new Date(dia.criado_em).toLocaleDateString('pt-BR')
                    : '-';

                const tr = `
                    <tr>
                        <td><strong>${dataFormatada}</strong></td>
                        <td>${diaSemana}</td>
                        <td>${dia.motivo || '-'}</td>
                        <td>${dia.observacoes ? (dia.observacoes.length > 50 ? dia.observacoes.substring(0, 50) + '...' : dia.observacoes) : '-'}</td>
                        <td>${statusBadge}</td>
                        <td>${criadoEm}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editarDiaFechado(${dia.id})" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="excluirDiaFechado(${dia.id})" title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(tr);
            });
        }

        function abrirModalNovo() {
            $('#formDiaFechado')[0].reset();
            $('#id').val('');
            $('#modalTitulo').text('Novo Dia Fechado');
            $('#ativo').prop('checked', true);
            modal.show();
        }

        function editarDiaFechado(id) {
            $.ajax({
                url: '../api/dias_fechado/buscar.php',
                method: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        const dia = response.dia;
                        $('#id').val(dia.id);
                        $('#data').val(dia.data);
                        $('#motivo').val(dia.motivo || '');
                        $('#observacoes').val(dia.observacoes || '');
                        $('#ativo').prop('checked', dia.ativo == 1);
                        $('#modalTitulo').text('Editar Dia Fechado');
                        modal.show();
                    } else {
                        exibirToast('Erro ao buscar dia fechado: ' + (response.mensagem || 'Erro desconhecido'), 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro de conexão ao buscar dia fechado', 'danger');
                }
            });
        }

        function salvarDiaFechado() {
            const formData = {
                id: $('#id').val(),
                data: $('#data').val(),
                motivo: $('#motivo').val(),
                observacoes: $('#observacoes').val(),
                ativo: $('#ativo').is(':checked') ? 1 : 0
            };

            if (!formData.data) {
                exibirToast('Por favor, informe a data', 'warning');
                return;
            }

            $.ajax({
                url: '../api/dias_fechado/salvar.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(formData),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        modal.hide();
                        carregarDiasFechado();
                        exibirToast(response.mensagem || 'Dia fechado salvo com sucesso!', 'success');
                    } else {
                        exibirToast('Erro ao salvar: ' + (response.mensagem || 'Erro desconhecido'), 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro de conexão ao salvar', 'danger');
                }
            });
        }

        function excluirDiaFechado(id) {
            // Usar modal de confirmação personalizado
            $('#modalConfirmacaoTexto').text('Tem certeza que deseja excluir este dia fechado? Esta ação não pode ser desfeita.');
            $('#modalConfirmacao').modal('show');
            
            $('#btnConfirmarAcao').off('click').on('click', function() {
                $('#modalConfirmacao').modal('hide');
                
                $.ajax({
                    url: '../api/dias_fechado/excluir.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ id: id }),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'ok') {
                            carregarDiasFechado();
                            exibirToast(response.mensagem || 'Dia fechado excluído com sucesso!', 'success');
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
        
        // Função exibirToast (se não existir globalmente)
        function exibirToast(mensagem, tipo = 'success') {
            // Verificar se o sistema de feedback está disponível
            if (typeof window.feedbackSystem !== 'undefined') {
                window.feedbackSystem.show(mensagem, tipo, { duration: 4000 });
            } else if (typeof window.exibirToast !== 'undefined') {
                window.exibirToast(mensagem, tipo);
            } else {
                // Fallback simples
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

