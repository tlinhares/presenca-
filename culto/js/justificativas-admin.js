// Variável global para armazenar justificativas selecionadas
let justificativasSelecionadas = new Set();

$(document).ready(function() {
    // Debug: Confirmar que o arquivo foi carregado
    console.log('Justificativas Admin JS carregado - Versão corrigida v3.0 (com seleção em lote)');
    
    // Definir datas padrão (últimos 30 dias)
    const hoje = new Date();
    const trintaDiasAtras = new Date();
    trintaDiasAtras.setDate(hoje.getDate() - 30);
    
    $('#filtro-data-fim').val(hoje.toISOString().split('T')[0]);
    $('#filtro-data-inicio').val(trintaDiasAtras.toISOString().split('T')[0]);
    
    carregarJustificativas();
    
    // Eventos
    $('#btn-atualizar').click(carregarJustificativas);
    $('#filtro-status').change(carregarJustificativas);
    $('#filtro-data-inicio, #filtro-data-fim').change(carregarJustificativas);
    $('#btn-limpar-filtros').click(limparFiltros);
    
    // Busca por nome com debounce (aguarda 500ms após parar de digitar)
    $('#filtro-nome').on('input', function() {
        clearTimeout(window.buscaTimeout);
        window.buscaTimeout = setTimeout(carregarJustificativas, 500);
    });
    $('#btn-confirmar-decisao').click(confirmarDecisao);
    
    // Eventos para ações em lote
    $('#btn-aprovar-lote').click(() => abrirModalDecisaoLote('aprovada'));
    $('#btn-rejeitar-lote').click(() => abrirModalDecisaoLote('rejeitada'));
    $('#btn-confirmar-decisao-lote').click(confirmarDecisaoLote);
    $('#selecionar-todas').change(toggleSelecionarTodas);
});

// Função de notificação
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

// Carregar justificativas
function carregarJustificativas() {
    // Mostrar alerta de carregamento
    exibirToast('Carregando justificativas...', 'info');
    
    const filtros = {
        status: $('#filtro-status').val(),
        nome: $('#filtro-nome').val().trim(),
        data_inicio: $('#filtro-data-inicio').val(),
        data_fim: $('#filtro-data-fim').val()
    };
    
    $.ajax({
        url: '../api/culto/listar_justificativas_admin.php',
        type: 'GET',
        data: filtros,
        dataType: 'json',
        success: function(resposta) {
            if (resposta.status === 'ok') {
                exibirJustificativas(resposta.justificativas);
                atualizarEstatisticas(resposta.estatisticas);
                
                // Mostrar alerta de sucesso
                exibirToast('Justificativas carregadas com sucesso!', 'success');
            } else {
                exibirToast('Erro ao carregar justificativas: ' + resposta.mensagem, 'danger');
            }
        },
        error: function() {
            exibirToast('Erro ao carregar justificativas. Tente novamente.', 'danger');
        }
    });
}

