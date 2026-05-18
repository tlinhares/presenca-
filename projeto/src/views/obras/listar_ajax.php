<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Obras</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaObra">
            <i class="fas fa-plus"></i> Nova Obra
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Lista de Obras</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="tabelaObras" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Cliente</th>
                        <th>Data Início</th>
                        <th>Data Fim</th>
                        <th>Status</th>
                        <th>Orçamento</th>
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

<!-- Modal Nova/Editar Obra -->
<div class="modal fade" id="modalNovaObra" tabindex="-1" aria-labelledby="modalNovaObraLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovaObraLabel">Nova Obra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formObra">
                    <input type="hidden" id="obraId" name="id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nome" class="form-label">Nome da Obra *</label>
                            <input type="text" class="form-control" id="nome" name="nome" required>
                        </div>
                        <div class="col-md-6">
                            <label for="cliente_id" class="form-label">Cliente</label>
                            <select class="form-select" id="cliente_id" name="cliente_id">
                                <option value="">Selecione um cliente</option>
                                <!-- Opções de clientes serão carregadas via AJAX -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="endereco_obra" class="form-label">Endereço da Obra *</label>
                        <textarea class="form-control" id="endereco_obra" name="endereco_obra" rows="2" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="data_inicio_prevista" class="form-label">Data de Início Prevista *</label>
                            <input type="date" class="form-control" id="data_inicio_prevista" name="data_inicio_prevista" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="data_fim_prevista" class="form-label">Data de Término Prevista *</label>
                            <input type="date" class="form-control" id="data_fim_prevista" name="data_fim_prevista" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="status_id" class="form-label">Status *</label>
                            <select class="form-select" id="status_id" name="status_id" required>
                                <!-- Opções de status serão carregadas via AJAX -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="orcamento_total" class="form-label">Orçamento Total (R$)</label>
                        <input type="text" class="form-control" id="orcamento_total" name="orcamento_total" placeholder="0,00">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarObra">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Visualizar Obra -->
