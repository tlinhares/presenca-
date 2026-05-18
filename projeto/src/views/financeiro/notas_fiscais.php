<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Notas Fiscais</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaNota">
            <i class="fas fa-plus"></i> Nova Nota Fiscal
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">Filtros</h6>
        <button class="btn btn-sm btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFilters" aria-expanded="false" aria-controls="collapseFilters">
            <i class="fas fa-filter"></i> Mostrar/Ocultar
        </button>
    </div>
    <div class="collapse" id="collapseFilters">
        <div class="card-body">
            <form id="formFiltros" class="row g-3">
                <div class="col-md-3">
                    <label for="filtroObra" class="form-label">Obra</label>
                    <select class="form-select" id="filtroObra" name="obra_id">
                        <option value="">Todas</option>
                        <!-- Opções de obras serão carregadas via AJAX -->
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filtroFornecedor" class="form-label">Fornecedor</label>
                    <select class="form-select" id="filtroFornecedor" name="fornecedor_id">
                        <option value="">Todos</option>
                        <!-- Opções de fornecedores serão carregadas via AJAX -->
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filtroDataInicio" class="form-label">Data Início</label>
                    <input type="date" class="form-control" id="filtroDataInicio" name="data_inicio">
                </div>
                <div class="col-md-3">
                    <label for="filtroDataFim" class="form-label">Data Fim</label>
                    <input type="date" class="form-control" id="filtroDataFim" name="data_fim">
                </div>
                <div class="col-12 text-end">
                    <button type="button" class="btn btn-secondary" id="btnLimparFiltros">Limpar</button>
                    <button type="button" class="btn btn-primary" id="btnFiltrar">Filtrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Lista de Notas Fiscais</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="tabelaNotasFiscais" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Fornecedor</th>
                        <th>Obra</th>
                        <th>Data Emissão</th>
                        <th>Valor Total</th>
                        <th>Forma Pagamento</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dados serão carregados via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nova Nota Fiscal -->
<div class="modal fade" id="modalNovaNota" tabindex="-1" aria-labelledby="modalNovaNotaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovaNotaLabel">Nova Nota Fiscal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formNotaFiscal">
                    <input type="hidden" id="notaId" name="id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="numero" class="form-label">Número da Nota Fiscal *</label>
                            <input type="text" class="form-control" id="numero" name="numero" required>
                        </div>
                        <div class="col-md-6">
                            <label for="data_emissao" class="form-label">Data de Emissão *</label>
                            <input type="date" class="form-control" id="data_emissao" name="data_emissao" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="fornecedor_id" class="form-label">Fornecedor *</label>
                            <select class="form-select" id="fornecedor_id" name="fornecedor_id" required>
                                <option value="">Selecione um fornecedor</option>
                                <!-- Opções de fornecedores serão carregadas via AJAX -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="obra_id" class="form-label">Obra *</label>
                            <select class="form-select" id="obra_id" name="obra_id" required>
                                <option value="">Selecione uma obra</option>
                                <!-- Opções de obras serão carregadas via AJAX -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="valor_total" class="form-label">Valor Total (R$) *</label>
                            <input type="text" class="form-control" id="valor_total" name="valor_total" placeholder="0,00" required>
                        </div>
                        <div class="col-md-6">
                            <label for="forma_pagamento" class="form-label">Forma de Pagamento *</label>
                            <select class="form-select" id="forma_pagamento" name="forma_pagamento" required>
                                <option value="">Selecione</option>
                                <option value="À Vista">À Vista</option>
                                <option value="Parcelado">Parcelado</option>
                                <option value="Boleto">Boleto</option>
                                <option value="Transferência">Transferência</option>
                                <option value="Cartão de Crédito">Cartão de Crédito</option>
                                <option value="Cartão de Débito">Cartão de Débito</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Dinheiro">Dinheiro</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status_pagamento" class="form-label">Status do Pagamento *</label>
                        <select class="form-select" id="status_pagamento" name="status_pagamento" required>
                            <option value="Pendente">Pendente</option>
                            <option value="Pago">Pago</option>
                            <option value="Parcialmente Pago">Parcialmente Pago</option>
                            <option value="Cancelado">Cancelado</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="arquivo" class="form-label">Anexar Nota Fiscal (PDF)</label>
                        <input type="file" class="form-control" id="arquivo" name="arquivo" accept=".pdf">
                    </div>
                    
                    <div id="secaoParcelas" class="mt-4 d-none">
                        <h5>Parcelas</h5>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Defina as parcelas de pagamento.
                        </div>
                        
                        <div class="mb-3 row">
                            <div class="col-md-4">
                                <label for="num_parcelas" class="form-label">Número de Parcelas</label>
                                <input type="number" class="form-control" id="num_parcelas" min="1" max="36" value="1">
                            </div>
                            <div class="col-md-4">
                                <label for="intervalo_dias" class="form-label">Intervalo (dias)</label>
                                <input type="number" class="form-control" id="intervalo_dias" min="1" value="30">
                            </div>
                            <div class="col-md-4">
                                <label for="data_primeira_parcela" class="form-label">Data 1ª Parcela</label>
                                <input type="date" class="form-control" id="data_primeira_parcela">
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-secondary mb-3" id="btnGerarParcelas">Gerar Parcelas</button>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered" id="tabelaParcelas">
                                <thead>
                                    <tr>
                                        <th>Nº</th>
                                        <th>Valor (R$)</th>
                                        <th>Vencimento</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Parcelas serão geradas dinamicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarNota">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Visualizar Nota Fiscal -->