// Exibir justificativas na tela
function exibirJustificativas(justificativas) {
    const container = $('#lista-justificativas');
    container.empty();
    
    // Limpar seleções anteriores
    justificativasSelecionadas.clear();
    atualizarBarraAcoesLote();
    $('#selecionar-todas').prop('checked', false);
    
    // Contar pendentes para mostrar/esconder controles de seleção
    const pendentes = justificativas.filter(j => j.status === 'pendente');
    $('#controles-selecao').toggle(pendentes.length > 0);
    
    if (justificativas.length === 0) {
        container.html(`
            <div class="text-center text-muted py-5">
                <i class="bi bi-file-text fs-1"></i>
                <h4 class="mt-3">Nenhuma justificativa encontrada</h4>
                <p>Não há justificativas que correspondam aos filtros selecionados.</p>
            </div>
        `);
        return;
    }
    
    justificativas.forEach(function(justificativa) {
        const statusClass = getStatusClass(justificativa.status);
        const statusIcon = getStatusIcon(justificativa.status);
        const statusText = getStatusText(justificativa.status);
        const motivoText = getMotivoText(justificativa.motivo);
        const isPendente = justificativa.status === 'pendente';
        
        const fotoHtml = justificativa.foto_base64 ? 
            `<img src="${justificativa.foto_base64}" alt="Foto" class="foto-usuario">` :
            `<div class="foto-usuario d-flex align-items-center justify-content-center bg-light"><i class="bi bi-person-fill text-muted"></i></div>`;
        
        // Checkbox para seleção (apenas para pendentes)
        const checkboxHtml = isPendente ? `
            <div class="col-auto pe-0">
                <div class="form-check">
                    <input class="form-check-input checkbox-justificativa" type="checkbox" 
                           value="${justificativa.id}" 
                           id="check-${justificativa.id}"
                           onchange="toggleSelecaoJustificativa(${justificativa.id})">
                </div>
            </div>
        ` : '';
        
        let acoesHtml = '';
        if (isPendente) {
            acoesHtml = `
                <button class="btn btn-primary btn-sm" onclick="abrirModalDecisao(${justificativa.id})">
                    <i class="bi bi-gavel me-1"></i>Decidir
                </button>
            `;
        } else {
            acoesHtml = `
                <span class="badge ${getStatusBadgeClass(justificativa.status)}">
                    ${statusIcon} ${statusText}
                </span>
            `;
        }
        
        const justificativaHtml = `
            <div class="card mb-3 justificativa-card ${statusClass}" data-id="${justificativa.id}" data-status="${justificativa.status}">
                <div class="card-body">
                    <div class="row align-items-center">
                        ${checkboxHtml}
                        <div class="col-md-1">
                            ${fotoHtml}
                        </div>
                        <div class="col-md-3">
                            <h6 class="mb-1 fw-bold">${justificativa.nome_usuario}</h6>
                            <small class="text-muted">${justificativa.email_usuario}</small>
                        </div>
                        <div class="col-md-2">
                            <strong>Data da Falta:</strong><br>
                            <span class="text-primary">${formatarData(justificativa.data_falta)}</span>
                        </div>
                        <div class="col-md-2">
                            <strong>Motivo:</strong><br>
                            <span class="badge motivo-badge bg-secondary">${motivoText}</span>
                        </div>
                        <div class="col-md-2">
                            <strong>Observações:</strong><br>
                            <div class="observacoes-text text-muted">
                                ${justificativa.observacoes || 'Nenhuma observação'}
                            </div>
                        </div>
                        <div class="col-md-1 text-center">
                            ${acoesHtml}
                        </div>
                    </div>
                    
                    ${!isPendente ? `
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <strong>Decidido por:</strong> ${justificativa.nome_admin || 'N/A'}<br>
                                    <strong>Data:</strong> ${justificativa.data_aprovacao ? formatarDataHora(justificativa.data_aprovacao) : 'N/A'}
                                </small>
                            </div>
                            <div class="col-md-6">
                                ${justificativa.observacoes_admin ? `
                                    <small class="text-muted">
                                        <strong>Observações do Admin:</strong><br>
                                        ${justificativa.observacoes_admin}
                                    </small>
                                ` : ''}
                            </div>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
        
        container.append(justificativaHtml);
    });
}

// Atualizar estatísticas
function atualizarEstatisticas(estatisticas) {
    $('#total-pendentes').text(estatisticas.pendentes || 0);
    $('#total-aprovadas').text(estatisticas.aprovadas || 0);
    $('#total-rejeitadas').text(estatisticas.rejeitadas || 0);
    $('#total-geral').text(estatisticas.total || 0);
}

// Limpar filtros
function limparFiltros() {
    $('#filtro-status').val('todos');
    $('#filtro-nome').val('');
    $('#filtro-data-inicio').val('');
    $('#filtro-data-fim').val('');
    carregarJustificativas();
}

// Abrir modal de decisão
function abrirModalDecisao(justificativaId) {
    // Mostrar alerta de carregamento
    exibirToast('Carregando detalhes...', 'info');
    
    $.ajax({
        url: '../api/culto/detalhes_justificativa.php',
        type: 'GET',
        data: { id: justificativaId },
        dataType: 'json',
        success: function(resposta) {
            if (resposta.status === 'ok') {
                exibirDetalhesJustificativa(resposta.justificativa);
                $('#justificativa-id').val(justificativaId);
                $('#modalDecisao').modal('show');
            } else {
                exibirToast('Erro ao carregar detalhes: ' + resposta.mensagem, 'danger');
            }
        },
        error: function() {
            exibirToast('Erro ao carregar detalhes. Tente novamente.', 'danger');
        }
    });
}

