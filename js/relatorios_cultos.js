$(document).ready(function() {
    carregarUsuarios();
});

function definirPeriodo(periodo) {
    const hoje = new Date();
    let dataInicio, dataFim;
    
    switch(periodo) {
        case 'hoje':
            dataInicio = dataFim = formatarDataParaInput(hoje);
            break;
        case 'semana':
            const inicioSemana = new Date(hoje);
            inicioSemana.setDate(hoje.getDate() - hoje.getDay());
            dataInicio = formatarDataParaInput(inicioSemana);
            dataFim = formatarDataParaInput(hoje);
            break;
        case 'mes':
            dataInicio = formatarDataParaInput(new Date(hoje.getFullYear(), hoje.getMonth(), 1));
            dataFim = formatarDataParaInput(hoje);
            break;
        case 'ano':
            dataInicio = formatarDataParaInput(new Date(hoje.getFullYear(), 0, 1));
            dataFim = formatarDataParaInput(hoje);
            break;
    }
    
    $('#data_inicio').val(dataInicio);
    $('#data_fim').val(dataFim);
}

function formatarDataParaInput(data) {
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');
    return `${ano}-${mes}-${dia}`;
}

function carregarUsuarios() {
    $.ajax({
        url: '../api/culto/listar_usuarios_simples.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'sucesso' || response.status === 'ok') {
                // A API retorna usuarios no formato { usuarios: [...] }
                const usuarios = response.usuarios || response.dados || response.data || [];
                const select = $('#filtro_usuario');
                
                // Destruir Select2 se já estiver inicializado
                if (select.hasClass('select2-hidden-accessible')) {
                    select.select2('destroy');
                }
                
                select.empty().append('<option value="">Todos os usuários</option>');
                
                usuarios.forEach(function(usuario) {
                    select.append(`<option value="${usuario.id}">${usuario.nome}</option>`);
                });
                
                // Inicializar Select2 com busca
                if (typeof $.fn.select2 !== 'undefined') {
                    select.select2({
                        theme: 'bootstrap-5',
                        placeholder: 'Digite para buscar...',
                        allowClear: true,
                        language: {
                            noResults: function() {
                                return 'Nenhum usuário encontrado';
                            },
                            searching: function() {
                                return 'Buscando...';
                            }
                        }
                    });
                }
            }
        },
        error: function() {
            console.error('Erro ao carregar usuários');
        }
    });
}

function gerarRelatorio(formato) {
    const tipoRelatorio = $('#tipo_relatorio').val();
    
    if (!tipoRelatorio) {
        exibirToast('Selecione um tipo de relatório', 'warning');
        return;
    }
    
    const dataInicio = $('#data_inicio').val();
    const dataFim = $('#data_fim').val();
    const usuarioId = $('#filtro_usuario').val();
    
    if (!dataInicio || !dataFim) {
        exibirToast('Selecione as datas de início e fim', 'warning');
        return;
    }
    
    if (!formato) {
        exibirToast('Selecione um formato de exportação', 'warning');
        return;
    }
    
    // Construir URL baseada no formato
    let url = '';
    switch(formato) {
        case 'pdf':
            url = `../api/culto/relatorios/exportar_pdf.php?tipo=${tipoRelatorio}&data_inicio=${dataInicio}&data_fim=${dataFim}`;
            break;
        case 'excel':
            url = `../api/culto/relatorios/exportar_excel.php?tipo=${tipoRelatorio}&data_inicio=${dataInicio}&data_fim=${dataFim}`;
            break;
        case 'csv':
            url = `../api/culto/relatorios/exportar_csv.php?tipo=${tipoRelatorio}&data_inicio=${dataInicio}&data_fim=${dataFim}`;
            break;
        default:
            exibirToast('Formato inválido', 'danger');
            return;
    }
    
    if (usuarioId) {
        url += `&usuario_id=${usuarioId}`;
    }
    
    // Abrir relatório diretamente
    window.open(url, '_blank');
    exibirToast('Relatório sendo gerado...', 'info');
}

