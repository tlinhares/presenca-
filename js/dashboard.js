// Dashboard JavaScript
$(document).ready(function() {
    carregarDadosDashboard();
    carregarGraficoSemana();
    
    // Controle de campos de data baseado no tipo de relatório
    controlarCamposData();
    
    // Event listener para mudança no tipo de relatório
    $('#tipoRelatorio').change(function() {
        controlarCamposData();
    });
    
    // Botão atualizar
    $('#btnAtualizar').click(function() {
        carregarDadosDashboard();
        carregarGraficoSemana();
    });
    
    // Botão exportar
    $('#btnExportar').click(function() {
        const tipoRelatorio = $('#tipoRelatorio').val();
        const tipoExportacao = $('#tipoExportacao').val();
        const dataInicio = $('#dataInicio').val();
        const dataFim = $('#dataFim').val();
        
        if (!dataInicio || !dataFim) {
            alert('Selecione o período para exportar');
            return;
        }
        
        if (tipoRelatorio === 'diario' || tipoRelatorio === 'diario_completo') {
            // Para relatórios diários, usar apenas a data de hoje
            const dataHoje = new Date().toISOString().split('T')[0];
            
            if (tipoExportacao === 'excel') {
                window.open(`api/relatorios/exportar_csv_diario.php?tipo=${tipoRelatorio}&data=${dataHoje}`, '_blank');
            } else if (tipoExportacao === 'pdf') {
                window.open(`api/relatorios/exportar_pdf_diario.php?tipo=${tipoRelatorio}&data=${dataHoje}`, '_blank');
            }
        } else if (tipoRelatorio === 'mensal') {
            // Para relatórios mensais, usar o período selecionado
            if (tipoExportacao === 'excel') {
                window.open(`api/relatorios/exportar_csv.php?inicio=${dataInicio}&fim=${dataFim}`, '_blank');
            } else if (tipoExportacao === 'pdf') {
                window.open(`api/relatorios/exportar_pdf.php?inicio=${dataInicio}&fim=${dataFim}`, '_blank');
            }
        }
    });
});

// Função para controlar campos de data baseado no tipo de relatório
function controlarCamposData() {
    const tipoRelatorio = $('#tipoRelatorio').val();
    const dataInicio = $('#dataInicio');
    const dataFim = $('#dataFim');
    
    if (tipoRelatorio === 'mensal') {
        // Habilitar campos de data para relatório mensal
        dataInicio.prop('disabled', false);
        dataFim.prop('disabled', false);
        dataInicio.addClass('form-control').removeClass('form-control-plaintext');
        dataFim.addClass('form-control').removeClass('form-control-plaintext');
    } else {
        // Desabilitar campos de data para relatórios diários
        dataInicio.prop('disabled', true);
        dataFim.prop('disabled', true);
        dataInicio.addClass('form-control-plaintext').removeClass('form-control');
        dataFim.addClass('form-control-plaintext').removeClass('form-control');
        
        // Definir valores padrão para relatórios diários (data de hoje)
        const dataHoje = new Date().toISOString().split('T')[0];
        dataInicio.val(dataHoje);
        dataFim.val(dataHoje);
    }
}