// Exibir detalhes da justificativa no modal
function exibirDetalhesJustificativa(justificativa) {
    const motivoText = getMotivoText(justificativa.motivo);
    
    const detalhesHtml = `
        <div class="row">
            <div class="col-md-4">
                <strong>Usuário:</strong><br>
                ${justificativa.nome_usuario}<br>
                <small class="text-muted">${justificativa.email_usuario}</small>
            </div>
            <div class="col-md-4">
                <strong>Data da Falta:</strong><br>
                <span class="text-primary">${formatarData(justificativa.data_falta)}</span>
            </div>
            <div class="col-md-4">
                <strong>Motivo:</strong><br>
                <span class="badge bg-secondary">${motivoText}</span>
            </div>
        </div>
        
        <hr>
        
        <div class="row">
            <div class="col-12">
                <strong>Observações do Usuário:</strong><br>
                <div class="bg-light p-3 rounded mt-2">
                    ${justificativa.observacoes || 'Nenhuma observação fornecida.'}
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <strong>Data de Envio:</strong><br>
                <span class="text-muted">${formatarDataHora(justificativa.data_cadastro)}</span>
            </div>
        </div>
    `;
    
    $('#detalhes-justificativa').html(detalhesHtml);
}

// Confirmar decisão
function confirmarDecisao() {
    const justificativaId = $('#justificativa-id').val();
    const decisao = $('input[name="decisao"]:checked').val();
    const observacoes = $('#observacoes-admin').val();
    
    if (!decisao) {
        exibirToast('Por favor, selecione uma decisão', 'warning');
        return;
    }
    
    // Mostrar alerta de processamento
    exibirToast('Processando decisão...', 'info');
    
    $.ajax({
        url: '../api/culto/decidir_justificativa.php',
        type: 'POST',
        data: {
            justificativa_id: justificativaId,
            decisao: decisao,
            observacoes_admin: observacoes
        },
        dataType: 'json',
        success: function(resposta) {
            if (resposta.status === 'ok') {
                exibirToast(resposta.mensagem, 'success');
                $('#modalDecisao').modal('hide');
                carregarJustificativas();
            } else {
                exibirToast('Erro ao processar decisão: ' + resposta.mensagem, 'danger');
            }
        },
        error: function() {
            exibirToast('Erro ao processar decisão. Tente novamente.', 'danger');
        }
    });
}

// Funções auxiliares
function getStatusClass(status) {
    switch(status) {
        case 'pendente': return 'status-pendente';
        case 'aprovada': return 'status-aprovada';
        case 'rejeitada': return 'status-rejeitada';
        default: return '';
    }
}

function getStatusIcon(status) {
    switch(status) {
        case 'pendente': return '<i class="bi bi-clock"></i>';
        case 'aprovada': return '<i class="bi bi-check-circle"></i>';
        case 'rejeitada': return '<i class="bi bi-x-circle"></i>';
        default: return '<i class="bi bi-question-circle"></i>';
    }
}

function getStatusText(status) {
    switch(status) {
        case 'pendente': return 'Pendente';
        case 'aprovada': return 'Aprovada';
        case 'rejeitada': return 'Rejeitada';
        default: return 'Desconhecido';
    }
}