function exibirRelatorio(dados) {
    const area = $('#area-resultados');
    area.empty();
    
    switch(tipoRelatorioSelecionado) {
        case 'presencas':
            exibirRelatorioPresencas(dados, area);
            break;
        case 'faltas':
            exibirRelatorioFaltas(dados, area);
            break;
        case 'justificativas':
            exibirRelatorioJustificativas(dados, area);
            break;
        case 'estatisticas':
            exibirRelatorioEstatisticas(dados, area);
            break;
        case 'usuario':
            exibirRelatorioUsuario(dados, area);
            break;
        case 'frequencia':
            exibirRelatorioFrequencia(dados, area);
            break;
        case 'atrasos':
            exibirRelatorioAtrasos(dados, area);
            break;
        case 'comparativo':
            exibirRelatorioComparativo(dados, area);
            break;
    }
}

function exibirRelatorioPresencas(dados, area) {
    area.empty();
    
    // Verificar se temos dados por usuário (novo formato)
    if (dados.usuarios && dados.usuarios.length > 0) {
        let html = `
            <div class="row mb-4">
                <div class="col-md-12">
                    <h4 class="mb-3">Relatório de Presenças</h4>
                    <p class="text-muted">Período: ${formatarData(dados.data_inicio)} a ${formatarData(dados.data_fim)}</p>
                </div>
            </div>
        `;
        
        // Criar seção para cada usuário
        dados.usuarios.forEach(function(usuario, index) {
            const usuarioId = usuario.id_usuario;
            const percentuais = usuario.percentuais || {};
            const presentes = usuario.presentes || 0;
            const atrasados = usuario.atrasados || 0;
            const faltas = usuario.faltas || 0;
            const justificados = usuario.justificados || 0;
            
            html += `
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>${usuario.nome_usuario}</h5>
                    </div>
                    <div class="card-body">
                        <!-- Estatísticas e Gráfico -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">Estatísticas</h6>
                                        <div class="d-flex flex-column gap-2">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-success rounded me-2" style="width: 20px; height: 20px;"></div>
                                                <span><strong>${percentuais.presentes || 0}% [${presentes}]</strong> - Pontual</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary rounded me-2" style="width: 20px; height: 20px;"></div>
                                                <span><strong>${percentuais.atrasados || 0}% [${atrasados}]</strong> - Atraso</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-danger rounded me-2" style="width: 20px; height: 20px;"></div>
                                                <span><strong>${percentuais.faltas || 0}% [${faltas}]</strong> - Falta</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-warning rounded me-2" style="width: 20px; height: 20px;"></div>
                                                <span><strong>${percentuais.justificados || 0}% [${justificados}]</strong> - Justificado</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">Gráfico de Distribuição</h6>
                                        <canvas id="graficoPizzaUsuario${usuarioId}" style="max-height: 250px;"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Calendário -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">Calendário de Presenças</h6>
                                        <div id="calendario-usuario${usuarioId}"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        area.html(html);
        
        // Criar gráficos e calendários após DOM ser atualizado
        setTimeout(function() {
            dados.usuarios.forEach(function(usuario, index) {
                const usuarioId = usuario.id_usuario;
                const presentes = usuario.presentes || 0;
                const atrasados = usuario.atrasados || 0;
                const faltas = usuario.faltas || 0;
                const justificados = usuario.justificados || 0;
                
                // Criar gráfico
                if (typeof Chart !== 'undefined') {
                    const ctx = document.getElementById(`graficoPizzaUsuario${usuarioId}`);
                    if (ctx) {
                        const total = presentes + atrasados + faltas + justificados;
                        const percentuais = total > 0 ? [
                            Math.round((presentes / total) * 100 * 10) / 10,
                            Math.round((atrasados / total) * 100 * 10) / 10,
                            Math.round((faltas / total) * 100 * 10) / 10,
                            Math.round((justificados / total) * 100 * 10) / 10
                        ] : [0, 0, 0, 0];
                        
                        new Chart(ctx, {
                            type: 'pie',
                            data: {
                                labels: ['Pontual', 'Atraso', 'Falta', 'Justificado'],
                                datasets: [{
                                    data: [presentes, atrasados, faltas, justificados],
                                    backgroundColor: [
                                        'rgba(40, 167, 69, 0.8)',   // Verde - Pontual
                                        'rgba(0, 123, 255, 0.8)',   // Azul - Atraso
                                        'rgba(220, 53, 69, 0.8)',   // Vermelho - Falta
                                        'rgba(255, 193, 7, 0.8)'    // Amarelo - Justificado
                                    ],
                                    borderColor: [
                                        'rgba(40, 167, 69, 1)',
                                        'rgba(0, 123, 255, 1)',
                                        'rgba(220, 53, 69, 1)',
                                        'rgba(255, 193, 7, 1)'
                                    ],
                                    borderWidth: 0
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: true,
                                layout: {
                                    padding: {
                                        right: 20
                                    }
                                },
                                plugins: {
                                    legend: {
                                        position: 'right',
                                        align: 'start',
                                        labels: {
                                            boxWidth: 12,
                                            padding: 8,
                                            font: {
                                                size: 11
                                            }
                                        }
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                const label = context.label || '';
                                                const value = context.parsed || 0;
                                                const percent = percentuais[context.dataIndex] || 0;
                                                return label + ': ' + value + ' (' + percent + '%)';
                                            }
                                        }
                                    }
                                },
                                animation: {
                                    onComplete: function() {
                                        const chart = this;
                                        const ctx = chart.ctx;
                                        ctx.save();
                                        
                                        const meta = chart.getDatasetMeta(0);
                                        const centerX = chart.chartArea.left + (chart.chartArea.right - chart.chartArea.left) / 2;
                                        const centerY = chart.chartArea.top + (chart.chartArea.bottom - chart.chartArea.top) / 2;
                                        
                                        meta.data.forEach((slice, index) => {
                                            const model = slice;
                                            const value = [presentes, atrasados, faltas, justificados][index];
                                            const percent = percentuais[index];
                                            
                                            if (value > 0 && percent > 5) { // Só mostrar se for maior que 5% para não sobrepor
                                                const angle = (model.startAngle + model.endAngle) / 2;
                                                const distance = model.innerRadius + (model.outerRadius - model.innerRadius) / 2;
                                                const x = centerX + Math.cos(angle) * distance;
                                                const y = centerY + Math.sin(angle) * distance;
                                                
                                                ctx.fillStyle = '#fff';
                                                ctx.font = 'bold 12px Arial';
                                                ctx.textAlign = 'center';
                                                ctx.textBaseline = 'middle';
                                                ctx.fillText(percent + '%', x, y);
                                                ctx.font = '10px Arial';
                                                ctx.fillText('(' + value + ')', x, y + 14);
                                            }
                                        });
                                        
                                        ctx.restore();
                                    }
                                }
                            }
                        });
                    }
                }
                
                // Gerar calendário
                if (usuario.calendario && usuario.data_inicio && usuario.data_fim) {
                    gerarCalendarioPresencas(usuario.calendario, usuario.data_inicio, usuario.data_fim, `calendario-usuario${usuarioId}`);
                }
            });
        }, 300);
    } else {
        // Sem dados
        area.html('<p class="text-center text-muted">Nenhum dado encontrado para o período selecionado.</p>');
    }
}
function exibirRelatorioFaltas(dados, area) {
    let html = `
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-number">${dados.total_faltas || 0}</div>
                    <div>Total de Faltas</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-number">${dados.faltas_justificadas || 0}</div>
                    <div>Faltas Justificadas</div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <h5 class="mb-3">Lista de Faltas</h5>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Usuário</th>
                        <th>Status</th>
                        <th>Justificativa</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (dados.faltas && dados.faltas.length > 0) {
        dados.faltas.forEach(function(falta) {
            html += `
                <tr>
                    <td>${formatarData(falta.data)}</td>
                    <td>${falta.nome_usuario}</td>
                    <td><span class="badge bg-${falta.justificada ? 'warning' : 'danger'}">${falta.justificada ? 'Justificada' : 'Não Justificada'}</span></td>
                    <td>${falta.motivo || '-'}</td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="4" class="text-center text-muted">Nenhuma falta encontrada</td></tr>';
    }
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    area.html(html);
}

function exibirRelatorioJustificativas(dados, area) {
    let html = `
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number">${dados.pendentes || 0}</div>
                    <div>Pendentes</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number">${dados.aprovadas || 0}</div>
                    <div>Aprovadas</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number">${dados.rejeitadas || 0}</div>
                    <div>Rejeitadas</div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <h5 class="mb-3">Lista de Justificativas</h5>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Data Falta</th>
                        <th>Usuário</th>
                        <th>Motivo</th>
                        <th>Status</th>
                        <th>Data Aprovação</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (dados.justificativas && dados.justificativas.length > 0) {
        dados.justificativas.forEach(function(justificativa) {
            html += `
                <tr>
                    <td>${formatarData(justificativa.data_falta)}</td>
                    <td>${justificativa.nome_usuario}</td>
                    <td>${justificativa.motivo}</td>
                    <td><span class="badge bg-${getBadgeColorJustificativa(justificativa.status)}">${justificativa.status}</span></td>
                    <td>${justificativa.data_aprovacao ? formatarData(justificativa.data_aprovacao) : '-'}</td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="5" class="text-center text-muted">Nenhuma justificativa encontrada</td></tr>';
    }
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    area.html(html);
}

function exibirRelatorioEstatisticas(dados, area) {
    let html = `
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number">${dados.total_cultos || 0}</div>
                    <div>Total de Cultos</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number">${dados.media_presenca || 0}%</div>
                    <div>Média de Presença</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number">${dados.total_presentes || 0}</div>
                    <div>Total Presentes</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number">${dados.total_faltas || 0}</div>
                    <div>Total Faltas</div>
                </div>
            </div>
        </div>
        <div class="chart-container">
            <canvas id="graficoPresencas"></canvas>
        </div>
    `;
    
    area.html(html);
    
    // Criar gráfico
    if (dados.grafico && dados.grafico.labels) {
        const ctx = document.getElementById('graficoPresencas').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: dados.grafico.labels,
                datasets: [{
                    label: 'Presenças',
                    data: dados.grafico.presencas,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

function exibirRelatorioUsuario(dados, area) {
    // Similar aos outros, mas focado em um usuário específico
    let html = `<div class="table-responsive">
        <h5 class="mb-3">Histórico do Usuário: ${dados.nome_usuario || ''}</h5>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Status</th>
                    <th>Horário</th>
                    <th>Tipo</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    if (dados.historico && dados.historico.length > 0) {
        dados.historico.forEach(function(item) {
            html += `
                <tr>
                    <td>${formatarData(item.data)}</td>
                    <td><span class="badge bg-${getBadgeColor(item.status)}">${item.status}</span></td>
                    <td>${item.horario || '-'}</td>
                    <td>${item.tipo || '-'}</td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="4" class="text-center text-muted">Nenhum registro encontrado</td></tr>';
    }
    
    html += `</tbody></table></div>`;
    area.html(html);
}

function exibirRelatorioFrequencia(dados, area) {
    let html = `
        <div class="table-responsive">
            <h5 class="mb-3">Frequência de Presença</h5>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Total Cultos</th>
                        <th>Presentes</th>
                        <th>Faltas</th>
                        <th>Frequência</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (dados.frequencias && dados.frequencias.length > 0) {
        dados.frequencias.forEach(function(freq) {
            html += `
                <tr>
                    <td>${freq.nome_usuario}</td>
                    <td>${freq.total_cultos}</td>
                    <td>${freq.presentes}</td>
                    <td>${freq.faltas}</td>
                    <td><strong>${freq.percentual}%</strong></td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="5" class="text-center text-muted">Nenhum dado encontrado</td></tr>';
    }
    
    html += `</tbody></table></div>`;
    area.html(html);
}

function exibirRelatorioAtrasos(dados, area) {
    // Similar ao de faltas, mas focado em atrasos
    let html = `<div class="table-responsive">
        <h5 class="mb-3">Detalhamento de Atrasos</h5>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Usuário</th>
                    <th>Horário Chegada</th>
                    <th>Horário Culto</th>
                    <th>Atraso (minutos)</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    if (dados.atrasos && dados.atrasos.length > 0) {
        dados.atrasos.forEach(function(atraso) {
            html += `
                <tr>
                    <td>${formatarData(atraso.data)}</td>
                    <td>${atraso.nome_usuario}</td>
                    <td>${atraso.horario_confirmacao}</td>
                    <td>${atraso.horario_culto || '-'}</td>
                    <td><span class="badge bg-warning">${atraso.minutos_atraso || '-'}</span></td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="5" class="text-center text-muted">Nenhum atraso encontrado</td></tr>';
    }
    
    html += `</tbody></table></div>`;
    area.html(html);
}

function exibirRelatorioComparativo(dados, area) {
    let html = `
        <div class="chart-container">
            <canvas id="graficoComparativo"></canvas>
        </div>
    `;
    
    area.html(html);
    
    if (dados.periodos && dados.periodos.length > 0) {
        const ctx = document.getElementById('graficoComparativo').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dados.periodos.map(p => p.periodo),
                datasets: [{
                    label: 'Presenças',
                    data: dados.periodos.map(p => p.presencas),
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true
            }
        });
    }
}

function formatarData(data) {
    if (!data) return '-';
    const d = new Date(data + 'T00:00:00');
    return d.toLocaleDateString('pt-BR');
}

function getBadgeColor(status) {
    const cores = {
        'presente': 'success',
        'atrasado': 'warning',
        'falta': 'danger',
        'ausente': 'secondary',
        'justificado': 'info'
    };
    return cores[status] || 'secondary';
}

function getBadgeColorJustificativa(status) {
    const cores = {
        'pendente': 'warning',
        'aprovada': 'success',
        'rejeitada': 'danger'
    };
    return cores[status] || 'secondary';
}

// Funções de exportação removidas - agora são integradas em gerarRelatorio()

function gerarCalendarioPresencas(calendario, dataInicio, dataFim, containerId = 'calendario-presencas') {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const inicio = new Date(dataInicio + 'T00:00:00');
    const fim = new Date(dataFim + 'T00:00:00');
    
    // Calcular número de meses entre as datas
    const mesesDiferenca = (fim.getFullYear() - inicio.getFullYear()) * 12 + (fim.getMonth() - inicio.getMonth()) + 1;
    
    // Se o período for maior que 1 mês, dividir por meses
    if (mesesDiferenca > 1) {
        let html = '';
        let dataAtual = new Date(inicio);
        
        while (dataAtual <= fim) {
            // Calcular início e fim do mês atual
            const inicioMes = new Date(dataAtual.getFullYear(), dataAtual.getMonth(), 1);
            const fimMes = new Date(dataAtual.getFullYear(), dataAtual.getMonth() + 1, 0);
            
            // Ajustar para não ultrapassar as datas do período
            const inicioMesAjustado = inicioMes < inicio ? inicio : inicioMes;
            const fimMesAjustado = fimMes > fim ? fim : fimMes;
            
            // Nome do mês
            const nomeMes = dataAtual.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
            html += `<div class="mb-4">
                <h6 class="mb-3 text-capitalize">${nomeMes}</h6>
                ${gerarCalendarioMes(calendario, inicioMesAjustado, fimMesAjustado)}
            </div>`;
            
            // Avançar para o próximo mês
            dataAtual = new Date(dataAtual.getFullYear(), dataAtual.getMonth() + 1, 1);
        }
        
        container.innerHTML = html;
    } else {
        // Período de até 1 mês, gerar calendário único
        container.innerHTML = gerarCalendarioMes(calendario, inicio, fim);
    }
}

function gerarCalendarioMes(calendario, inicio, fim) {
    let html = '<div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr>';
    const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    diasSemana.forEach(dia => {
        html += `<th class="text-center" style="font-size: 0.85rem; padding: 5px;">${dia}</th>`;
    });
    html += '</tr></thead><tbody>';
    
    let data = new Date(inicio);
    let primeiroDia = true;
    
    while (data <= fim) {
        if (primeiroDia || data.getDay() === 0) {
            if (!primeiroDia) html += '</tr>';
            html += '<tr>';
            if (data.getDay() > 0 && primeiroDia) {
                for (let i = 0; i < data.getDay(); i++) {
                    html += '<td></td>';
                }
            }
            primeiroDia = false;
        }
        
        const dataStr = data.toISOString().split('T')[0];
        const dia = calendario[dataStr] || { presentes: [], atrasados: [], faltas: [], justificados: [] };
        
        let classe = '';
        let tooltip = formatarData(dataStr);
        let simbolo = '';
        
        if (dia.presentes.length > 0 || dia.atrasados.length > 0 || dia.faltas.length > 0 || dia.justificados.length > 0) {
            // Verificar justificativa aprovada primeiro (tem prioridade)
            if (dia.justificados.length > 0) {
                const justAprovada = dia.justificados.find(j => j.tipo === 'aprovada');
                if (justAprovada) {
                    classe = 'bg-warning text-dark';
                    tooltip += ' - Justificativa Aprovada';
                    simbolo = '✓';
                } else {
                    classe = 'bg-warning text-dark';
                    tooltip += ` - ${dia.justificados.length} justificado(s)`;
                }
            } else if (dia.faltas.length > 0) {
                // Verificar se há justificativa rejeitada ou pendente
                const faltaComJust = dia.faltas.find(f => f.tipo === 'justificativa_rejeitada' || f.tipo === 'justificativa_pendente');
                if (faltaComJust) {
                    if (faltaComJust.tipo === 'justificativa_rejeitada') {
                        classe = 'bg-danger text-white';
                        tooltip += ' - Falta (Justificativa Rejeitada)';
                        simbolo = '✗';
                    } else {
                        classe = 'bg-danger text-white';
                        tooltip += ' - Falta (Justificativa Pendente)';
                        simbolo = '?';
                    }
                } else {
                    classe = 'bg-danger text-white';
                    tooltip += ` - ${dia.faltas.length} falta(s)`;
                }
            } else if (dia.atrasados.length > 0) {
                classe = 'bg-primary text-white';
                tooltip += ` - ${dia.atrasados.length} atraso(s)`;
            } else if (dia.presentes.length > 0) {
                classe = 'bg-success text-white';
                tooltip += ` - ${dia.presentes.length} presente(s)`;
            }
        }
        
        html += `<td class="text-center ${classe}" style="cursor: pointer; min-width: 35px; padding: 4px; position: relative; font-size: 0.85rem;" title="${tooltip}" data-bs-toggle="tooltip">
            ${data.getDate()}
            ${simbolo ? `<span style="position: absolute; top: 1px; right: 2px; font-size: 9px;">${simbolo}</span>` : ''}
        </td>`;
        
        if (data.getDay() === 6) {
            html += '</tr>';
        }
        
        data.setDate(data.getDate() + 1);
    }
    
    // Fechar última linha se necessário
    const ultimaData = new Date(data.getTime() - 24 * 60 * 60 * 1000);
    const ultimoDiaSemana = ultimaData.getDay();
    if (ultimoDiaSemana !== 6 && ultimaData <= fim) {
        for (let i = ultimoDiaSemana + 1; i <= 6; i++) {
            html += '<td></td>';
        }
        html += '</tr>';
    }
    
    html += '</tbody></table></div>';
    return html;
}