<div class="modal fade" id="modalVisualizarNota" tabindex="-1" aria-labelledby="modalVisualizarNotaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVisualizarNotaLabel">Detalhes da Nota Fiscal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detalhesNotaFiscal">
                <!-- Conteúdo será carregado via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="btnEditarNota">Editar</button>
            </div>
        </div>
    </div>
</div>

<!-- DataTables -->
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        // Inicializar DataTable
        var table = $('#tabelaNotasFiscais').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
            },
            processing: true,
            serverSide: false,
            ajax: {
                url: '/api/notas-fiscais',
                dataSrc: ''
            },
            columns: [
                { data: 'numero' },
                { data: 'fornecedor_nome' },
                { data: 'obra_nome' },
                { 
                    data: 'data_emissao',
                    render: function(data) {
                        if (!data) return '-';
                        return new Date(data).toLocaleDateString('pt-BR');
                    }
                },
                { 
                    data: 'valor_total',
                    render: function(data) {
                        return 'R$ ' + parseFloat(data).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                    }
                },
                { data: 'forma_pagamento' },
                { 
                    data: 'status_pagamento',
                    render: function(data) {
                        let badgeClass = 'bg-secondary';
                        
                        switch (data) {
                            case 'Pendente':
                                badgeClass = 'bg-warning';
                                break;
                            case 'Pago':
                                badgeClass = 'bg-success';
                                break;
                            case 'Parcialmente Pago':
                                badgeClass = 'bg-info';
                                break;
                            case 'Cancelado':
                                badgeClass = 'bg-danger';
                                break;
                        }
                        
                        return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                    }
                },
                { 
                    data: 'id',
                    render: function(data) {
                        return '<button class="btn btn-sm btn-info btn-visualizar" data-id="' + data + '" title="Visualizar"><i class="fas fa-eye"></i></button> ' +
                               '<button class="btn btn-sm btn-warning btn-editar" data-id="' + data + '" title="Editar"><i class="fas fa-edit"></i></button> ' +
                               '<button class="btn btn-sm btn-danger btn-excluir" data-id="' + data + '" title="Excluir"><i class="fas fa-trash"></i></button>';
                    }
                }
            ]
        });
        
        // Carregar opções de obras e fornecedores
        $.getJSON('/api/obras', function(data) {
            let options = '<option value="">Todas</option>';
            $.each(data, function(index, obra) {
                options += '<option value="' + obra.id + '">' + obra.nome + '</option>';
            });
            $('#filtroObra, #obra_id').html(options);
        });
        
        $.getJSON('/api/fornecedores', function(data) {
            let options = '<option value="">Todos</option>';
            $.each(data, function(index, fornecedor) {
                options += '<option value="' + fornecedor.id + '">' + fornecedor.nome_fantasia + '</option>';
            });
            $('#filtroFornecedor, #fornecedor_id').html(options);
        });
        
        // Filtrar notas fiscais
        $('#btnFiltrar').on('click', function() {
            const filtros = $('#formFiltros').serialize();
            table.ajax.url('/api/notas-fiscais?' + filtros).load();
        });
        
        // Limpar filtros
        $('#btnLimparFiltros').on('click', function() {
            $('#formFiltros')[0].reset();
            table.ajax.url('/api/notas-fiscais').load();
        });
        
        // Mostrar/ocultar seção de parcelas
        $('#forma_pagamento').on('change', function() {
            if ($(this).val() === 'Parcelado') {
                $('#secaoParcelas').removeClass('d-none');
            } else {
                $('#secaoParcelas').addClass('d-none');
            }
        });
        
        // Gerar parcelas
        $('#btnGerarParcelas').on('click', function() {
            const numParcelas = parseInt($('#num_parcelas').val());
            const intervaloDias = parseInt($('#intervalo_dias').val());
            const dataPrimeiraParcela = $('#data_primeira_parcela').val();
            const valorTotal = parseFloat($('#valor_total').val().replace('.', '').replace(',', '.'));
            
            if (!numParcelas || !dataPrimeiraParcela || isNaN(valorTotal)) {
                alert('Preencha o número de parcelas, data da primeira parcela e valor total.');
                return;
            }
            
            const valorParcela = valorTotal / numParcelas;
            let html = '';
            
            for (let i = 1; i <= numParcelas; i++) {
                const dataVencimento = new Date(dataPrimeiraParcela);
                dataVencimento.setDate(dataVencimento.getDate() + (i - 1) * intervaloDias);
                
                const dataFormatada = dataVencimento.toISOString().split('T')[0];
                const valorFormatado = valorParcela.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                
                html += `
                    <tr>
                        <td>${i}</td>
                        <td>
                            <input type="hidden" name="parcelas[${i-1}][numero_parcela]" value="${i}">
                            <input type="text" class="form-control form-control-sm valor-parcela" name="parcelas[${i-1}][valor]" value="${valorFormatado}">
                        </td>
                        <td>
                            <input type="date" class="form-control form-control-sm" name="parcelas[${i-1}][data_vencimento]" value="${dataFormatada}">
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="parcelas[${i-1}][status]">
                                <option value="Pendente">Pendente</option>
                                <option value="Pago">Pago</option>
                            </select>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-danger btn-remover-parcela"><i class="fas fa-times"></i></button>
                        </td>
                    </tr>
                `;
            }
            
            $('#tabelaParcelas tbody').html(html);
            
            // Aplicar máscara aos valores das parcelas
            $('.valor-parcela').on('input', function() {
                let value = $(this).val().replace(/\D/g, '');
                value = (parseInt(value) / 100).toFixed(2) + '';
                value = value.replace(".", ",");
                value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
                $(this).val(value);
            });
        });
        
        // Remover parcela
        $(document).on('click', '.btn-remover-parcela', function() {
            $(this).closest('tr').remove();
            
            // Renumerar parcelas
            $('#tabelaParcelas tbody tr').each(function(index) {
                $(this).find('td:first').text(index + 1);
                $(this).find('input, select').each(function() {
                    const name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                    }
                });
            });
        });
        
        // Máscara para campo de valor total
        $('#valor_total').on('input', function() {
            let value = $(this).val().replace(/\D/g, '');
            value = (parseInt(value) / 100).toFixed(2) + '';
            value = value.replace(".", ",");
            value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
            $(this).val(value);
        });
        
        // Salvar nota fiscal
        $('#btnSalvarNota').on('click', function() {
            const form = $('#formNotaFiscal')[0];
            const formData = new FormData(form);
            
            // Validar campos obrigatórios
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // Verificar se há parcelas quando forma de pagamento é parcelado
            if ($('#forma_pagamento').val() === 'Parcelado' && $('#tabelaParcelas tbody tr').length === 0) {
                alert('Por favor, gere as parcelas para o pagamento parcelado.');
                return;
            }
            
            // Adicionar arquivo se selecionado
            const arquivo = $('#arquivo')[0].files[0];
            if (arquivo) {
                formData.append('arquivo', arquivo);
            }
            
            // Enviar dados via AJAX
            $.ajax({
                url: '/api/notas-fiscais/salvar',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    alert('Nota fiscal salva com sucesso!');
                    $('#modalNovaNota').modal('hide');
                    table.ajax.reload();
                },
                error: function(xhr) {
                    alert('Erro ao salvar nota fiscal: ' + xhr.responseText);
                }
            });
        });
        
        // Visualizar nota fiscal
        $(document).on('click', '.btn-visualizar', function() {
            const id = $(this).data('id');
            
            $.getJSON('/api/notas-fiscais/' + id, function(data) {
                let html = `
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Número da Nota Fiscal</h6>
                            <p>${data.numero}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Data de Emissão</h6>
                            <p>${new Date(data.data_emissao).toLocaleDateString('pt-BR')}</p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Fornecedor</h6>
                            <p>${data.fornecedor_nome}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Obra</h6>
                            <p>${data.obra_nome}</p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Valor Total</h6>
                            <p>R$ ${parseFloat(data.valor_total).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Forma de Pagamento</h6>
                            <p>${data.forma_pagamento}</p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Status do Pagamento</h6>
                            <p>${data.status_pagamento}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Descrição</h6>
                            <p>${data.descricao || '-'}</p>
                        </div>
                    </div>
                `;
                
                if (data.arquivo_path) {
                    html += `
                        <div class="mb-3">
                            <h6>Arquivo</h6>
                            <p><a href="${data.arquivo_path}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-pdf"></i> Visualizar PDF</a></p>
                        </div>
                    `;
                }
                
                // Adicionar parcelas se existirem
                if (data.parcelas && data.parcelas.length > 0) {
                    html += `
                        <h5 class="mt-4">Parcelas</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Nº</th>
                                        <th>Valor (R$)</th>
                                        <th>Vencimento</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    data.parcelas.forEach(function(parcela) {
                        let statusClass = 'bg-warning';
                        if (parcela.status === 'Pago') {
                            statusClass = 'bg-success';
                        }
                        
                        html += `
                            <tr>
                                <td>${parcela.numero_parcela}</td>
                                <td>R$ ${parseFloat(parcela.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                                <td>${new Date(parcela.data_vencimento).toLocaleDateString('pt-BR')}</td>
                                <td><span class="badge ${statusClass}">${parcela.status}</span></td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                }
                
                $('#detalhesNotaFiscal').html(html);
                $('#btnEditarNota').data('id', id);
                $('#modalVisualizarNota').modal('show');
            });
        });
        
        // Editar nota fiscal
        $(document).on('click', '#btnEditarNota, .btn-editar', function() {
            const id = $(this).data('id');
            
            $.getJSON('/api/notas-fiscais/' + id, function(data) {
                // Preencher formulário com dados da nota fiscal
                $('#notaId').val(data.id);
                $('#numero').val(data.numero);
                $('#data_emissao').val(data.data_emissao);
                $('#fornecedor_id').val(data.fornecedor_id);
                $('#obra_id').val(data.obra_id);
                $('#valor_total').val(parseFloat(data.valor_total).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace('.', ','));
                $('#forma_pagamento').val(data.forma_pagamento);
                $('#descricao').val(data.descricao);
                $('#status_pagamento').val(data.status_pagamento);
                
                // Mostrar/ocultar seção de parcelas
                if (data.forma_pagamento === 'Parcelado') {
                    $('#secaoParcelas').removeClass('d-none');
                    
                    // Preencher tabela de parcelas
                    if (data.parcelas && data.parcelas.length > 0) {
                        let html = '';
                        
                        data.parcelas.forEach(function(parcela, index) {
                            html += `
                                <tr>
                                    <td>${parcela.numero_parcela}</td>
                                    <td>
                                        <input type="hidden" name="parcelas[${index}][numero_parcela]" value="${parcela.numero_parcela}">
                                        <input type="text" class="form-control form-control-sm valor-parcela" name="parcelas[${index}][valor]" value="${parseFloat(parcela.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 }).replace('.', ',')}">
                                    </td>
                                    <td>
                                        <input type="date" class="form-control form-control-sm" name="parcelas[${index}][data_vencimento]" value="${parcela.data_vencimento}">
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="parcelas[${index}][status]">
                                            <option value="Pendente" ${parcela.status === 'Pendente' ? 'selected' : ''}>Pendente</option>
                                            <option value="Pago" ${parcela.status === 'Pago' ? 'selected' : ''}>Pago</option>
                                        </select>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger btn-remover-parcela"><i class="fas fa-times"></i></button>
                                    </td>
                                </tr>
                            `;
                        });
                        
                        $('#tabelaParcelas tbody').html(html);
                        
                        // Aplicar máscara aos valores das parcelas
                        $('.valor-parcela').on('input', function() {
                            let value = $(this).val().replace(/\D/g, '');
                            value = (parseInt(value) / 100).toFixed(2) + '';
                            value = value.replace(".", ",");
                            value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
                            $(this).val(value);
                        });
                    }
                } else {
                    $('#secaoParcelas').addClass('d-none');
                }
                
                // Fechar modal de visualização se estiver aberto
                $('#modalVisualizarNota').modal('hide');
                
                // Abrir modal de edição
                $('#modalNovaNotaLabel').text('Editar Nota Fiscal');
                $('#modalNovaNota').modal('show');
            });
        });
        
        // Excluir nota fiscal
        $(document).on('click', '.btn-excluir', function() {
            const id = $(this).data('id');
            
            if (confirm('Tem certeza que deseja excluir esta nota fiscal?')) {
                $.ajax({
                    url: '/api/notas-fiscais/excluir/' + id,
                    type: 'POST',
                    success: function() {
                        alert('Nota fiscal excluída com sucesso!');
                        table.ajax.reload();
                    },
                    error: function(xhr) {
                        alert('Erro ao excluir nota fiscal: ' + xhr.responseText);
                    }
                });
            }
        });
        
        // Resetar formulário ao abrir modal para nova nota
        $('#modalNovaNota').on('show.bs.modal', function(event) {
            if (!$(event.relatedTarget).hasClass('btn-editar')) {
                $('#formNotaFiscal')[0].reset();
                $('#notaId').val('');
                $('#tabelaParcelas tbody').empty();
                $('#secaoParcelas').addClass('d-none');
                $('#modalNovaNotaLabel').text('Nova Nota Fiscal');
                
                // Definir data atual para emissão e primeira parcela
                const hoje = new Date().toISOString().split('T')[0];
                $('#data_emissao').val(hoje);
                $('#data_primeira_parcela').val(hoje);
            }
        });
    });
</script>