function getStatusBadgeClass(status) {
    switch(status) {
        case 'pendente': return 'bg-warning';
        case 'aprovada': return 'bg-success';
        case 'rejeitada': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function getMotivoText(motivo) {
    switch(motivo) {
        case 'doenca': return 'Doença';
        case 'viagem': return 'Viagem';
        case 'atendimento_medico': return 'Atendimento Médico';
        case 'atendimento_juridico': return 'Atendimento Jurídico';
        case 'emergencia_familiar': return 'Emergência Familiar';
        case 'compromisso_trabalho': return 'Compromisso de Trabalho';
        case 'problema_transporte': return 'Problema de Transporte';
        case 'outros': return 'Outros';
        // Valores antigos (compatibilidade)
        case 'trabalho': return 'Trabalho';
        case 'familia': return 'Problemas familiares';
        case 'outro': return 'Outro';
        default: return motivo || 'Desconhecido';
    }
}

function formatarData(data) {
    // Garantir que a data seja interpretada corretamente
    const [ano, mes, dia] = data.split('-');
    const dataObj = new Date(ano, mes - 1, dia);
    return dataObj.toLocaleDateString('pt-BR');
}

function formatarDataHora(dataHora) {
    const dataObj = new Date(dataHora);
    return dataObj.toLocaleString('pt-BR');
}

// ═══════════════════════════════════════════════════════════════════
// FUNÇÕES PARA SELEÇÃO EM LOTE
// ═══════════════════════════════════════════════════════════════════

// Toggle seleção de uma justificativa
function toggleSelecaoJustificativa(id) {
    if (justificativasSelecionadas.has(id)) {
        justificativasSelecionadas.delete(id);
    } else {
        justificativasSelecionadas.add(id);
    }
    atualizarBarraAcoesLote();
    atualizarCheckboxSelecionarTodas();
}

// Toggle selecionar todas
function toggleSelecionarTodas() {
    const selecionarTodas = $('#selecionar-todas').is(':checked');
    
    $('.checkbox-justificativa').each(function() {
        const id = parseInt($(this).val());
        $(this).prop('checked', selecionarTodas);
        
        if (selecionarTodas) {
            justificativasSelecionadas.add(id);
        } else {
            justificativasSelecionadas.delete(id);
        }
    });
    
    atualizarBarraAcoesLote();
}

// Atualizar estado do checkbox "Selecionar Todas"
function atualizarCheckboxSelecionarTodas() {
    const totalCheckboxes = $('.checkbox-justificativa').length;
    const totalSelecionadas = justificativasSelecionadas.size;
    
    if (totalCheckboxes === 0) {
        $('#selecionar-todas').prop('checked', false).prop('indeterminate', false);
    } else if (totalSelecionadas === totalCheckboxes) {
        $('#selecionar-todas').prop('checked', true).prop('indeterminate', false);
    } else if (totalSelecionadas > 0) {
        $('#selecionar-todas').prop('checked', false).prop('indeterminate', true);
    } else {
        $('#selecionar-todas').prop('checked', false).prop('indeterminate', false);
    }
}

// Atualizar barra de ações em lote
function atualizarBarraAcoesLote() {
    const quantidade = justificativasSelecionadas.size;
    const barra = $('#barra-acoes-lote');
    
    if (quantidade > 0) {
        barra.removeClass('d-none').addClass('d-flex');
        $('#contador-selecionadas').text(quantidade);
    } else {
        barra.removeClass('d-flex').addClass('d-none');
    }
}

// Abrir modal para decisão em lote
function abrirModalDecisaoLote(decisao) {
    if (justificativasSelecionadas.size === 0) {
        exibirToast('Nenhuma justificativa selecionada', 'warning');
        return;
    }
    
    const quantidade = justificativasSelecionadas.size;
    const decisaoTexto = decisao === 'aprovada' ? 'APROVAR' : 'REJEITAR';
    const corClasse = decisao === 'aprovada' ? 'text-success' : 'text-danger';
    const icone = decisao === 'aprovada' ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
    
    $('#decisao-lote-tipo').val(decisao);
    $('#quantidade-selecionadas').text(quantidade);
    $('#decisao-lote-texto').html(`<i class="bi ${icone} ${corClasse} me-1"></i><span class="${corClasse}">${decisaoTexto}</span>`);
    $('#observacoes-lote').val('');
    
    $('#modalDecisaoLote').modal('show');
}

// Confirmar decisão em lote
function confirmarDecisaoLote() {
    const decisao = $('#decisao-lote-tipo').val();
    const observacoes = $('#observacoes-lote').val();
    const ids = Array.from(justificativasSelecionadas);
    
    if (ids.length === 0) {
        exibirToast('Nenhuma justificativa selecionada', 'warning');
        return;
    }
    
    const btn = $('#btn-confirmar-decisao-lote');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Processando...');
    
    exibirToast('Processando decisão em lote...', 'info');
    
    $.ajax({
        url: '../api/culto/decidir_justificativas_lote.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            ids: ids,
            decisao: decisao,
            observacoes_admin: observacoes
        }),
        dataType: 'json',
        success: function(resposta) {
            if (resposta.status === 'ok') {
                exibirToast(resposta.mensagem, 'success');
                
                // Se houver avisos, mostrar também
                if (resposta.avisos && resposta.avisos.length > 0) {
                    setTimeout(() => {
                        exibirToast('Avisos: ' + resposta.avisos.join(', '), 'warning');
                    }, 1000);
                }
                
                $('#modalDecisaoLote').modal('hide');
                justificativasSelecionadas.clear();
                carregarJustificativas();
            } else {
                exibirToast('Erro: ' + resposta.mensagem, 'danger');
            }
        },
        error: function() {
            exibirToast('Erro ao processar decisão em lote. Tente novamente.', 'danger');
        },
        complete: function() {
            btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Confirmar');
        }
    });
}

// Limpar seleção
function limparSelecao() {
    justificativasSelecionadas.clear();
    $('.checkbox-justificativa').prop('checked', false);
    $('#selecionar-todas').prop('checked', false).prop('indeterminate', false);
    atualizarBarraAcoesLote();
}
