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

$(document).ready(function() {
    carregarUsuarios();
    
    // Eventos
    $('#btn-atualizar').click(carregarUsuarios);
    $('#btn-hoje').click(irParaHoje);
    $('#seletor-data').change(alterarData);
    $('#filtro-status').change(filtrarUsuarios);
    $('#busca-usuario').on('input', filtrarUsuarios);
});

// Carregar lista de usuários
function carregarUsuarios() {
    const dataSelecionada = $('#seletor-data').val();
    
    // Mostrar alerta de carregamento
    exibirToast('Carregando usuários...', 'info');
    
    $.ajax({
        url: '../api/culto/listar_usuarios_admin.php',
        type: 'GET',
        data: { data: dataSelecionada },
        dataType: 'json',
        success: function(resposta) {
            if (resposta.status === 'ok') {
                exibirUsuarios(resposta.usuarios);
                atualizarEstatisticas(resposta.estatisticas);
                atualizarDataExibida(resposta.data);
                
                // Mostrar alerta de sucesso
                exibirToast('Todos os usuários carregados com sucesso!', 'success');
            } else {
                exibirToast('Erro ao carregar usuários: ' + resposta.mensagem, 'danger');
            }
        },
        error: function() {
            exibirToast('Erro ao carregar usuários. Tente novamente.', 'danger');
        }
    });
}

