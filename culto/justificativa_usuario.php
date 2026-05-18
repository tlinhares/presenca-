<?php
session_start();
require_once '../auth/verifica_sessao.php';
require_once '../config/timezone.php';

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: culto_justificativa_usuario (acesso_padrao=0, requer_culto=1) ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('culto_justificativa_usuario');

$nome_usuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_id = $_SESSION['usuario_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Justificar Faltas - Sistema de Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .falta-card {
            transition: all 0.3s ease;
            border-left: 4px solid #dc3545;
        }
        
        .falta-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .falta-card.justificada {
            border-left-color: #ffc107;
            background-color: #fff3cd;
        }
        
        .falta-card.aprovada {
            border-left-color: #198754;
            background-color: #d4edda;
        }
        
        .falta-card.rejeitada {
            border-left-color: #dc3545;
            background-color: #f8d7da;
        }
        
        .btn-justificar {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-justificar:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .btn-justificar-lote {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            border: none;
            color: white;
        }
        
        .btn-justificar-lote:hover {
            background: linear-gradient(135deg, #1e7e34, #155724);
            transform: translateY(-2px);
        }
        
        .checkbox-falta {
            transform: scale(1.2);
        }
        
        .dias-selecionados {
            background-color: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .dia-selecionado {
            display: inline-block;
            background-color: #2196f3;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            margin: 2px;
            font-size: 0.9em;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../resumo.php">
                <i class="bi bi-file-text me-2"></i>Justificar Faltas
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($nome_usuario) ?>
                </span>
                <a href="../resumo.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-house-door me-1"></i>Resumo
                </a>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="mb-1">
                    <i class="bi bi-file-text me-2"></i>Justificar Faltas de Culto
                </h2>
                <p class="text-muted mb-0">Selecione os dias com falta e justifique suas ausências</p>
            </div>
        </div>

        <!-- Filtro de Datas -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-funnel me-2"></i>Filtro de Período
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label for="data_inicio" class="form-label">Data Inicial</label>
                                <input type="date" class="form-control" id="data_inicio" name="data_inicio">
                            </div>
                            <div class="col-md-3">
                                <label for="data_fim" class="form-label">Data Final</label>
                                <input type="date" class="form-control" id="data_fim" name="data_fim">
                            </div>
                            <div class="col-md-2">
                                <label for="filtro_rapido" class="form-label">Filtro Rápido</label>
                                <select class="form-select" id="filtro_rapido">
                                    <option value="">Selecione um período</option>
                                    <option value="hoje">Hoje</option>
                                    <option value="semana">Esta Semana</option>
                                    <option value="mes_atual">Mês Atual</option>
                                    <option value="mes_anterior">Mês Anterior</option>
                                    <option value="ultimos_3_meses">Últimos 3 Meses</option>
                                    <option value="ultimos_6_meses">Últimos 6 Meses</option>
                                    <option value="ano_atual">Ano Atual</option>
                                    <option value="todos" selected>Todos os Períodos</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="filtro_status" class="form-label">Status</label>
                                <select class="form-select" id="filtro_status">
                                    <option value="">Todos</option>
                                    <option value="falta">Faltas</option>
                                    <option value="pendente">Pendentes</option>
                                    <option value="aprovada">Aprovadas</option>
                                    <option value="rejeitada">Rejeitadas</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary me-2" id="btn-filtrar">
                                    <i class="bi bi-search me-1"></i>Filtrar
                                </button>
                                <button class="btn btn-outline-secondary" id="btn-limpar-filtro">
                                    <i class="bi bi-x-circle me-1"></i>Limpar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="row mb-4">
            <div class="col">
                <div class="card text-center border-danger">
                    <div class="card-body py-3">
                        <i class="bi bi-x-circle text-danger fs-2"></i>
                        <h6 class="card-title mt-2 mb-1">Faltas</h6>
                        <h4 class="text-danger mb-0" id="total-faltas">0</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-center border-warning">
                    <div class="card-body py-3">
                        <i class="bi bi-clock-history text-warning fs-2"></i>
                        <h6 class="card-title mt-2 mb-1">Pendentes</h6>
                        <h4 class="text-warning mb-0" id="total-pendentes">0</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-center border-success">
                    <div class="card-body py-3">
                        <i class="bi bi-check-circle text-success fs-2"></i>
                        <h6 class="card-title mt-2 mb-1">Aprovadas</h6>
                        <h4 class="text-success mb-0" id="total-aprovadas">0</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-center border-secondary">
                    <div class="card-body py-3">
                        <i class="bi bi-x-octagon text-secondary fs-2"></i>
                        <h6 class="card-title mt-2 mb-1">Rejeitadas</h6>
                        <h4 class="text-secondary mb-0" id="total-rejeitadas">0</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-center border-info">
                    <div class="card-body py-3">
                        <i class="bi bi-list-ul text-info fs-2"></i>
                        <h6 class="card-title mt-2 mb-1">Total</h6>
                        <h4 class="text-info mb-0" id="total-geral">0</h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Legenda -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-info-circle me-2"></i>Legenda dos Tipos de Falta
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-secondary me-2">Implícita</span>
                                    <small class="text-muted">Falta automática: Usuário não tem presença registrada em dia de culto</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-info me-2">Explícita</span>
                                    <small class="text-muted">Falta registrada: Usuário tem presença registrada com status 'falta'</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dias Selecionados para Justificação em Lote -->
        <div id="dias-selecionados" class="dias-selecionados" style="display: none;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-2">
                        <i class="bi bi-calendar-check me-2"></i>Dias Selecionados para Justificação em Lote
                    </h6>
                    <div id="lista-dias-selecionados"></div>
                </div>
                <div>
                    <button class="btn btn-justificar-lote me-2" id="btn-justificar-lote">
                        <i class="bi bi-send me-1"></i>Justificar Selecionados
                    </button>
                    <button class="btn btn-outline-secondary" id="btn-limpar-selecao">
                        <i class="bi bi-x-circle me-1"></i>Limpar Seleção
                    </button>
                </div>
            </div>
        </div>

        <!-- Lista de Faltas -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                <i class="bi bi-list-ul me-2"></i>Suas Faltas de Culto
                            </h5>
                            <small class="text-muted" id="filtro-ativo" style="display: none;">
                                <i class="bi bi-funnel me-1"></i>Filtro ativo
                            </small>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary" id="btn-selecionar-todos">
                                <i class="bi bi-check-square me-1"></i>Selecionar Todas
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" id="btn-desmarcar-todas">
                                <i class="bi bi-square me-1"></i>Desmarcar Todas
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="lista-faltas">
                            <!-- Faltas serão carregadas aqui -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Justificação Individual -->
    <div class="modal fade" id="modalJustificativaIndividual" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-file-text me-2"></i>Justificar Falta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formJustificativaIndividual">
                        <input type="hidden" id="data_falta_individual" name="data_falta">
                        
                        <div class="mb-3">
                            <label class="form-label">Data da Falta:</label>
                            <input type="text" class="form-control" id="data_falta_display" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="motivo_individual" class="form-label">Motivo da Falta *</label>
                            <select class="form-select" id="motivo_individual" name="motivo" required>
                                <option value="">Selecione o motivo</option>
                                <option value="doenca">Doença</option>
                                <option value="viagem">Viagem</option>
                                <option value="atendimento_medico">Atendimento Médico</option>
                                <option value="atendimento_juridico">Atendimento Jurídico</option>
                                <option value="emergencia_familiar">Emergência Familiar</option>
                                <option value="compromisso_trabalho">Compromisso de Trabalho</option>
                                <option value="problema_transporte">Problema de Transporte</option>
                                <option value="outros">Outros</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="observacoes_individual" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoes_individual" name="observacoes" rows="3" 
                                      placeholder="Adicione detalhes sobre sua falta..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-justificar" id="btn-confirmar-individual">
                        <i class="bi bi-send me-1"></i>Enviar Justificativa
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Justificação em Lote -->
    <div class="modal fade" id="modalJustificativaLote" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-file-text me-2"></i>Justificar Faltas em Lote
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formJustificativaLote">
                        <div class="mb-3">
                            <label class="form-label">Dias Selecionados:</label>
                            <div id="dias-lote-display" class="dias-selecionados"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="motivo_lote" class="form-label">Motivo da Falta *</label>
                            <select class="form-select" id="motivo_lote" name="motivo" required>
                                <option value="">Selecione o motivo</option>
                                <option value="doenca">Doença</option>
                                <option value="viagem">Viagem</option>
                                <option value="atendimento_medico">Atendimento Médico</option>
                                <option value="atendimento_juridico">Atendimento Jurídico</option>
                                <option value="emergencia_familiar">Emergência Familiar</option>
                                <option value="compromisso_trabalho">Compromisso de Trabalho</option>
                                <option value="problema_transporte">Problema de Transporte</option>
                                <option value="outros">Outros</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="observacoes_lote" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoes_lote" name="observacoes" rows="3" 
                                      placeholder="Adicione detalhes sobre suas faltas..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-justificar-lote" id="btn-confirmar-lote">
                        <i class="bi bi-send me-1"></i>Enviar Justificativas
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/feedback-system.js?v=<?= time() ?>"></script>
    <script>
        // Função de notificação personalizada
        function exibirToast(mensagem, tipo = 'success') {
            // Verificar se o sistema de feedback está disponível
            if (typeof window.feedbackSystem !== 'undefined') {
                // Usar o sistema de feedback moderno
                const duration = tipo === 'info' ? 2000 : 4000; // Carregamento mais rápido
                window.feedbackSystem.show(mensagem, tipo, { duration: duration });
            } else if (typeof window.exibirToast !== 'undefined') {
                // Usar função global de fallback
                window.exibirToast(mensagem, tipo);
            } else {
                // Fallback simples
                console.log(`[${tipo.toUpperCase()}] ${mensagem}`);
                
                // Criar toast simples se jQuery estiver disponível
                if (typeof $ !== 'undefined') {
                    const alertClass = tipo === 'success' ? 'alert-success' : 
                                     tipo === 'danger' ? 'alert-danger' : 
                                     tipo === 'warning' ? 'alert-warning' : 'alert-info';
                    
                    // Duração baseada no tipo
                    const duration = tipo === 'info' ? 2000 : 4000;
                    
                    const toast = $(`
                        <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                            <div class="d-flex align-items-center">
                                <i class="bi ${tipo === 'info' ? 'bi-hourglass-split' : 
                                              tipo === 'success' ? 'bi-check-circle-fill' : 
                                              tipo === 'danger' ? 'bi-exclamation-triangle-fill' : 
                                              'bi-info-circle-fill'} me-2"></i>
                                ${mensagem}
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                    
                    $('body').append(toast);
                    
                    // Auto-remove após duração específica
                    setTimeout(() => {
                        toast.alert('close');
                    }, duration);
                }
            }
        }

        let faltas = [];
        let diasSelecionados = [];
        let filtroAtual = {
            data_inicio: '',
            data_fim: '',
            filtro_rapido: 'mes_atual',
            filtro_status: ''
        };

        // Carregar dados iniciais
        $(document).ready(function() {
            configurarFiltroInicial();
            carregarFaltas();
            configurarEventos();
        });

        // Configurar filtro inicial
        function configurarFiltroInicial() {
            // Iniciar sem filtros para mostrar todos os dados
            $('#data_inicio').val('');
            $('#data_fim').val('');
            $('#filtro_rapido').val('todos');
            $('#filtro_status').val('');
            
            filtroAtual.data_inicio = '';
            filtroAtual.data_fim = '';
            filtroAtual.filtro_rapido = 'todos';
            filtroAtual.filtro_status = '';
        }

        // Configurar eventos
        function configurarEventos() {
            // Filtro rápido
            $('#filtro_rapido').change(function() {
                const filtro = $(this).val();
                aplicarFiltroRapido(filtro);
            });

            // Filtro de status
            $('#filtro_status').change(function() {
                const status = $(this).val();
                filtroAtual.filtro_status = status;
                carregarFaltas();
            });

            // Botões de filtro
            $('#btn-filtrar').click(function() {
                aplicarFiltroManual();
            });

            $('#btn-limpar-filtro').click(function() {
                limparFiltro();
            });

            // Seleção de faltas
            $(document).on('change', '.checkbox-falta', function() {
                const dataFalta = $(this).data('data');
                if ($(this).is(':checked')) {
                    if (!diasSelecionados.includes(dataFalta)) {
                        diasSelecionados.push(dataFalta);
                    }
                } else {
                    diasSelecionados = diasSelecionados.filter(dia => dia !== dataFalta);
                }
                atualizarDiasSelecionados();
            });

            // Botões de seleção
            $('#btn-selecionar-todos').click(function() {
                // Primeiro, desmarcar todos os checkboxes
                $('.checkbox-falta').prop('checked', false);
                
                // Depois, marcar apenas os checkboxes que correspondem a faltas sem justificativa
                $('.checkbox-falta').each(function() {
                    const dataFalta = $(this).data('data');
                    const falta = faltas.find(f => f.data === dataFalta);
                    
                    // Marcar apenas se não tem justificativa ou se o status é 'falta'
                    if (falta && (!falta.status_justificativa || falta.status_justificativa === 'falta')) {
                        $(this).prop('checked', true);
                    }
                });
                
                // Atualizar array de dias selecionados
                diasSelecionados = [];
                $('.checkbox-falta:checked').each(function() {
                    diasSelecionados.push($(this).data('data'));
                });
                
                atualizarDiasSelecionados();
            });

            $('#btn-desmarcar-todas').click(function() {
                $('.checkbox-falta').prop('checked', false);
                diasSelecionados = [];
                atualizarDiasSelecionados();
            });

            // Justificação individual
            $(document).on('click', '.btn-justificar-individual', function() {
                const dataFalta = $(this).data('data');
                $('#data_falta_individual').val(dataFalta);
                $('#data_falta_display').val(formatarData(dataFalta));
                $('#formJustificativaIndividual')[0].reset();
                $('#data_falta_individual').val(dataFalta);
                $('#data_falta_display').val(formatarData(dataFalta));
                $('#modalJustificativaIndividual').modal('show');
            });

            // Justificação em lote
            $('#btn-justificar-lote').click(function() {
                if (diasSelecionados.length === 0) {
                    exibirToast('Selecione pelo menos um dia para justificar.', 'warning');
                    return;
                }
                $('#dias-lote-display').html(diasSelecionados.map(dia => 
                    `<span class="dia-selecionado">${formatarData(dia)}</span>`
                ).join(''));
                $('#formJustificativaLote')[0].reset();
                $('#modalJustificativaLote').modal('show');
            });

            // Limpar seleção
            $('#btn-limpar-selecao').click(function() {
                $('.checkbox-falta').prop('checked', false);
                diasSelecionados = [];
                atualizarDiasSelecionados();
            });

            // Confirmações
            $('#btn-confirmar-individual').click(confirmarJustificativaIndividual);
            $('#btn-confirmar-lote').click(confirmarJustificativaLote);
        }

        // Carregar faltas do usuário
        function carregarFaltas(filtros = null) {
            const parametros = filtros || filtroAtual;
            
            // Mostrar indicador de filtro ativo
            const temFiltro = parametros.data_inicio || parametros.data_fim || parametros.filtro_rapido || parametros.filtro_status;
            if (temFiltro) {
                $('#filtro-ativo').show();
                let textoFiltro = '';
                let partesFiltro = [];
                
                // Filtro de período
                if (parametros.filtro_rapido) {
                    const opcoes = {
                        'hoje': 'Hoje',
                        'semana': 'Esta Semana',
                        'mes_atual': 'Mês Atual',
                        'mes_anterior': 'Mês Anterior',
                        'ultimos_3_meses': 'Últimos 3 Meses',
                        'ultimos_6_meses': 'Últimos 6 Meses',
                        'ano_atual': 'Ano Atual',
                        'todos': 'Todos os Períodos'
                    };
                    partesFiltro.push(opcoes[parametros.filtro_rapido] || '');
                } else if (parametros.data_inicio && parametros.data_fim) {
                    partesFiltro.push(`${formatarData(parametros.data_inicio)} até ${formatarData(parametros.data_fim)}`);
                } else if (parametros.data_inicio) {
                    partesFiltro.push(`A partir de ${formatarData(parametros.data_inicio)}`);
                } else if (parametros.data_fim) {
                    partesFiltro.push(`Até ${formatarData(parametros.data_fim)}`);
                }
                
                // Filtro de status
                if (parametros.filtro_status) {
                    const opcoesStatus = {
                        'falta': 'Faltas',
                        'pendente': 'Pendentes',
                        'aprovada': 'Aprovadas',
                        'rejeitada': 'Rejeitadas'
                    };
                    partesFiltro.push(opcoesStatus[parametros.filtro_status] || '');
                }
                
                textoFiltro = partesFiltro.join(' | ');
                $('#filtro-ativo').html(`<i class="bi bi-funnel me-1"></i>Filtro: ${textoFiltro}`);
            } else {
                $('#filtro-ativo').hide();
            }
            
            console.log('Carregando faltas com parâmetros:', parametros);
            
            $.ajax({
                url: '../api/culto/listar_faltas_usuario.php',
                method: 'GET',
                data: parametros,
                dataType: 'json',
                success: function(response) {
                    console.log('Resposta da API:', response);
                    if (response.status === 'ok') {
                        faltas = response.faltas;
                        atualizarEstatisticas(response.estatisticas);
                        renderizarFaltas(response.faltas);
                    } else {
                        console.error('Erro na API:', response.mensagem);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro AJAX:', status, error);
                    console.error('Resposta do servidor:', xhr.responseText);
                }
            });
        }

        // Aplicar filtro rápido
        function aplicarFiltroRapido(filtro) {
            const hoje = new Date();
            let dataInicio, dataFim;

            switch (filtro) {
                case 'hoje':
                    dataInicio = dataFim = hoje;
                    break;
                case 'semana':
                    const inicioSemana = new Date(hoje);
                    inicioSemana.setDate(hoje.getDate() - hoje.getDay());
                    const fimSemana = new Date(inicioSemana);
                    fimSemana.setDate(inicioSemana.getDate() + 6);
                    dataInicio = inicioSemana;
                    dataFim = fimSemana;
                    break;
                case 'mes_atual':
                    dataInicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
                    dataFim = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
                    break;
                case 'mes_anterior':
                    dataInicio = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1);
                    dataFim = new Date(hoje.getFullYear(), hoje.getMonth(), 0);
                    break;
                case 'ultimos_3_meses':
                    dataInicio = new Date(hoje.getFullYear(), hoje.getMonth() - 3, 1);
                    dataFim = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
                    break;
                case 'ultimos_6_meses':
                    dataInicio = new Date(hoje.getFullYear(), hoje.getMonth() - 6, 1);
                    dataFim = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
                    break;
                case 'ano_atual':
                    dataInicio = new Date(hoje.getFullYear(), 0, 1);
                    dataFim = new Date(hoje.getFullYear(), 11, 31);
                    break;
                case 'todos':
                    dataInicio = null;
                    dataFim = null;
                    break;
                default:
                    return;
            }

            if (dataInicio && dataFim) {
                $('#data_inicio').val(formatarDataParaInput(dataInicio));
                $('#data_fim').val(formatarDataParaInput(dataFim));
            } else {
                $('#data_inicio').val('');
                $('#data_fim').val('');
            }

            filtroAtual.data_inicio = dataInicio ? formatarDataParaInput(dataInicio) : '';
            filtroAtual.data_fim = dataFim ? formatarDataParaInput(dataFim) : '';
            filtroAtual.filtro_rapido = filtro;

            carregarFaltas();
        }

        // Aplicar filtro manual
        function aplicarFiltroManual() {
            const dataInicio = $('#data_inicio').val();
            const dataFim = $('#data_fim').val();
            const status = $('#filtro_status').val();

            if (dataInicio && dataFim && dataInicio > dataFim) {
                exibirToast('A data inicial não pode ser maior que a data final.', 'warning');
                return;
            }

            filtroAtual.data_inicio = dataInicio;
            filtroAtual.data_fim = dataFim;
            filtroAtual.filtro_rapido = '';
            filtroAtual.filtro_status = status;

            carregarFaltas();
        }

        // Limpar filtro
        function limparFiltro() {
            $('#data_inicio').val('');
            $('#data_fim').val('');
            $('#filtro_rapido').val('');
            $('#filtro_status').val('');

            filtroAtual.data_inicio = '';
            filtroAtual.data_fim = '';
            filtroAtual.filtro_rapido = '';
            filtroAtual.filtro_status = '';

            carregarFaltas();
        }

        // Formatar data para input date
        function formatarDataParaInput(data) {
            return data.toISOString().split('T')[0];
        }

        // Atualizar estatísticas
        function atualizarEstatisticas(stats) {
            $('#total-faltas').text(stats.faltas || 0);
            $('#total-pendentes').text(stats.pendentes || 0);
            $('#total-aprovadas').text(stats.aprovadas || 0);
            $('#total-rejeitadas').text(stats.rejeitadas || 0);
            $('#total-geral').text(stats.total || 0);
        }

        // Renderizar lista de faltas
        function renderizarFaltas(faltas) {
            const container = $('#lista-faltas');
            
            if (faltas.length === 0) {
                container.html(`
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-check-circle fs-1"></i>
                        <p class="mt-3">Nenhuma falta encontrada</p>
                        <small>Parabéns! Você não tem faltas para justificar</small>
                    </div>
                `);
                return;
            }

            let html = '';
            faltas.forEach(function(falta) {
                const statusClass = falta.status_justificativa || 'falta';
                const statusText = {
                    'falta': 'Falta',
                    'pendente': 'Justificativa Pendente',
                    'aprovada': 'Justificativa Aprovada',
                    'rejeitada': 'Justificativa Rejeitada'
                }[statusClass] || 'Falta';

                const motivoText = falta.motivo ? {
                    'doenca': 'Doença',
                    'viagem': 'Viagem',
                    'atendimento_medico': 'Atendimento Médico',
                    'atendimento_juridico': 'Atendimento Jurídico',
                    'emergencia_familiar': 'Emergência Familiar',
                    'compromisso_trabalho': 'Compromisso de Trabalho',
                    'problema_transporte': 'Problema de Transporte',
                    'outros': 'Outros'
                }[falta.motivo] || falta.motivo : '';

                // Indicador de tipo de falta
                const tipoFaltaBadge = falta.tipo_falta ? 
                    `<span class="badge bg-${falta.tipo_falta === 'implícita' ? 'secondary' : 'info'} me-1">
                        ${falta.tipo_falta === 'implícita' ? 'Implícita' : 'Explícita'}
                    </span>` : '';

                html += `
                    <div class="falta-card card mb-3 status-${statusClass}">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-1">
                                    ${statusClass === 'falta' ? 
                                        `<input type="checkbox" class="form-check-input checkbox-falta" data-data="${falta.data}">` : 
                                        '<i class="bi bi-check-circle text-success"></i>'
                                    }
                                </div>
                                <div class="col-md-2">
                                    <strong>${formatarData(falta.data)}</strong>
                                    ${tipoFaltaBadge}
                                </div>
                                <div class="col-md-2">
                                    <span class="badge bg-${statusClass === 'falta' ? 'danger' : statusClass === 'pendente' ? 'warning' : statusClass === 'aprovada' ? 'success' : 'danger'}">
                                        ${statusText}
                                    </span>
                                </div>
                                <div class="col-md-3">
                                    ${motivoText ? `<small class="text-muted">${motivoText}</small>` : ''}
                                </div>
                                <div class="col-md-2">
                                    ${falta.observacoes ? `<small class="text-muted">${falta.observacoes}</small>` : ''}
                                </div>
                                <div class="col-md-2">
                                    ${statusClass === 'falta' ? 
                                        `<button class="btn btn-sm btn-justificar btn-justificar-individual" data-data="${falta.data}">
                                            <i class="bi bi-file-text me-1"></i>Justificar
                                        </button>` : 
                                        '<small class="text-muted">Já justificada</small>'
                                    }
                                </div>
                            </div>
                            ${falta.observacoes_admin ? `
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <div class="alert alert-info">
                                            <strong>Observação do Administrador:</strong><br>
                                            ${falta.observacoes_admin}
                                        </div>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            });

            container.html(html);
        }

        // Atualizar dias selecionados
        function atualizarDiasSelecionados() {
            const container = $('#dias-selecionados');
            const lista = $('#lista-dias-selecionados');
            
            if (diasSelecionados.length === 0) {
                container.hide();
                return;
            }
            
            container.show();
            lista.html(diasSelecionados.map(dia => 
                `<span class="dia-selecionado">${formatarData(dia)}</span>`
            ).join(''));
        }

        // Confirmar justificativa individual
        function confirmarJustificativaIndividual() {
            const formData = {
                data_falta: $('#data_falta_individual').val(),
                motivo: $('#motivo_individual').val(),
                observacoes: $('#observacoes_individual').val()
            };

            if (!formData.motivo) {
                exibirToast('Por favor, selecione o motivo da falta.', 'warning');
                return;
            }

            enviarJustificativa(formData, function() {
                $('#modalJustificativaIndividual').modal('hide');
                carregarFaltas();
            });
        }

        // Confirmar justificativa em lote
        function confirmarJustificativaLote() {
            const formData = {
                datas_falta: diasSelecionados,
                motivo: $('#motivo_lote').val(),
                observacoes: $('#observacoes_lote').val()
            };

            if (!formData.motivo) {
                exibirToast('Por favor, selecione o motivo da falta.', 'warning');
                return;
            }

            enviarJustificativaLote(formData, function() {
                $('#modalJustificativaLote').modal('hide');
                diasSelecionados = [];
                atualizarDiasSelecionados();
                carregarFaltas();
            });
        }

        // Enviar justificativa individual
        function enviarJustificativa(formData, callback) {
            $.ajax({
                url: '../api/culto/enviar_justificativa.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        exibirToast(response.mensagem, 'success');
                        if (callback) callback();
                    } else {
                        exibirToast('Erro: ' + response.mensagem, 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro ao enviar justificativa. Tente novamente.', 'danger');
                }
            });
        }

        // Enviar justificativa em lote
        function enviarJustificativaLote(formData, callback) {
            $.ajax({
                url: '../api/culto/enviar_justificativa_lote.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        exibirToast(response.mensagem, 'success');
                        if (callback) callback();
                    } else {
                        exibirToast('Erro: ' + response.mensagem, 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro ao enviar justificativas. Tente novamente.', 'danger');
                }
            });
        }

        // Formatar data
        function formatarData(data) {
            return new Date(data + 'T00:00:00').toLocaleDateString('pt-BR');
        }
    </script>
</body>
</html>
