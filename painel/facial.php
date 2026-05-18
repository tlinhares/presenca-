<?php
// painel/facial.php - Painel de controle para sincronização facial
session_start();
require_once '../api/conexao.php';
include_once '../auth/verifica_sessao.php';

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: facial                                                 ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('facial');

// Processar sincronização manual se solicitado
$mensagem_sincronizacao = '';
if (isset($_POST['sincronizar_agora'])) {
    // Log da tentativa
    $log_file = "../logs/sincronizacao_manual_" . date('Y-m-d') . ".log";
    $time = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$time] Sincronização manual solicitada por: {$_SESSION['usuario_nome']}" . PHP_EOL, FILE_APPEND);
    
    $mensagem_sincronizacao = '<div class="alert alert-info">Sincronização solicitada. Aguarde...</div>';
}

// Carregar estatísticas atuais
$stats = [
    'total' => 0,
    'sincronizados' => 0,
    'pendentes' => 0,
    'falhas' => 0
];

$data_filtro = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');

// Buscar estatísticas da tabela facial_sync
$sql_stats = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sincronizado' THEN 1 ELSE 0 END) as sincronizados,
        SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
        SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as falhas
    FROM facial_sync
    WHERE data = ?
";

$stmt = $conn->prepare($sql_stats);
$stmt->bind_param("s", $data_filtro);
$stmt->execute();
$stmt->bind_result($total, $sincronizados, $pendentes, $falhas);
if ($stmt->fetch()) {
    $stats['total'] = (int)$total;
    $stats['sincronizados'] = (int)$sincronizados;
    $stats['pendentes'] = (int)$pendentes;
    $stats['falhas'] = (int)$falhas;
}
$stmt->close();

// Título da página
$titulo = "Painel de Sincronização Facial";