// Exibir usuários na tela
function exibirUsuarios(usuarios) {
    const container = $('#lista-usuarios');
    container.empty();
    
    if (usuarios.length === 0) {
        container.html('<div class="col-12 text-center text-muted py-4"><i class="bi bi-people fs-1"></i><p class="mt-2">Nenhum usuário encontrado</p></div>');
        return;
    }
    
    usuarios.forEach(function(usuario) {
        const statusClass = getStatusClass(usuario.status_presenca);
        const statusIcon = getStatusIcon(usuario.status_presenca);
        const statusText = getStatusText(usuario.status_presenca);
        
        const fotoHtml = usuario.foto_base64 ? 
            `<img src="${usuario.foto_base64}" alt="Foto" class="foto-usuario">` :
            `<div class="foto-usuario d-flex align-items-center justify-content-center bg-light"><i class="bi bi-person-fill text-muted"></i></div>`;
        
        // Verificar se tem justificativa
        const temJustificativa = usuario.justificativa && usuario.justificativa.id;
        const justificativaClass = temJustificativa ? 'justificativa-bloqueada' : '';
        const cursorClass = temJustificativa ? 'cursor-not-allowed' : '';
        
        // Formatar horário da leitura facial (apenas para presente/atrasado)
        let horarioHtml = '';
        if (usuario.horario_confirmacao && (usuario.status_presenca === 'presente' || usuario.status_presenca === 'atrasado')) {
            // Extrair apenas HH:MM do horário
            const horario = usuario.horario_confirmacao.substring(0, 5);
            horarioHtml = `<small class="text-muted ms-1" title="Horário da leitura facial"><i class="bi bi-clock-history"></i> ${horario}</small>`;
        }
        
        let justificativaInfo = '';
        if (temJustificativa) {
            justificativaInfo = `
                <div class="mt-2">
                    <small class="text-muted">
                        <i class="bi bi-file-text me-1"></i>
                        Justificativa: ${usuario.justificativa.motivo}
                        <span class="badge ${getStatusBadgeClass(usuario.justificativa.status)} ms-1">
                            ${getStatusTextJustificativa(usuario.justificativa.status)}
                        </span>
                    </small>
                </div>
            `;
        }
        
        const usuarioHtml = `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card usuario-item ${statusClass} ${justificativaClass} ${cursorClass}" 
                     data-usuario-id="${usuario.id}" 
                     data-status="${usuario.status_presenca}"
                     data-horario="${usuario.horario_confirmacao || ''}"
                     data-tem-justificativa="${temJustificativa}">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                ${fotoHtml}
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1 fw-bold">${usuario.nome}</h6>
                                <small class="text-muted">${usuario.email}</small>
                                <div class="mt-2">
                                    <span class="badge ${getStatusBadgeClass(usuario.status_presenca)}">
                                        ${statusIcon} ${statusText}
                                    </span>
                                    ${horarioHtml}
                                </div>
                                ${justificativaInfo}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        container.append(usuarioHtml);
    });
    
    // Adicionar evento de clique
    $('.usuario-item').click(function() {
        const temJustificativa = $(this).data('tem-justificativa');
        const usuarioId = $(this).data('usuario-id');
        
        
        if (temJustificativa) {
            exibirToast('Não é possível alterar presença: existe uma justificativa para esta data', 'warning');
            return;
        }
        
        const statusAtual = $(this).data('status');
        alterarPresenca(usuarioId, statusAtual, $(this));
    });
}

// Alterar presença do usuário
function alterarPresenca(usuarioId, statusAtual, elemento) {
    const dataSelecionada = $('#seletor-data').val();
    const hoje = new Date();
    const hojeStr = hoje.getFullYear() + '-' + String(hoje.getMonth() + 1).padStart(2, '0') + '-' + String(hoje.getDate()).padStart(2, '0');
    
    // Verificar se a data é futura
    if (dataSelecionada > hojeStr) {
        exibirToast('Não é possível alterar presenças de datas futuras', 'warning');
        return;
    }
    
    // Mostrar loading no elemento específico
    const loadingHtml = '<i class="bi bi-hourglass-split me-1"></i>Alterando...';
    const originalHtml = elemento.html();
    elemento.html(loadingHtml).prop('disabled', true);
    
    // Timeout de segurança para restaurar elemento se algo der errado
    const timeoutId = setTimeout(() => {
        elemento.html(originalHtml).prop('disabled', false);
        exibirToast('Timeout na operação. Tente novamente.', 'warning');
    }, 10000); // 10 segundos
    
    $.ajax({
        url: '../api/culto/alterar_presenca_admin.php',
        type: 'POST',
        data: {
            usuario_id: usuarioId,
            status_atual: statusAtual,
            data: dataSelecionada
        },
        dataType: 'json',
        success: function(resposta) {
            // Limpar timeout
            clearTimeout(timeoutId);
            
            if (resposta.status === 'ok') {
                // Restaurar HTML original primeiro
                elemento.html(originalHtml);
                
                // Atualizar elemento visual
                elemento.removeClass('status-sem-presenca status-presente status-atrasado status-falta');
                elemento.addClass(resposta.novo_status_class);
                elemento.data('status', resposta.novo_status);
                elemento.data('horario', resposta.horario || '');
                
                // Atualizar badge e horário
                const badgeContainer = elemento.find('.mt-2').first();
                const badge = badgeContainer.find('.badge');
                badge.removeClass('bg-secondary bg-success bg-primary bg-danger');
                badge.addClass(resposta.novo_badge_class);
                badge.html(`${resposta.novo_icon} ${resposta.novo_texto}`);
                
                // Remover horário antigo se existir
                badgeContainer.find('small.text-muted').remove();
                
                // Adicionar horário se for presente ou atrasado
                if (resposta.horario && (resposta.novo_status === 'presente' || resposta.novo_status === 'atrasado')) {
                    const horarioHtml = `<small class="text-muted ms-1" title="Horário da leitura facial"><i class="bi bi-clock-history"></i> ${resposta.horario}</small>`;
                    badge.after(horarioHtml);
                }
                
                // Atualizar apenas as estatísticas (sem recarregar lista)
                atualizarEstatisticasAposAlteracao(resposta.novo_status, statusAtual);
                
                exibirToast(resposta.mensagem, 'success');
            } else {
                exibirToast('Erro ao alterar presença: ' + resposta.mensagem, 'danger');
                // Restaurar HTML original em caso de erro
                elemento.html(originalHtml);
            }
        },
        error: function() {
            // Limpar timeout
            clearTimeout(timeoutId);
            
            exibirToast('Erro ao alterar presença. Tente novamente.', 'danger');
            // Restaurar HTML original em caso de erro
            elemento.html(originalHtml);
        },
        complete: function() {
            // Reabilitar botão
            elemento.prop('disabled', false);
        }
    });
}

// Alterar data selecionada
function alterarData() {
    exibirToast('Alterando data...', 'info');
    carregarUsuarios();
}

// Ir para o dia de hoje
function irParaHoje() {
    exibirToast('Carregando dados de hoje...', 'info');
    const hoje = new Date();
    const hojeStr = hoje.getFullYear() + '-' + String(hoje.getMonth() + 1).padStart(2, '0') + '-' + String(hoje.getDate()).padStart(2, '0');
    $('#seletor-data').val(hojeStr);
    carregarUsuarios();
}

// Atualizar data exibida no header
function atualizarDataExibida(data) {
    // Garantir que a data seja interpretada corretamente
    const [ano, mes, dia] = data.split('-');
    const dataObj = new Date(ano, mes - 1, dia);
    const dataFormatada = dataObj.toLocaleDateString('pt-BR');
    $('#data-exibida').text(dataFormatada);
    
    // Atualizar indicador de data
    const hoje = new Date();
    const hojeStr = hoje.getFullYear() + '-' + String(hoje.getMonth() + 1).padStart(2, '0') + '-' + String(hoje.getDate()).padStart(2, '0');
    const ontem = new Date();
    ontem.setDate(ontem.getDate() - 1);
    const ontemStr = ontem.getFullYear() + '-' + String(ontem.getMonth() + 1).padStart(2, '0') + '-' + String(ontem.getDate()).padStart(2, '0');
    
    const indicador = $('#indicador-data');
    
    if (data === hojeStr) {
        indicador.removeClass('bg-secondary bg-warning').addClass('bg-primary').text('Hoje');
        $('body').removeClass('data-futura');
    } else if (data === ontemStr) {
        indicador.removeClass('bg-primary bg-warning').addClass('bg-secondary').text('Ontem');
        $('body').removeClass('data-futura');
    } else if (data < hojeStr) {
        indicador.removeClass('bg-primary bg-warning').addClass('bg-secondary').text('Data Anterior');
        $('body').removeClass('data-futura');
    } else {
        indicador.removeClass('bg-primary bg-secondary').addClass('bg-warning').text('Data Futura');
        $('body').addClass('data-futura');
    }
}

// Atualizar estatísticas
function atualizarEstatisticas(stats) {
    $('#total-usuarios').text(stats.total_usuarios);
    $('#total-presentes').text(stats.total_presentes);
    $('#total-atrasados').text(stats.total_atrasados);
    $('#total-faltas').text(stats.total_faltas);
}

// Atualizar estatísticas após alteração (sem recarregar lista)
function atualizarEstatisticasAposAlteracao(novoStatus, statusAnterior) {
    // Obter valores atuais
    let presentes = parseInt($('#total-presentes').text()) || 0;
    let atrasados = parseInt($('#total-atrasados').text()) || 0;
    let faltas = parseInt($('#total-faltas').text()) || 0;
    
    // Remover do status anterior
    if (statusAnterior === 'presente') {
        presentes = Math.max(0, presentes - 1);
    } else if (statusAnterior === 'atrasado') {
        atrasados = Math.max(0, atrasados - 1);
    } else if (statusAnterior === 'falta') {
        faltas = Math.max(0, faltas - 1);
    }
    
    // Adicionar ao novo status
    if (novoStatus === 'presente') {
        presentes++;
    } else if (novoStatus === 'atrasado') {
        atrasados++;
    } else if (novoStatus === 'falta') {
        faltas++;
    }
    
    // Atualizar na tela
    $('#total-presentes').text(presentes);
    $('#total-atrasados').text(atrasados);
    $('#total-faltas').text(faltas);
}

// Filtrar usuários
function filtrarUsuarios() {
    const filtroStatus = $('#filtro-status').val();
    const buscaTexto = $('#busca-usuario').val().toLowerCase();
    
    $('.usuario-item').each(function() {
        const elemento = $(this);
        const status = elemento.data('status');
        const nome = elemento.find('h6').text().toLowerCase();
        
        let mostrar = true;
        
        // Filtro por status
        if (filtroStatus !== 'todos') {
            if (filtroStatus === 'sem-presenca' && status !== 'sem-presenca') mostrar = false;
            if (filtroStatus === 'presente' && status !== 'presente') mostrar = false;
            if (filtroStatus === 'atrasado' && status !== 'atrasado') mostrar = false;
            if (filtroStatus === 'falta' && status !== 'falta') mostrar = false;
        }
        
        // Filtro por busca
        if (buscaTexto && !nome.includes(buscaTexto)) {
            mostrar = false;
        }
        
        elemento.closest('.col-md-6').toggle(mostrar);
    });
}

// Funções auxiliares para status
function getStatusClass(status) {
    switch(status) {
        case 'presente': return 'status-presente';
        case 'atrasado': return 'status-atrasado';
        case 'falta': return 'status-falta';
        default: return 'status-sem-presenca';
    }
}

function getStatusIcon(status) {
    switch(status) {
        case 'presente': return '<i class="bi bi-check-circle-fill"></i>';
        case 'atrasado': return '<i class="bi bi-clock-fill"></i>';
        case 'falta': return '<i class="bi bi-x-circle-fill"></i>';
        default: return '<i class="bi bi-circle"></i>';
    }
}

function getStatusText(status) {
    switch(status) {
        case 'presente': return 'Presente';
        case 'atrasado': return 'Atrasado';
        case 'falta': return 'Falta';
        default: return 'Sem Presença';
    }
}

function getStatusBadgeClass(status) {
    switch(status) {
        case 'presente': return 'bg-success';
        case 'atrasado': return 'bg-primary';
        case 'falta': return 'bg-danger';
        case 'justificado': return 'bg-warning';
        default: return 'bg-secondary';
    }
}

function getStatusTextJustificativa(status) {
    switch(status) {
        case 'pendente': return 'Pendente';
        case 'aprovada': return 'Aprovada';
        case 'rejeitada': return 'Rejeitada';
        default: return 'Desconhecido';
    }
}


