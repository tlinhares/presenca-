<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary">Exportar</button>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
            <i class="fas fa-calendar"></i> Esta semana
        </button>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Obras em Andamento</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">1</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-building fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Orçamento Total</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ 500.000,00</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Tarefas Pendentes</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">18</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tasks fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Progresso das Obras</h6>
            </div>
            <div class="card-body">
                <h4>Condomínio Residencial (6 casas)</h4>
                <div class="progress mb-4">
                    <div class="progress-bar bg-danger" role="progressbar" style="width: 20%" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <h4>Fundação</h4>
                <div class="progress mb-4">
                    <div class="progress-bar" role="progressbar" style="width: 60%" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <h4>Estrutura</h4>
                <div class="progress mb-4">
                    <div class="progress-bar bg-info" role="progressbar" style="width: 40%" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <h4>Alvenaria</h4>
                <div class="progress mb-4">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: 10%" aria-valuenow="10" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Tarefas Recentes</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Tarefa</th>
                                <th>Responsável</th>
                                <th>Status</th>
                                <th>Prazo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Concretagem da fundação</td>
                                <td>João Silva</td>
                                <td><span class="badge bg-success">Concluída</span></td>
                                <td>15/05/2025</td>
                            </tr>
                            <tr>
                                <td>Montagem de armação</td>
                                <td>Carlos Oliveira</td>
                                <td><span class="badge bg-warning">Em andamento</span></td>
                                <td>30/05/2025</td>
                            </tr>
                            <tr>
                                <td>Instalação elétrica</td>
                                <td>Pedro Santos</td>
                                <td><span class="badge bg-secondary">A fazer</span></td>
                                <td>15/06/2025</td>
                            </tr>
                            <tr>
                                <td>Instalação hidráulica</td>
                                <td>Marcos Souza</td>
                                <td><span class="badge bg-secondary">A fazer</span></td>
                                <td>20/06/2025</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Resumo Financeiro</h6>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="financialChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    $(document).ready(function() {
        // Gráfico financeiro
        var ctx = document.getElementById('financialChart').getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio'],
                datasets: [{
                    label: 'Orçado',
                    data: [50000, 60000, 70000, 80000, 90000],
                    backgroundColor: 'rgba(78, 115, 223, 0.5)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1
                }, {
                    label: 'Realizado',
                    data: [45000, 65000, 60000, 85000, 75000],
                    backgroundColor: 'rgba(28, 200, 138, 0.5)',
                    borderColor: 'rgba(28, 200, 138, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
</script>
