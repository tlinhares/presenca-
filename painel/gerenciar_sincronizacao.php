<?php
// painel/gerenciar_sincronizacao.php
require_once "../api/conexao.php";
require_once "../inc/funcoes.php";
require_once "../inc/header.php";

// Verificar autenticação
verificaLogin();
verificaPermissao('admin');

$titulo = "Gerenciamento de Sincronização Facial";
$data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $titulo; ?></h1>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Status da Sincronização</h6>
                    <form class="form-inline">
                        <div class="form-group mx-sm-3 mb-2">
                            <label for="data" class="sr-only">Data</label>
                            <input type="date" class="form-control" id="data" name="data" value="<?php echo $data; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary mb-2">Consultar</button>
                    </form>
                </div>
                <div class="card-body">
                    <div id="status-data"></div>
                    
                    <div class="mb-4">
                        <h5 class="mb-3">Ações Disponíveis</h5>
                        <button id="btn-verificar-status" class="btn btn-info mr-2">
                            <i class="fas fa-sync-alt"></i> Verificar Status
                        </button>
                        <button id="btn-preparar-sync" class="btn btn-warning mr-2">
                            <i class="fas fa-tasks"></i> Preparar Sincronização
                        </button>
                        <button id="btn-executar-sync" class="btn btn-success mr-2">
                            <i class="fas fa-play"></i> Executar Sincronização
                        </button>
                        <button id="btn-retry-failed" class="btn btn-danger mr-2">
                            <i class="fas fa-redo"></i> Tentar Novamente Falhas
                        </button>
                        <button id="btn-corrigir-permissoes" class="btn btn-secondary">
                            <i class="fas fa-tools"></i> Verificar/Corrigir Permissões
                        </button>
                    </div>
                    
                    <div class="mb-4">
                        <h5 class="mb-3">Resultados</h5>
                        <div id="resultado" class="alert d-none"></div>
                    </div>
                    
                    <div class="mb-4">
                        <h5 class="mb-3">Falhas de Sincronização</h5>
                        <div id="tabela-falhas">
                            <p>Carregando dados...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Logs do Sistema</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <button id="btn-logs-sincronizacao" class="btn btn-outline-primary mr-2">Logs de Sincronização</button>
                        <button id="btn-logs-preparacao" class="btn btn-outline-primary mr-2">Logs de Preparação</button>
                        <button id="btn-logs-execucao" class="btn btn-outline-primary mr-2">Logs de Execução</button>
                        <button id="btn-logs-php" class="btn btn-outline-danger">Logs de Erro PHP</button>
                    </div>
                    
                    <div id="logs-container" style="max-height: 400px; overflow-y: auto; background-color: #f8f9fc; padding: 15px; border-radius: 5px; font-family: monospace;">
                        <p>Selecione um tipo de log para visualizar.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Carregar status inicial
    carregarStatus();
    
    // Botão para verificar status
    $("#btn-verificar-status").click(function() {
        carregarStatus();
    });
    
    // Botão para preparar sincronização
    $("#btn-preparar-sync").click(function() {
        executarAcao('preparar', 'Preparando sincronização...');
    });
    
    // Botão para executar sincronização
    $("#btn-executar-sync").click(function() {
        executarAcao('executar', 'Executando sincronização...');
    });
    
    // Botão para tentar novamente falhas
    $("#btn-retry-failed").click(function() {
        executarAcao('retry', 'Redefinindo registros com falha...');
    });
    
    // Botão para verificar/corrigir permissões
    $("#btn-corrigir-permissoes").click(function() {
        executarAcao('permissoes', 'Verificando permissões...');
    });
    
    // Botões para visualizar logs
    $("#btn-logs-sincronizacao").click(function() {
        carregarLogs('sincronizacao');
    });
    
    $("#btn-logs-preparacao").click(function() {
        carregarLogs('preparacao');
    });
    
    $("#btn-logs-execucao").click(function() {
        carregarLogs('execucao');
    });
    
    $("#btn-logs-php").click(function() {
        carregarLogs('php');
    });
    
    // Função para carregar status
    function carregarStatus() {
        const data = $("#data").val();
        mostrarResultado('info', 'Carregando status da sincronização...');
        
        $.ajax({
            url: '../api/facial/sync_status_handler.php',
            data: { 
                action: 'status',
                data: data
            },
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ok') {
                    atualizarStatus(response.estatisticas);
                    ocultarResultado();
                } else {
                    mostrarResultado('danger', 'Erro ao carregar status: ' + response.mensagem);
                }
            },
            error: function(xhr, status, error) {
                mostrarResultado('danger', 'Erro ao carregar status: ' + error);
            }
        });
    }
    
    // Função para executar ações
    function executarAcao(acao, mensagemProcessando) {
        const data = $("#data").val();
        mostrarResultado('info', mensagemProcessando);
        
        let url = '';
        let params = {};
        
        switch(acao) {
            case 'preparar':
                url = '../api/facial/preparar_sincronizacao.php';
                params = { data: data };
                break;
            case 'executar':
                url = '../api/facial/executar_sync.php';
                params = { limite: 10, max_execucoes: 5 };
                break;
            case 'retry':
                url = '../api/facial/sync_status_handler.php';
                params = { action: 'retry_failed', data: data };
                break;
            case 'permissoes':
                url = '../api/facial/testar_logs.php';
                params = {};
                break;
        }
        
        $.ajax({
            url: url,
            data: params,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ok') {
                    mostrarResultado('success', response.mensagem || 'Operação concluída com sucesso.');
                    
                    // Se for uma ação que altera status, atualizar
                    if (acao !== 'permissoes') {
                        setTimeout(carregarStatus, 1000);
                    }
                } else {
                    mostrarResultado('danger', 'Erro: ' + response.mensagem);
                }
            },
            error: function(xhr, status, error) {
                mostrarResultado('danger', 'Erro na requisição: ' + error);
            }
        });
    }
    
    // Função para carregar logs
    function carregarLogs(tipo) {
        let url = '../api/facial/obter_logs.php';
        let params = { tipo: tipo };
        
        $("#logs-container").html('<p>Carregando logs...</p>');
        
        $.ajax({
            url: url,
            data: params,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ok') {
                    let html = '';
                    if (response.linhas && response.linhas.length > 0) {
                        html = '<pre>' + response.linhas.join('\n') + '</pre>';
                    } else {
                        html = '<p>Nenhuma linha de log encontrada.</p>';
                    }
                    $("#logs-container").html(html);
                } else {
                    $("#logs-container").html('<p class="text-danger">Erro ao carregar logs: ' + response.mensagem + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $("#logs-container").html('<p class="text-danger">Erro na requisição: ' + error + '</p>');
            }
        });
    }
    
    // Função para atualizar status na tela
    function atualizarStatus(stats) {
        let html = `
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">${stats.total}</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pendentes</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">${stats.pendentes}</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Sincronizados</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">${stats.sincronizados}</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Falhas</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">${stats.falhas}</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $("#status-data").html(html);
        
        // Atualizar tabela de falhas
        if (stats.falhas > 0 && stats.detalhes_falhas && stats.detalhes_falhas.length > 0) {
            let tabelaHtml = `
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuário</th>
                                <th>Horário</th>
                                <th>Detalhes</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            stats.detalhes_falhas.forEach(function(falha) {
                tabelaHtml += `
                    <tr>
                        <td>${falha.id_usuario}</td>
                        <td>${falha.nome}</td>
                        <td>${falha.horario}</td>
                        <td>${falha.detalhes}</td>
                    </tr>
                `;
            });
            
            tabelaHtml += `
                        </tbody>
                    </table>
                </div>
            `;
            
            $("#tabela-falhas").html(tabelaHtml);
        } else {
            $("#tabela-falhas").html('<p>Não há registros de falha para esta data.</p>');
        }
    }
    
    // Função para mostrar resultado
    function mostrarResultado(tipo, mensagem) {
        $("#resultado").removeClass('d-none alert-success alert-info alert-warning alert-danger')
            .addClass('alert-' + tipo)
            .html(mensagem);
    }
    
    // Função para ocultar resultado
    function ocultarResultado() {
        $("#resultado").addClass('d-none');
    }
});
</script>

<?php
require_once "../inc/footer.php";
?> 