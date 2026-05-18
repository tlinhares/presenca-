<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('gestao_reservas');

require_once __DIR__ . '/../api/conexao.php';

$isAdmin = $_SESSION['usuario_categoria'] === 'admin';
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Reservas - Sistema de Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/feedback-system.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .header-page {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
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
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 1.25rem;
            text-align: center;
        }
        .stat-card h3 {
            margin: 0;
            font-size: 2rem;
            font-weight: bold;
        }
        .stat-card small {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-page">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="mb-1"><i class="bi bi-calendar-check me-2"></i>Gestão de Reservas</h3>
                    <small class="opacity-75">Gerencie todas as reservas de almoço do sistema</small>
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
        <!-- Filtros -->
        <div class="content-card">
            <h5 class="mb-3"><i class="bi bi-funnel me-2"></i>Filtros</h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="dataInicio" class="form-label">Data Início</label>
                    <input type="date" class="form-control" id="dataInicio" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-md-3">
                    <label for="dataFim" class="form-label">Data Fim</label>
                    <input type="date" class="form-control" id="dataFim" value="<?= date('Y-m-t') ?>">
                </div>
                <div class="col-md-4">
                    <label for="filtroUsuario" class="form-label">Usuário (opcional)</label>
                    <input type="text" class="form-control" id="filtroUsuario" placeholder="Buscar por nome ou email">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" onclick="carregarReservas()">
                        <i class="bi bi-search me-1"></i>Buscar
                    </button>
                </div>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="stat-card">
                    <h6 class="text-muted mb-2">Total de Reservas</h6>
                    <h3 class="text-primary" id="totalReservas">-</h3>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card">
                    <h6 class="text-muted mb-2">Valor Total</h6>
                    <h3 class="text-success" id="valorTotal">R$ 0,00</h3>
                </div>
            </div>
        </div>

        <!-- Tabela de Reservas -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Lista de Reservas</h5>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-sm btn-outline-primary" id="btnMarcarTodos" onclick="marcarTodos()">
                        <i class="bi bi-check-square me-1"></i>Marcar Todos
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" id="btnDesmarcarTodos" onclick="desmarcarTodos()">
                        <i class="bi bi-square me-1"></i>Desmarcar Todos
                    </button>
                    <button class="btn btn-sm btn-danger" id="btnExcluirSelecionados" onclick="excluirSelecionados()" disabled>
                        <i class="bi bi-trash me-1"></i>Excluir Selecionados (<span id="contadorSelecionados">0</span>)
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="tabelaReservas">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="checkTodos" class="form-check-input" onchange="toggleTodos(this)">
                            </th>
                            <th>Data</th>
                            <th>Usuário</th>
                            <th>Tipo</th>
                            <th>Dependente</th>
                            <th>Valor</th>
                            <th>Horário</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyReservas">
                        <tr>
                            <td colspan="8" class="text-center py-4">
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

    <!-- Modal de Confirmação -->
    <div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-labelledby="modalConfirmacaoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalConfirmacaoLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Confirmar Exclusão
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-question-circle-fill text-danger" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-0" id="modalConfirmacaoTexto">
                                Tem certeza que deseja excluir esta reserva? Esta ação não pode ser desfeita.
                            </p>
                            <div id="modalConfirmacaoDetalhes" class="mt-3" style="display: none;">
                                <ul class="list-unstyled mb-0" id="listaReservasExcluir"></ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarExclusao">
                        <i class="bi bi-trash me-1"></i>
                        Excluir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/feedback-system.js?v=<?php echo time(); ?>"></script>
    <script>
        let reservaParaExcluir = null;
        let reservasParaExcluir = [];
        let modalConfirmacao = null;
        let todasReservas = [];

        $(document).ready(function() {
            // Inicializar modal Bootstrap 5
            const modalElement = document.getElementById('modalConfirmacao');
            if (modalElement) {
                modalConfirmacao = new bootstrap.Modal(modalElement);
            }
            
            carregarReservas();
            
            // Registrar evento de clique no botão de confirmar exclusão
            $('#btnConfirmarExclusao').on('click', function() {
                if (reservasParaExcluir.length > 0) {
                    // Exclusão em massa
                    excluirReservasEmMassa();
                } else if (reservaParaExcluir) {
                    // Exclusão individual
                    excluirReservaIndividual();
                }
            });
        });

        function carregarReservas() {
            const dataInicio = $('#dataInicio').val();
            const dataFim = $('#dataFim').val();
            
            const params = {
                data_inicio: dataInicio,
                data_fim: dataFim
            };

            $.ajax({
                url: '../api/almoco/listar_todas_reservas.php',
                method: 'GET',
                data: params,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        let reservas = response.reservas;
                        
                        // Filtrar por nome/email se houver filtro
                        const filtroUsuario = $('#filtroUsuario').val().trim().toLowerCase();
                        if (filtroUsuario) {
                            reservas = reservas.filter(function(reserva) {
                                const nome = (reserva.usuario_nome || '').toLowerCase();
                                const email = (reserva.usuario_email || '').toLowerCase();
                                return nome.includes(filtroUsuario) || email.includes(filtroUsuario);
                            });
                        }
                        
                        renderizarTabela(reservas);
                        
                        // Recalcular estatísticas com reservas filtradas
                        const valorTotal = reservas.reduce(function(sum, r) {
                            return sum + parseFloat(r.valor_refeicao || 0);
                        }, 0);
                        atualizarEstatisticas({
                            total: reservas.length,
                            valor_total: valorTotal
                        });
                    } else {
                        exibirToast('Erro ao carregar reservas: ' + (response.mensagem || 'Erro desconhecido'), 'danger');
                        $('#tbodyReservas').html('<tr><td colspan="8" class="text-center py-4 text-muted">Erro ao carregar dados</td></tr>');
                    }
                },
                error: function() {
                    exibirToast('Erro de conexão ao carregar reservas', 'danger');
                    $('#tbodyReservas').html('<tr><td colspan="8" class="text-center py-4 text-muted">Erro de conexão</td></tr>');
                }
            });
        }

        function renderizarTabela(reservas) {
            const tbody = $('#tbodyReservas');
            tbody.empty();

            todasReservas = reservas; // Armazenar para uso posterior
            
            if (reservas.length === 0) {
                tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted">Nenhuma reserva encontrada no período selecionado</td></tr>');
                return;
            }

            reservas.forEach(function(reserva) {
                const dataObj = new Date(reserva.data + 'T00:00:00');
                const dataFormatada = dataObj.toLocaleDateString('pt-BR');
                
                const tipoBadge = reserva.tipo_reserva === 'propria' 
                    ? '<span class="badge bg-primary badge-status">Própria</span>'
                    : '<span class="badge bg-info badge-status">Dependente</span>';

                const dependenteNome = reserva.dependente_nome 
                    ? `${reserva.dependente_nome} ${reserva.parentesco ? '(' + reserva.parentesco + ')' : ''}`
                    : '-';

                const valorFormatado = parseFloat(reserva.valor_refeicao || 0).toLocaleString('pt-BR', {
                    style: 'currency',
                    currency: 'BRL'
                });

                const horario = reserva.horario_confirmacao 
                    ? new Date(reserva.horario_confirmacao).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
                    : '-';

                const tr = `
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input check-reserva" 
                                   data-id="${reserva.id}" 
                                   data-tipo="${reserva.tipo_reserva}"
                                   onchange="atualizarContador()">
                        </td>
                        <td><strong>${dataFormatada}</strong></td>
                        <td>
                            <div>${reserva.usuario_nome || '-'}</div>
                            <small class="text-muted">${reserva.usuario_email || ''}</small>
                        </td>
                        <td>${tipoBadge}</td>
                        <td>${dependenteNome}</td>
                        <td>${valorFormatado}</td>
                        <td>${horario}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-danger" onclick="confirmarExclusao(${reserva.id}, '${reserva.tipo_reserva}')" title="Excluir" type="button">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(tr);
            });
            
            // Atualizar contador após renderizar
            atualizarContador();
        }

        function atualizarEstatisticas(estatisticas) {
            $('#totalReservas').text(estatisticas.total || 0);
            $('#valorTotal').text(
                parseFloat(estatisticas.valor_total || 0).toLocaleString('pt-BR', {
                    style: 'currency',
                    currency: 'BRL'
                })
            );
        }

        function confirmarExclusao(id, tipo) {
            console.log('confirmarExclusao chamado:', id, tipo);
            reservaParaExcluir = { id: id, tipo: tipo };
            reservasParaExcluir = [];
            $('#modalConfirmacaoTexto').text(
                `Tem certeza que deseja excluir esta reserva ${tipo === 'propria' ? 'própria' : 'de dependente'}? Esta ação não pode ser desfeita.`
            );
            $('#modalConfirmacaoDetalhes').hide();
            $('#listaReservasExcluir').empty();
            
            if (modalConfirmacao) {
                modalConfirmacao.show();
            } else {
                console.error('Modal não inicializado');
                // Fallback para Bootstrap 4
                $('#modalConfirmacao').modal('show');
            }
        }
        
        function excluirReservaIndividual() {
            if (!reservaParaExcluir) return;

            if (modalConfirmacao) {
                modalConfirmacao.hide();
            }

            $.ajax({
                url: '../api/almoco/excluir_reserva_admin.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    id: reservaParaExcluir.id,
                    tipo: reservaParaExcluir.tipo
                }),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        exibirToast(response.mensagem || 'Reserva excluída com sucesso!', 'success');
                        carregarReservas();
                    } else {
                        exibirToast('Erro ao excluir: ' + (response.mensagem || 'Erro desconhecido'), 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao excluir reserva:', error, xhr.responseText);
                    exibirToast('Erro de conexão ao excluir reserva', 'danger');
                }
            });

            reservaParaExcluir = null;
        }
        
        function excluirReservasEmMassa() {
            if (reservasParaExcluir.length === 0) return;

            if (modalConfirmacao) {
                modalConfirmacao.hide();
            }

            // Desabilitar botão durante processamento
            $('#btnExcluirSelecionados').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Excluindo...');

            $.ajax({
                url: '../api/almoco/excluir_reservas_massa.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    reservas: reservasParaExcluir
                }),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok' || response.status === 'parcial') {
                        let mensagem = response.mensagem || 'Reservas excluídas com sucesso!';
                        if (response.falhas > 0 && response.detalhes_falhas) {
                            mensagem += '\n\nFalhas:\n' + response.detalhes_falhas.join('\n');
                        }
                        exibirToast(mensagem, response.status === 'ok' ? 'success' : 'warning');
                        carregarReservas();
                    } else {
                        let mensagem = 'Erro ao excluir reservas: ' + (response.mensagem || 'Erro desconhecido');
                        if (response.detalhes_falhas) {
                            mensagem += '\n\nDetalhes:\n' + response.detalhes_falhas.join('\n');
                        }
                        exibirToast(mensagem, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao excluir reservas:', error, xhr.responseText);
                    exibirToast('Erro de conexão ao excluir reservas', 'danger');
                },
                complete: function() {
                    // Reabilitar botão
                    atualizarContador();
                    reservasParaExcluir = [];
                }
            });
        }
        
        function marcarTodos() {
            $('.check-reserva').prop('checked', true);
            $('#checkTodos').prop('checked', true);
            atualizarContador();
        }
        
        function desmarcarTodos() {
            $('.check-reserva').prop('checked', false);
            $('#checkTodos').prop('checked', false);
            atualizarContador();
        }
        
        function toggleTodos(checkbox) {
            const checked = checkbox.checked;
            $('.check-reserva').prop('checked', checked);
            atualizarContador();
        }
        
        function atualizarContador() {
            const selecionados = $('.check-reserva:checked');
            const total = selecionados.length;
            
            $('#contadorSelecionados').text(total);
            $('#btnExcluirSelecionados').prop('disabled', total === 0);
            
            // Atualizar checkbox "Marcar Todos"
            const totalCheckboxes = $('.check-reserva').length;
            $('#checkTodos').prop('checked', total > 0 && total === totalCheckboxes);
            
            // Coletar reservas selecionadas
            reservasParaExcluir = [];
            selecionados.each(function() {
                reservasParaExcluir.push({
                    id: $(this).data('id'),
                    tipo: $(this).data('tipo')
                });
            });
        }
        
        function excluirSelecionados() {
            const selecionados = $('.check-reserva:checked');
            
            if (selecionados.length === 0) {
                exibirToast('Nenhuma reserva selecionada', 'warning');
                return;
            }
            
            reservasParaExcluir = [];
            reservaParaExcluir = null;
            
            selecionados.each(function() {
                reservasParaExcluir.push({
                    id: $(this).data('id'),
                    tipo: $(this).data('tipo')
                });
            });
            
            // Preparar mensagem do modal
            let mensagem = `Tem certeza que deseja excluir ${reservasParaExcluir.length} reserva(s) selecionada(s)? Esta ação não pode ser desfeita.`;
            $('#modalConfirmacaoTexto').text(mensagem);
            
            // Mostrar detalhes das reservas
            const lista = $('#listaReservasExcluir');
            lista.empty();
            
            reservasParaExcluir.forEach(function(reserva) {
                const reservaData = todasReservas.find(r => r.id == reserva.id && r.tipo_reserva == reserva.tipo);
                if (reservaData) {
                    const dataObj = new Date(reservaData.data + 'T00:00:00');
                    const dataFormatada = dataObj.toLocaleDateString('pt-BR');
                    const tipoTexto = reserva.tipo === 'propria' ? 'Própria' : 'Dependente';
                    lista.append(`<li><small>${dataFormatada} - ${reservaData.usuario_nome || '-'} (${tipoTexto})</small></li>`);
                }
            });
            
            $('#modalConfirmacaoDetalhes').show();
            
            if (modalConfirmacao) {
                modalConfirmacao.show();
            } else {
                $('#modalConfirmacao').modal('show');
            }
        }

        // Função exibirToast
        function exibirToast(mensagem, tipo = 'success') {
            // Verificar se existe função global do sistema de feedback (feedback-system.js)
            if (typeof window.feedbackSystem !== 'undefined' && typeof window.feedbackSystem.show === 'function') {
                window.feedbackSystem.show(mensagem, tipo, { duration: 4000 });
                return;
            }
            
            // Verificar se existe função global exibirToast de outro sistema (não a local)
            const globalExibirToast = window.exibirToast;
            if (typeof globalExibirToast === 'function' && globalExibirToast !== exibirToast) {
                globalExibirToast(mensagem, tipo);
                return;
            }
            
            // Fallback: criar toast manualmente
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
    </script>
</body>
</html>