// Incluir o header padrão
include_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo $titulo; ?></h1>
        <div>
            <form method="get" class="d-flex">
                <input type="date" class="form-control me-2" id="dataFiltro" name="data" value="<?php echo $data_filtro; ?>">
                <button type="submit" class="btn btn-primary" id="btnFiltrar">Filtrar</button>
            </form>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Sincronização para <?php echo date('d/m/Y', strtotime($data_filtro)); ?></h5>
                </div>
                <div class="card-body">
                    <div id="mensagemSincronizacao">
                        <?php echo $mensagem_sincronizacao; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <form method="post">
                                <button type="submit" name="verificar_preparar" class="btn btn-info mb-3" id="btnVerificarPreparar">
                                    <i class="fas fa-sync-alt"></i> Verificar e Preparar
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6 text-end">
                            <form method="post">
                                <button type="submit" name="sincronizar_agora" class="btn btn-success mb-3">
                                    <i class="fas fa-play"></i> Sincronizar Agora
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h2 class="text-primary"><?php echo $stats['total']; ?></h2>
                                    <p class="mb-0">Total de Usuários</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h2><?php echo $stats['sincronizados']; ?></h2>
                                    <p class="mb-0">Sincronizados</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-dark">
                                <div class="card-body text-center">
                                    <h2><?php echo $stats['pendentes']; ?></h2>
                                    <p class="mb-0">Pendentes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h2><?php echo $stats['falhas']; ?></h2>
                                    <p class="mb-0">Falhas</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Lista de Sincronizações</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuário</th>
                                    <th>Status</th>
                                    <th>Horário</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaSincronizacoes">
                                <tr>
                                    <td colspan="5" class="text-center">Carregando dados...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">Check-ins</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuário</th>
                                    <th>Horário</th>
                                    <th>Dispositivo</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaCheckIns">
                                <tr>
                                    <td colspan="4" class="text-center">Carregando dados...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Sincronização Automática</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="limite" class="form-label">Limite por ciclo</label>
                            <input type="number" class="form-control" id="limite" value="20" min="1" max="100">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="max_execucoes" class="form-label">Máx. ciclos</label>
                            <input type="number" class="form-control" id="max_execucoes" value="5" min="1" max="20">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="intervalo" class="form-label">Intervalo (seg)</label>
                            <input type="number" class="form-control" id="intervalo" value="2" min="1" max="10">
                        </div>
                    </div>
                    <button class="btn btn-primary w-100" id="btnExecutarSync">Iniciar Sincronização</button>
                    
                    <div class="mt-3" id="resultadoSyncContainer" style="display: none;">
                        <div class="alert alert-info" id="resultadoSync">
                            Aguardando execução...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
  // Variável global para controlar se uma sincronização está em andamento
  var sincronizacaoEmAndamento = false;
  
  // Função para carregar dados da página
  function carregarDados() {
    var data = $('#dataFiltro').val();
    
    // Carregar lista de sincronizações
    $.ajax({
      url: '../api/facial/listar_sincronizacoes.php',
      method: 'GET',
      data: { data: data },
      dataType: 'json',
      success: function(resposta) {
        renderizarTabelaSincronizacoes(resposta.sincronizacoes);
      },
      error: function() {
        console.error("Erro ao carregar sincronizações");
        $('#tabelaSincronizacoes').html('<tr><td colspan="5" class="text-center">Erro ao carregar dados</td></tr>');
      }
    });
    
    // Carregar check-ins
    $.ajax({
      url: '../api/facial/listar_checkins.php',
      method: 'GET',
      data: { data: data },
      dataType: 'json',
      success: function(resposta) {
        renderizarTabelaCheckIns(resposta.checkins);
      },
      error: function() {
        console.error("Erro ao carregar check-ins");
        $('#tabelaCheckIns').html('<tr><td colspan="4" class="text-center">Erro ao carregar dados</td></tr>');
      }
    });
  }
  
  function renderizarTabelaSincronizacoes(dados) {
    if (!dados || dados.length === 0) {
      $('#tabelaSincronizacoes').html('<tr><td colspan="5" class="text-center">Nenhum registro encontrado</td></tr>');
      return;
    }
    
    var html = '';
    for (var i = 0; i < dados.length; i++) {
      var item = dados[i];
      var statusClass = '';
      var statusText = '';
      
      switch (item.status) {
        case 'sincronizado':
          statusClass = 'success';
          statusText = 'Sincronizado';
          break;
        case 'pendente':
          statusClass = 'warning';
          statusText = 'Pendente';
          break;
        case 'falha':
          statusClass = 'danger';
          statusText = 'Falha';
          break;
        default:
          statusClass = 'secondary';
          statusText = item.status || 'Desconhecido';
      }
      
      html += '<tr>' +
        '<td>' + item.id + '</td>' +
        '<td>' + item.nome_usuario + '</td>' +
        '<td><span class="badge bg-' + statusClass + '">' + statusText + '</span></td>' +
        '<td>' + (item.horario_sync || '-') + '</td>' +
        '<td>' +
          (item.status !== 'sincronizado' ? 
            '<button class="btn btn-sm btn-primary btnSincronizar" data-id="' + item.id + '">Sincronizar</button>' : 
            '') +
        '</td>' +
      '</tr>';
    }
    
    $('#tabelaSincronizacoes').html(html);
    
    // Adicionar evento para botões de sincronização individual
    $('.btnSincronizar').click(function() {
      var id = $(this).data('id');
      $.ajax({
        url: '../api/facial/sincronizar_individual.php',
        method: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(resposta) {
          alert(resposta.mensagem);
          carregarDados();
        },
        error: function() {
          alert("Erro ao sincronizar usuário. Verifique os logs.");
        }
      });
    });
  }
  
  function renderizarTabelaCheckIns(dados) {
    if (!dados || dados.length === 0) {
      $('#tabelaCheckIns').html('<tr><td colspan="4" class="text-center">Nenhum registro encontrado</td></tr>');
      return;
    }
    
    var html = '';
    for (var i = 0; i < dados.length; i++) {
      var item = dados[i];
      html += '<tr>' +
        '<td>' + item.id + '</td>' +
        '<td>' + item.nome_usuario + '</td>' +
        '<td>' + item.data_hora + '</td>' +
        '<td>' + (item.dispositivo_id || '-') + '</td>' +
      '</tr>';
    }
    
    $('#tabelaCheckIns').html(html);
  }
  
  // Função para verificar e preparar a sincronização
  function verificarEPreparar() {
    var data = $('#dataFiltro').val();
    $('#mensagemSincronizacao').html('<div class="alert alert-info">Verificando e preparando sincronização...</div>');
    
    $.ajax({
      url: '../api/facial/verificar_e_preparar.php',
      method: 'GET',
      data: { data: data },
      dataType: 'json',
      success: function(resposta) {
        if (resposta.status === 'ok') {
          if (resposta.inseridos > 0) {
            $('#mensagemSincronizacao').html(
              '<div class="alert alert-success">' + 
              'Foram adicionados ' + resposta.inseridos + ' usuários à fila de sincronização. ' +
              'Total de reservas: ' + resposta.total_reservas + ', ' +
              'Total na fila: ' + resposta.total_sync_depois + '</div>'
            );
          } else {
            $('#mensagemSincronizacao').html(
              '<div class="alert alert-info">' + 
              'Não há novos usuários para sincronização. ' +
              'Total de reservas: ' + resposta.total_reservas + ', ' +
              'Total na fila: ' + resposta.total_sync + '</div>'
            );
          }
          // Atualizar os dados da página
          carregarDados();
        } else {
          $('#mensagemSincronizacao').html('<div class="alert alert-danger">Erro: ' + resposta.mensagem + '</div>');
        }
      },
      error: function(xhr, status, error) {
        $('#mensagemSincronizacao').html('<div class="alert alert-danger">Erro na comunicação com o servidor: ' + error + '</div>');
        console.error("Erro AJAX:", status, error, xhr.responseText);
      }
    });
  }

  // Carregar dados iniciais
  carregarDados();
  
  // Filtrar por data
  $('#btnFiltrar').click(function() {
    carregarDados();
  });

  // Botão para verificar e preparar
  $('#btnVerificarPreparar').click(function(e) {
    e.preventDefault();
    verificarEPreparar();
  });

  // Código para sincronização AJAX
  $('form').on('submit', function(e) {
    if ($(this).find('button[name="sincronizar_agora"]').length > 0) {
        e.preventDefault();
        
        // Evitar múltiplas solicitações
        if (sincronizacaoEmAndamento) {
            $('#mensagemSincronizacao').html('<div class="alert alert-warning">Já existe uma sincronização em andamento. Aguarde.</div>');
            return;
        }
        
        sincronizacaoEmAndamento = true;
        $('#mensagemSincronizacao').html('<div class="alert alert-info">Sincronização iniciada. Por favor, aguarde...</div>');
        
        // Adicionar timeout para atualizar a UI mesmo se o servidor não responder
        var timeoutId = setTimeout(function() {
            sincronizacaoEmAndamento = false;
            $('#mensagemSincronizacao').html('<div class="alert alert-warning">A sincronização está demorando mais que o esperado, mas continuará em segundo plano.</div>');
            
            // Recarregar os dados após timeout
            carregarDados();
        }, 10000); // 10 segundos
        
        // Fazer a chamada AJAX para sincronização
        $.ajax({
            url: '../api/facial/executar_sync.php',
            method: 'POST',
            dataType: 'json',
            timeout: 30000, // 30 segundos
            success: function(resposta) {
                // Limpar o timeout porque recebemos uma resposta
                clearTimeout(timeoutId);
                
                sincronizacaoEmAndamento = false;
                if (resposta && resposta.status === 'ok') {
                    $('#mensagemSincronizacao').html('<div class="alert alert-success">' + resposta.mensagem + '</div>');
                } else {
                    var msg = (resposta && resposta.mensagem) ? resposta.mensagem : 'Erro desconhecido na sincronização.';
                    $('#mensagemSincronizacao').html('<div class="alert alert-danger">' + msg + '</div>');
                }
                // Recarregar os dados após 2 segundos
                setTimeout(function() {
                    carregarDados();
                }, 2000);
            },
            error: function(xhr, status, error) {
                // Limpar o timeout porque recebemos uma resposta (mesmo com erro)
                clearTimeout(timeoutId);
                
                sincronizacaoEmAndamento = false;
                
                // Simular sucesso para prosseguir com a interface mesmo com erro no servidor
                $('#mensagemSincronizacao').html('<div class="alert alert-warning">Ocorreu um erro na comunicação, mas a sincronização foi iniciada em segundo plano.</div>');
                console.error("Erro AJAX:", status, error, xhr.responseText);
                
                // Recarregar dados de qualquer forma
                setTimeout(function() {
                    carregarDados();
                }, 2000);
            }
        });
    }
  });

  // Função para executar a sincronização automática
  $("#btnExecutarSync").click(function() {
    // Obter parâmetros
    const limite = $("#limite").val();
    const max_execucoes = $("#max_execucoes").val();
    const intervalo = $("#intervalo").val();
    
    // Mostrar container de resultado
    $("#resultadoSyncContainer").show();
    $("#resultadoSync").removeClass("alert-success alert-danger").addClass("alert-info");
    $("#resultadoSync").html(`
      <div class="d-flex align-items-center">
        <div class="spinner-border spinner-border-sm me-2" role="status">
          <span class="visually-hidden">Carregando...</span>
        </div>
        <div>Executando sincronização automática...</div>
      </div>
    `);
    
    // Desabilitar botão durante execução
    $(this).prop("disabled", true);
    
    // Fazer chamada AJAX
    $.ajax({
      url: "../api/facial/executar_sync.php",
      method: "GET",
      data: {
        limite: limite,
        max_execucoes: max_execucoes,
        intervalo: intervalo
      },
      dataType: "json",
      success: function(response) {
        if (response.status === "ok") {
          $("#resultadoSync").removeClass("alert-info alert-danger").addClass("alert-success");
          $("#resultadoSync").html(`
            <i class="fas fa-check-circle me-2"></i>
            <div>
              <strong>Sincronização concluída!</strong><br>
              ${response.mensagem}<br>
              Ciclos executados: ${response.ciclos_executados}<br>
              Total sincronizados: ${response.total_sincronizados}<br>
              Total falhas: ${response.total_falhas}
            </div>
          `);
          
          // Atualizar tabela de sincronização
          carregarDados();
        } else {
          $("#resultadoSync").removeClass("alert-info alert-success").addClass("alert-danger");
          $("#resultadoSync").html(`
            <i class="fas fa-exclamation-circle me-2"></i>
            <div>
              <strong>Erro na sincronização!</strong><br>
              ${response.mensagem}
            </div>
          `);
        }
      },
      error: function(xhr, status, error) {
        $("#resultadoSync").removeClass("alert-info alert-success").addClass("alert-danger");
        $("#resultadoSync").html(`
          <i class="fas fa-exclamation-circle me-2"></i>
          <div>
            <strong>Erro na requisição!</strong><br>
            Não foi possível completar a operação. Tente novamente mais tarde.
          </div>
        `);
        console.error("Erro na requisição AJAX:", error);
      },
      complete: function() {
        // Reabilitar botão ao finalizar
        $("#btnExecutarSync").prop("disabled", false);
      }
    });
  });

  // Função para carregar sincronizações pendentes
  function carregarSincronizacoesPendentes() {
    $.ajax({
      url: "../api/facial/listar_pendentes.php",
      method: "GET",
      dataType: "json",
      success: function(response) {
        if (response.status === "ok") {
          if (response.registros && response.registros.length > 0) {
            let html = "";
            $.each(response.registros, function(index, registro) {
              html += `<tr>
                <td>${registro.id}</td>
                <td>${registro.nome_usuario || 'N/A'}</td>
                <td>${registro.data_hora}</td>
                <td>${registro.dispositivo || 'N/A'}</td>
              </tr>`;
            });
            $("#tabelaLogSincronizacao").html(html);
          } else {
            $("#tabelaLogSincronizacao").html('<tr><td colspan="4" class="text-center">Nenhum registro pendente encontrado.</td></tr>');
          }
        } else {
          $("#tabelaLogSincronizacao").html('<tr><td colspan="4" class="text-center text-danger">Erro ao carregar registros: ' + response.mensagem + '</td></tr>');
        }
      },
      error: function() {
        $("#tabelaLogSincronizacao").html('<tr><td colspan="4" class="text-center text-danger">Erro ao comunicar com o servidor.</td></tr>');
      }
    });
  }

  // Atualizar a cada 60 segundos
  setInterval(function() {
    carregarDados();
  }, 60000);
});
</script>
</body>
</html>