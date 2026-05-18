<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Obras</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="/obras/nova" class="btn btn-sm btn-primary">
            <i class="fas fa-plus"></i> Nova Obra
        </a>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Lista de Obras</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
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
                    <?php
                    // Incluir o modelo de obras
                    require_once SRC_PATH . '/models/obra_model.php';
                    
                    // Criar instância do banco de dados
                    $database = new Database();
                    $db = $database->getConnection();
                    
                    // Criar instância do modelo de obras
                    $obraModel = new ObraModel($db);
                    
                    // Obter todas as obras
                    $obras = $obraModel->getAll();
                    
                    // Verificar se existem obras
                    if (count($obras) > 0) {
                        foreach ($obras as $obra) {
                            // Formatar valores
                            $dataInicio = !empty($obra['data_inicio_prevista']) ? date('d/m/Y', strtotime($obra['data_inicio_prevista'])) : '-';
                            $dataFim = !empty($obra['data_fim_prevista']) ? date('d/m/Y', strtotime($obra['data_fim_prevista'])) : '-';
                            $orcamento = !empty($obra['orcamento_total']) ? 'R$ ' . number_format($obra['orcamento_total'], 2, ',', '.') : '-';
                            
                            // Definir classe de status
                            $statusClass = '';
                            switch ($obra['status_nome']) {
                                case 'Planejada':
                                    $statusClass = 'bg-secondary';
                                    break;
                                case 'Em Andamento':
                                    $statusClass = 'bg-primary';
                                    break;
                                case 'Concluída':
                                    $statusClass = 'bg-success';
                                    break;
                                case 'Pausada':
                                    $statusClass = 'bg-warning';
                                    break;
                                case 'Cancelada':
                                    $statusClass = 'bg-danger';
                                    break;
                            }
                            
                            echo '<tr>';
                            echo '<td>' . $obra['id'] . '</td>';
                            echo '<td>' . $obra['nome'] . '</td>';
                            echo '<td>' . ($obra['cliente_nome'] ?? '-') . '</td>';
                            echo '<td>' . $dataInicio . '</td>';
                            echo '<td>' . $dataFim . '</td>';
                            echo '<td><span class="badge ' . $statusClass . '">' . $obra['status_nome'] . '</span></td>';
                            echo '<td>' . $orcamento . '</td>';
                            echo '<td>
                                    <a href="/obras/visualizar/' . $obra['id'] . '" class="btn btn-sm btn-info" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="/obras/editar/' . $obra['id'] . '" class="btn btn-sm btn-warning" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="/obras/excluir/' . $obra['id'] . '" class="btn btn-sm btn-danger btn-delete" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                  </td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="8" class="text-center">Nenhuma obra cadastrada.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- DataTables -->
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#dataTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
            }
        });
    });
</script>