<div class="modal fade" id="modalVisualizarObra" tabindex="-1" aria-labelledby="modalVisualizarObraLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVisualizarObraLabel">Detalhes da Obra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detalhesObra">
                <!-- Conteúdo será carregado via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="btnEditarObra">Editar</button>
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
        var table = $('#tabelaObras').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
            },
            processing: true,
            serverSide: false,
            ajax: {
                url: '/api.php?action=getObras',
                dataSrc: function(json) {
                    return json.success ? json.data : [];
                }
            },
            columns: [
                { data: 'id' },
                { data: 'nome' },
                { 
                    data: 'cliente_nome',
                    render: function(data) {
                        return data || '-';
                    }
                },
                { 
                    data: 'data_inicio_prevista',
                    render: function(data) {
                        if (!data) return '-';
                        return new Date(data).toLocaleDateString('pt-BR');
                    }
                },
                { 
                    data: 'data_fim_prevista',
                    render: function(data) {
                        if (!data) return '-';
                        return new Date(data).toLocaleDateString('pt-BR');
                    }
                },
                { 
                    data: 'status_nome',
                    render: function(data) {
                        let badgeClass = 'bg-secondary';
                        
                        switch (data) {
                            case 'Planejada':
                                badgeClass = 'bg-secondary';
                                break;
                            case 'Em Andamento':
                                badgeClass = 'bg-primary';
                                break;
                            case 'Concluída':
                                badgeClass = 'bg-success';
                                break;
                            case 'Pausada':
                                badgeClass = 'bg-warning';
                                break;
                            case 'Cancelada':
                                badgeClass = 'bg-danger';
                                break;
                        }
                        
                        return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                    }
                },
                { 
                    data: 'orcamento_total',
                    render: function(data) {
                        if (!data) return '-';
                        return 'R$ ' + parseFloat(data).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
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
        
        // Carregar opções de clientes e status
        $.getJSON('/api.php?action=getClientes', function(response) {
            if (response.success) {
                let options = '<option value="">Selecione um cliente</option>';
                $.each(response.data, function(index, cliente) {
                    options += '<option value="' + cliente.id + '">' + cliente.nome + '</option>';
                });
                $('#cliente_id').html(options);
            }
        });
        
        $.getJSON('/api.php?action=getStatusObra', function(response) {
            if (response.success) {
                let options = '';
                $.each(response.data, function(index, status) {
                    options += '<option value="' + status.id + '">' + status.nome + '</option>';
                });
                $('#status_id').html(options);
            }
        });
        
        // Máscara para campo de orçamento
        $('#orcamento_total').on('input', function() {
            let value = $(this).val().replace(/\D/g, '');
            value = (parseInt(value) / 100).toFixed(2) + '';
            value = value.replace(".", ",");
            value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
            $(this).val(value);
        });
        
        // Validação de datas
        $('#data_fim_prevista').on('change', function() {
            const dataInicio = new Date($('#data_inicio_prevista').val());
            const dataFim = new Date($(this).val());
            
            if (dataFim < dataInicio) {
                alert('A data de término não pode ser anterior à data de início.');
                $(this).val('');
            }
        });
        
        // Salvar obra
        $('#btnSalvarObra').on('click', function() {
            const form = $('#formObra')[0];
            
            // Validar campos obrigatórios
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const formData = new FormData(form);
            const id = $('#obraId').val();
            const action = id ? 'atualizarObra' : 'salvarObra';
            
            // Enviar dados via AJAX
            $.ajax({
                url: '/api.php?action=' + action,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert(id ? 'Obra atualizada com sucesso!' : 'Obra salva com sucesso!');
                        $('#modalNovaObra').modal('hide');
                        table.ajax.reload();
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function(xhr) {
                    alert('Erro ao salvar obra: ' + xhr.responseText);
                }
            });
        });
        
        // Visualizar obra
        $(document).on('click', '.btn-visualizar', function() {
            const id = $(this).data('id');
            
            $.getJSON('/api.php?action=getObra&id=' + id, function(response) {
                if (response.success) {
                    const obra = response.data;
                    
                    let html = `
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Nome da Obra</h6>
                                <p>${obra.nome}</p>
                            </div>
                            <div class="col-md-6">
                                <h6>Cliente</h6>
                                <p>${obra.cliente_nome || '-'}</p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Descrição</h6>
                            <p>${obra.descricao || '-'}</p>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Endereço da Obra</h6>
                            <p>${obra.endereco_obra}</p>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <h6>Data de Início Prevista</h6>
                                <p>${new Date(obra.data_inicio_prevista).toLocaleDateString('pt-BR')}</p>
                            </div>
                            <div class="col-md-4">
                                <h6>Data de Término Prevista</h6>
                                <p>${new Date(obra.data_fim_prevista).toLocaleDateString('pt-BR')}</p>
                            </div>
                            <div class="col-md-4">
                                <h6>Status</h6>
                                <p>${obra.status_nome}</p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Orçamento Total</h6>
                            <p>${obra.orcamento_total ? 'R$ ' + parseFloat(obra.orcamento_total).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) : '-'}</p>
                        </div>
                    `;
                    
                    $('#detalhesObra').html(html);
                    $('#btnEditarObra').data('id', id);
                    $('#modalVisualizarObra').modal('show');
                } else {
                    alert('Erro: ' + response.message);
                }
            });
        });
        
        // Editar obra
        $(document).on('click', '#btnEditarObra, .btn-editar', function() {
            const id = $(this).data('id');
            
            $.getJSON('/api.php?action=getObra&id=' + id, function(response) {
                if (response.success) {
                    const obra = response.data;
                    
                    // Preencher formulário com dados da obra
                    $('#obraId').val(obra.id);
                    $('#nome').val(obra.nome);
                    $('#cliente_id').val(obra.cliente_id);
                    $('#descricao').val(obra.descricao);
                    $('#endereco_obra').val(obra.endereco_obra);
                    $('#data_inicio_prevista').val(obra.data_inicio_prevista);
                    $('#data_fim_prevista').val(obra.data_fim_prevista);
                    $('#status_id').val(obra.status_id);
                    $('#orcamento_total').val(obra.orcamento_total ? parseFloat(obra.orcamento_total).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace('.', ',') : '');
                    
                    // Fechar modal de visualização se estiver aberto
                    $('#modalVisualizarObra').modal('hide');
                    
                    // Abrir modal de edição
                    $('#modalNovaObraLabel').text('Editar Obra');
                    $('#modalNovaObra').modal('show');
                } else {
                    alert('Erro: ' + response.message);
                }
            });
        });
        
        // Excluir obra
        $(document).on('click', '.btn-excluir', function() {
            const id = $(this).data('id');
            
            if (confirm('Tem certeza que deseja excluir esta obra?')) {
                $.ajax({
                    url: '/api.php?action=excluirObra',
                    type: 'POST',
                    data: { id: id },
                    success: function(response) {
                        if (response.success) {
                            alert('Obra excluída com sucesso!');
                            table.ajax.reload();
                        } else {
                            alert('Erro: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        alert('Erro ao excluir obra: ' + xhr.responseText);
                    }
                });
            }
        });
        
        // Resetar formulário ao abrir modal para nova obra
        $('#modalNovaObra').on('show.bs.modal', function(event) {
            if (!$(event.relatedTarget).hasClass('btn-editar')) {
                $('#formObra')[0].reset();
                $('#obraId').val('');
                $('#modalNovaObraLabel').text('Nova Obra');
                
                // Definir data atual para início
                const hoje = new Date().toISOString().split('T')[0];
                $('#data_inicio_prevista').val(hoje);
            }
        });
    });
</script>