function carregarDadosDashboard() {
    const dataInicio = $('#dataInicio').val();
    const dataFim = $('#dataFim').val();
    
    $.ajax({
        url: 'api/relatorios/dados_dashboard.php',
        type: 'GET',
        data: { data: dataFim },
        dataType: 'json',
        success: function(data) {
            if (data.status === 'sucesso' && data.dados) {
                const dados = data.dados;
                
                // Total de refeições
                const totalProprias = parseInt(dados.reservas_hoje) || 0;
                const totalAdicionais = parseInt(dados.reservas_adicionais_hoje) || 0;
                const totalDepartamentos = parseInt(dados.reservas_departamentos_hoje) || 0;
                const totalGeral = totalProprias + totalAdicionais + totalDepartamentos;
                
                // Debug: Log dos valores
                console.log('DEBUG - Valores recebidos:', {
                    totalProprias: totalProprias,
                    totalAdicionais: totalAdicionais,
                    totalDepartamentos: totalDepartamentos,
                    totalGeral: totalGeral
                });
                
                $('#totalRefeicoes').text(totalGeral);
                $('#detalhesRefeicoes').text(`Próprias: ${totalProprias}, Adicionais: ${totalAdicionais}, Departamentos: ${totalDepartamentos}`);
                
                // Valor estimado
                const valorProprias = parseFloat(dados.receita_proprias_hoje) || 0;
                const valorAdicionais = parseFloat(dados.receita_adicionais_hoje) || 0;
                const valorDepartamentos = parseFloat(dados.receita_departamentos_hoje) || 0;
                const valorTotal = Number(valorProprias) + Number(valorAdicionais) + Number(valorDepartamentos);
                
                // Verificar se valorTotal é um número válido
                if (isNaN(valorTotal)) {
                    console.error('Valor total inválido:', valorTotal);
                    $('#valorEstimado').text('R$ 0,00');
                    $('#detalhesValor').text('Erro ao calcular valores');
                } else {
                    $('#valorEstimado').text('R$ ' + valorTotal.toFixed(2).replace('.', ','));
                    $('#detalhesValor').text(`Próprias: R$ ${Number(valorProprias).toFixed(2).replace('.', ',')}, Adicionais: R$ ${Number(valorAdicionais).toFixed(2).replace('.', ',')}, Departamentos: R$ ${Number(valorDepartamentos).toFixed(2).replace('.', ',')}`);
                }
                
                // Departamentos
                $('#totalDepartamentos').text(totalDepartamentos + ' refeições');
                
                // Últimas reservas (simulado)
                $('#ultimasReservas').html(`
                    <small class="text-muted">Anderson de Castro Meneses</small><br>
                    <small class="text-muted">Joaquim Neves Cardoso</small><br>
                    <small class="text-muted">Reinan Souza da Silva</small>
                `);
            }
        },
        error: function() {
            console.error('Erro ao carregar dados do dashboard');
            $('#totalRefeicoes').text('Erro');
            $('#valorEstimado').text('Erro');
        }
    });
}

function carregarGraficoSemana() {
    // Calcular os últimos 7 dias a partir de hoje
    const hoje = new Date();
    const ultimos7Dias = [];
    
    for (let i = 6; i >= 0; i--) {
        const data = new Date(hoje);
        data.setDate(hoje.getDate() - i);
        ultimos7Dias.push(data);
    }
    
    // Preparar dados para a API
    const dataInicio = ultimos7Dias[0].toISOString().split('T')[0];
    const dataFim = ultimos7Dias[6].toISOString().split('T')[0];
    
    $.ajax({
        url: 'api/almoco/grafico_semana.php',
        type: 'GET',
        data: { 
            data_inicio: dataInicio,
            data_fim: dataFim 
        },
        dataType: 'json',
        success: function(data) {
            if (data && data.length > 0) {
                renderizarGrafico(data, ultimos7Dias);
            } else {
                // Dados simulados para demonstração
                const dadosSimulados = gerarDadosSimulados(ultimos7Dias);
                renderizarGrafico(dadosSimulados, ultimos7Dias);
            }
        },
        error: function() {
            console.error('Erro ao carregar dados do gráfico');
            // Dados simulados em caso de erro
            const dadosSimulados = gerarDadosSimulados(ultimos7Dias);
            renderizarGrafico(dadosSimulados, ultimos7Dias);
        }
    });
}

function gerarDadosSimulados(dias) {
    const nomesDias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    return dias.map((data, index) => ({
        dia: nomesDias[data.getDay()],
        quantidade: Math.floor(Math.random() * 50) + 10, // Simular entre 10 e 60
        data: data.toISOString().split('T')[0]
    }));
}

function renderizarGrafico(dados, ultimos7Dias) {
    const ctx = document.getElementById('graficoSemana').getContext('2d');
    
    // Destruir gráfico anterior se existir
    if (window.graficoSemana && typeof window.graficoSemana.destroy === 'function') {
        window.graficoSemana.destroy();
    }
    
    const dias = dados.map(item => item.dia);
    const valores = dados.map(item => item.quantidade);
    const datas = dados.map(item => item.data);
    
    // Destacar o dia atual (último dia)
    const hoje = new Date();
    const dataAtualIndex = dias.length - 1; // Último dia da lista
    
    const coresPontos = dias.map((d, i) => i === dataAtualIndex ? 'red' : 'rgba(75, 192, 192, 1)');
    const raioPontos = dias.map((d, i) => i === dataAtualIndex ? 8 : 4);
    
    window.graficoSemana = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: dias,
            datasets: [{
                label: 'Refeições',
                data: valores,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                pointBackgroundColor: coresPontos,
                pointRadius: raioPontos,
                pointHoverRadius: raioPontos
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 5
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            const index = context[0].dataIndex;
                            const data = datas[index];
                            const dataFormatada = new Date(data).toLocaleDateString('pt-BR');
                            return `${context[0].label} - ${dataFormatada}`;
                        },
                        label: function(context) {
                            return `Refeições: ${context.raw}`;
                        }
                    }
                }
            }
        }
    });
}
