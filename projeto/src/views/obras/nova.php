<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Nova Obra</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="/obras" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Cadastro de Nova Obra</h6>
    </div>
    <div class="card-body">
        <form action="/obras/salvar" method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nome" class="form-label">Nome da Obra *</label>
                    <input type="text" class="form-control" id="nome" name="nome" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="cliente_id" class="form-label">Cliente</label>
                    <select class="form-select" id="cliente_id" name="cliente_id">
                        <option value="">Selecione um cliente</option>
                        <?php
                        // Incluir o modelo de obras se ainda não foi incluído
                        if (!class_exists('ObraModel')) {
                            require_once SRC_PATH . '/models/obra_model.php';
                            
                            // Criar instância do banco de dados
                            $database = new Database();
                            $db = $database->getConnection();
                            
                            // Criar instância do modelo de obras
                            $obraModel = new ObraModel($db);
                        }
                        
                        // Obter todos os clientes
                        $clientes = $obraModel->getAllClientes();
                        
                        // Listar clientes
                        foreach ($clientes as $cliente) {
                            echo '<option value="' . $cliente['id'] . '">' . $cliente['nome'] . '</option>';
                        }
                        ?>
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
                        <?php
                        // Obter todos os status
                        $status = $obraModel->getAllStatus();
                        
                        // Listar status
                        foreach ($status as $s) {
                            // Selecionar "Planejada" como padrão
                            $selected = ($s['nome'] === 'Planejada') ? 'selected' : '';
                            echo '<option value="' . $s['id'] . '" ' . $selected . '>' . $s['nome'] . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="orcamento_total" class="form-label">Orçamento Total (R$)</label>
                <input type="text" class="form-control" id="orcamento_total" name="orcamento_total" placeholder="0,00">
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="reset" class="btn btn-secondary me-md-2">Limpar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
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
    });
</script>
