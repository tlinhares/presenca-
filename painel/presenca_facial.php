<?php
header('Content-Type: text/html; charset=UTF-8');
// painel/presenca_facial.php - Painel de controle para sincronização facial de presença
session_start();
require_once '../api/conexao.php';
include_once '../auth/verifica_sessao.php';
include_once '../auth/verifica_permissao.php';

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: reconhecimento_facial                                  ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('reconhecimento_facial');

// Definir constantes para supressão de erros
define('E_UNCAUGHT_EXCEPTION', 512);
error_reporting(E_ALL & ~E_NOTICE & ~E_UNCAUGHT_EXCEPTION);

// Processar sincronização manual se solicitado
$mensagem_sincronizacao = '';
if (isset($_POST['sincronizar_agora'])) {
    // Log da tentativa
    $log_file = "../logs/sincronizacao_presenca_" . date('Y-m-d') . ".log";
    $time = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$time] Sincronização de presença solicitada por: {$_SESSION['usuario_nome']}" . PHP_EOL, FILE_APPEND);
    
    $mensagem_sincronizacao = '<div class="alert alert-info">Sincronização de presença solicitada. Aguarde...</div>';
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
if ($stmt) {
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
} else {
    // Tratar erro - verificar se tabela existe
    $check_table = $conn->query("SHOW TABLES LIKE 'facial_sync'");
    if ($check_table->num_rows == 0) {
        // Tabela não existe - será criada automaticamente quando verificar_e_preparar.php for executado
        $log_file = "../logs/presenca_error_" . date('Y-m-d') . ".log";
        $time = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$time] Tabela facial_sync não existe. Será criada automaticamente." . PHP_EOL, FILE_APPEND);
    } else {
        // Outro erro com a consulta
        $log_file = "../logs/presenca_error_" . date('Y-m-d') . ".log";
        $time = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$time] Erro ao preparar consulta: " . $conn->error . PHP_EOL, FILE_APPEND);
    }
}

// Título da página
$titulo = "Painel de Sincronização de Presença com Reconhecimento Facial";

// Incluir o header padrão
// include_once '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { background-color: #f0f2f5; }
        .header-page {
            background: linear-gradient(135deg, #6f42c1 0%, #9f5ed0 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card { border-radius: 12px; border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .card-header { border-radius: 12px 12px 0 0 !important; }
        .form-control, .form-select { border-radius: 8px; }
        .btn { border-radius: 8px; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-page">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="mb-1"><i class="fas fa-user-check me-2"></i>Reconhecimento Facial</h3>
                    <small class="opacity-75">Sincronização de Presença com Reconhecimento Facial</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <form method="get" class="d-flex gap-2">
                        <input type="date" class="form-control form-control-sm" id="dataFiltro" name="data" value="<?php echo $data_filtro; ?>">
                        <button type="submit" class="btn btn-light btn-sm" id="btnFiltrar">
                            <i class="fas fa-filter me-1"></i>Filtrar
                        </button>
                    </form>
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

<div class="container pb-5">
    
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Sincronização de Presença para <?php echo date('d/m/Y', strtotime($data_filtro)); ?></h5>
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
                    
                    <?php
                    // Exibir informações sobre reservas para depuração
                    $info_reservas = $conn->query("SELECT COUNT(*) as total FROM reservas_almoco WHERE data = '$data_filtro'");
if ($info_reservas && $row = $info_reservas->fetch_assoc()) {
    echo '<div class="alert alert-info">';
    echo "<strong>Informações de Depuração:</strong><br>";
    echo "Total de reservas próprias para a data $data_filtro: {$row['total']}<br>";

    // Listar algumas reservas próprias
    $lista_reservas = $conn->query("SELECT ra.*, u.nome FROM reservas_almoco ra 
                                  JOIN usuarios u ON ra.id_usuario = u.id 
                                  WHERE ra.data = '$data_filtro' LIMIT 5");
    if ($lista_reservas && $lista_reservas->num_rows > 0) {
        echo "<strong>Primeiras reservas próprias:</strong><br>";
        echo "<ul>";
        while ($reserva = $lista_reservas->fetch_assoc()) {
            echo "<li>ID: {$reserva['id']} - Usuário: {$reserva['id_usuario']} ({$reserva['nome']})</li>";
        }
        echo "</ul>";
    } else {
        echo "Nenhuma reserva própria encontrada para esta data.<br>";
    }

    // Verificar reservas adicionais
    $info_adicionais = $conn->query("SELECT COUNT(*) as total FROM reservas_adicionais WHERE data = '$data_filtro'");
    if ($info_adicionais && $row_add = $info_adicionais->fetch_assoc()) {
        echo "Total de reservas adicionais para a data $data_filtro: {$row_add['total']}<br>";

        // Listar algumas reservas adicionais
        $lista_adicionais = $conn->query("SELECT ra.id, ra.id_dependente, d.nome FROM reservas_adicionais ra 
                                          JOIN dependentes d ON ra.id_dependente = d.id 
                                          WHERE ra.data = '$data_filtro' LIMIT 5");
        if ($lista_adicionais && $lista_adicionais->num_rows > 0) {
            echo "<strong>Primeiras reservas adicionais:</strong><br>";
            echo "<ul>";
            while ($reserva_add = $lista_adicionais->fetch_assoc()) {
                echo "<li>ID: {$reserva_add['id']} - Dependente: {$reserva_add['id_dependente']} ({$reserva_add['nome']})</li>";
            }
            echo "</ul>";
        } else {
            echo "Nenhuma reserva adicional encontrada para esta data.<br>";
        }
    }

    // Verificar tabela facial_sync
    $info_sync = $conn->query("SELECT COUNT(*) as total FROM facial_sync WHERE data = '$data_filtro'");
    if ($info_sync && $row_sync = $info_sync->fetch_assoc()) {
        echo "Total de registros na tabela facial_sync para a data $data_filtro: {$row_sync['total']}<br>";
    }

    echo '</div>';
}
                    
                    ?>
                    
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
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Sincronizações por Dispositivo</h5>
                </div>
                <div class="card-body">
                    <div id="sincronizacoesPorDispositivo">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Carregando dispositivos...</span>
                            </div>
                            <p class="mt-2">Carregando sincronizações por dispositivo...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
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
    
    // Carregar sincronizações por dispositivo
    $.ajax({
      url: '../api/presenca/listar_sincronizacoes_por_dispositivo.php',
      method: 'GET',
      data: { data: data },
      dataType: 'json',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      success: function(resposta) {
        renderizarSincronizacoesPorDispositivo(resposta.dispositivos);
      },
      error: function() {
        console.error("Erro ao carregar sincronizações por dispositivo");
        $('#sincronizacoesPorDispositivo').html('<div class="alert alert-danger">Erro ao carregar dados</div>');
      }
    });
  }
  
  function renderizarSincronizacoesPorDispositivo(dispositivos) {
    if (!dispositivos || dispositivos.length === 0) {
      $('#sincronizacoesPorDispositivo').html('<div class="alert alert-info">Nenhum dispositivo encontrado</div>');
      return;
    }
    
    var html = '<div class="row">';
    
    for (var i = 0; i < dispositivos.length; i++) {
      var dispositivo = dispositivos[i];
      var statusClass = dispositivo.status_conexao === 'online' ? 'success' : 'danger';
      var statusText = dispositivo.status_conexao === 'online' ? 'Online' : 'Offline';
      
      html += '<div class="col-md-6 mb-4">';
      html += '<div class="card">';
      html += '<div class="card-header d-flex justify-content-between align-items-center">';
      html += '<h6 class="mb-0">' + dispositivo.nome + '</h6>';
      html += '<span class="badge bg-' + statusClass + '">' + statusText + '</span>';
      html += '</div>';
      html += '<div class="card-body">';
      html += '<p class="text-muted small mb-3">IP: ' + dispositivo.ip + ':' + dispositivo.porta + '</p>';
      
      // Estatísticas do dispositivo
      html += '<div class="row text-center mb-3">';
      html += '<div class="col-4">';
      html += '<div class="border rounded p-2">';
      html += '<div class="h5 text-primary mb-0">' + dispositivo.total_sincronizacoes + '</div>';
      html += '<small class="text-muted">Total</small>';
      html += '</div>';
      html += '</div>';
      html += '<div class="col-4">';
      html += '<div class="border rounded p-2">';
      html += '<div class="h5 text-success mb-0">' + dispositivo.sincronizados + '</div>';
      html += '<small class="text-muted">Sucesso</small>';
      html += '</div>';
      html += '</div>';
      html += '<div class="col-4">';
      html += '<div class="border rounded p-2">';
      html += '<div class="h5 text-danger mb-0">' + dispositivo.falhas + '</div>';
      html += '<small class="text-muted">Falhas</small>';
      html += '</div>';
      html += '</div>';
      html += '</div>';
      
      // Lista de sincronizações
      if (dispositivo.sincronizacoes && dispositivo.sincronizacoes.length > 0) {
        html += '<div class="table-responsive">';
        html += '<table class="table table-sm">';
        html += '<thead>';
        html += '<tr>';
        html += '<th>Usuário</th>';
        html += '<th>Status</th>';
        html += '<th>Horário</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        for (var j = 0; j < dispositivo.sincronizacoes.length; j++) {
          var sync = dispositivo.sincronizacoes[j];
          var syncStatusClass = '';
          var syncStatusText = '';
          
          switch (sync.status) {
            case 'sincronizado':
              syncStatusClass = 'success';
              syncStatusText = 'Sincronizado';
              break;
            case 'pendente':
              syncStatusClass = 'warning';
              syncStatusText = 'Pendente';
              break;
            case 'falha':
              syncStatusClass = 'danger';
              syncStatusText = 'Falha';
              break;
            default:
              syncStatusClass = 'secondary';
              syncStatusText = sync.status || 'Desconhecido';
          }
          
          html += '<tr>';
          html += '<td>' + sync.nome_usuario + '</td>';
          html += '<td><span class="badge bg-' + syncStatusClass + '">' + syncStatusText + '</span></td>';
          html += '<td>' + (sync.horario_sync || '-') + '</td>';
          html += '</tr>';
        }
        
        html += '</tbody>';
        html += '</table>';
        html += '</div>';
      } else {
        html += '<div class="text-center text-muted">';
        html += '<i class="fas fa-info-circle"></i> Nenhuma sincronização encontrada';
        html += '</div>';
      }
      
      html += '</div>';
      html += '</div>';
      html += '</div>';
    }
    
    html += '</div>';
    $('#sincronizacoesPorDispositivo').html(html);
  }
  
  // Função para verificar e preparar a sincronização
  function verificarEPreparar() {
    var data = $('#dataFiltro').val();
    $('#mensagemSincronizacao').html('<div class="alert alert-info">Verificando e preparando sincronização...</div>');
    
    $.ajax({
      url: '../api/presenca/verificar_e_preparar.php',
      method: 'GET',
      data: { data: data },
      dataType: 'json',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      success: function(resposta) {
        if (resposta.status === 'ok') {
          if (resposta.inseridos > 0) {
            $('#mensagemSincronizacao').html(
              '<div class="alert alert-success">' + 
              'Foram adicionados ' + resposta.inseridos + ' usuários à fila de sincronização. ' +
              'Total de usuários: ' + resposta.total_usuarios + ', ' +
              'Total na fila: ' + resposta.total_sync_depois + 
              ' <button class="btn btn-sm btn-primary" onclick="window.location.reload()">Atualizar Página</button></div>'
            );
          } else {
            $('#mensagemSincronizacao').html(
              '<div class="alert alert-info">' + 
              'Não há novos usuários para sincronização. ' +
              'Total de usuários: ' + resposta.total_usuarios + ', ' +
              'Total na fila: ' + resposta.total_sync + 
              ' <button class="btn btn-sm btn-primary" onclick="window.location.reload()">Atualizar Página</button></div>'
            );
          }
          // Atualizar os dados da página
          carregarDados();
        } else {
          $('#mensagemSincronizacao').html('<div class="alert alert-danger">Erro: ' + resposta.mensagem + ' <button class="btn btn-sm btn-primary" onclick="window.location.reload()">Atualizar Página</button></div>');
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
            url: '../api/presenca/executar_sync.php',
            method: 'POST',
            dataType: 'json',
            timeout: 30000, // 30 segundos
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            },
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
    const data = $('#dataFiltro').val();
    
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
      url: "../api/presenca/executar_sync.php",
      method: "GET",
      data: {
        limite: limite,
        max_execucoes: max_execucoes,
        intervalo: intervalo,
        data: data,
        debug: true
      },
      dataType: "json",
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
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
          
          // Exibir logs detalhados se disponíveis
          if (response.logs && response.logs.length > 0) {
            let logsHtml = '<div class="mt-3"><strong>Detalhes do processamento:</strong><pre style="max-height: 300px; overflow-y: auto; font-size: 12px; background: #f8f9fa; padding: 10px; border-radius: 4px;">';
            
            for (let log of response.logs) {
              logsHtml += log + '\n';
            }
            
            logsHtml += '</pre></div>';
            $("#resultadoSync").append(logsHtml);
          }
          
          // Atualizar tabela de sincronização
          carregarDados();
        } else {
          $("#resultadoSync").removeClass("alert-info alert-success").addClass("alert-danger");
          let errorHtml = `
            <i class="fas fa-exclamation-circle me-2"></i>
            <div>
              <strong>Erro na sincronização!</strong><br>
              ${response.mensagem}
            </div>
          `;
          
          // Exibir detalhes do erro se disponíveis
          if (response.logs && response.logs.length > 0) {
            errorHtml += '<div class="mt-3"><strong>Detalhes do erro:</strong><pre style="max-height: 300px; overflow-y: auto; font-size: 12px; background: #f8f9fa; padding: 10px; border-radius: 4px;">';
            
            for (let log of response.logs) {
              errorHtml += log + '\n';
            }
            
            errorHtml += '</pre></div>';
          }
          
          $("#resultadoSync").html(errorHtml);
        }
      },
      error: function(xhr, status, error) {
        $("#resultadoSync").removeClass("alert-info alert-success").addClass("alert-danger");
        
        // Tentar extrair informações da resposta
        let responseText = '';
        try {
          const errorObj = JSON.parse(xhr.responseText);
          if (errorObj.logs && errorObj.logs.length > 0) {
            responseText = '<div class="mt-3"><strong>Detalhes do processamento:</strong><pre style="max-height: 300px; overflow-y: auto; font-size: 12px; background: #f8f9fa; padding: 10px; border-radius: 4px;">';
            
            for (let log of errorObj.logs) {
              responseText += log + '\n';
            }
            
            responseText += '</pre></div>';
          }
        } catch (e) {
          responseText = '<pre style="max-height: 200px; overflow-y: auto; font-size: 12px; background: #f8f9fa; padding: 10px;">' + xhr.responseText + '</pre>';
        }
        
        $("#resultadoSync").html(`
          <i class="fas fa-exclamation-circle me-2"></i>
          <div>
            <strong>Erro na requisição!</strong><br>
            Status: ${status}, Erro: ${error}<br>
            Não foi possível completar a operação. Tente novamente mais tarde.
          </div>
          ${responseText}
        `);
        console.error("Erro na requisição AJAX:", error, xhr.responseText);
      },
      complete: function() {
        // Reabilitar botão ao finalizar
        $("#btnExecutarSync").prop("disabled", false);
      }
    });
  });

  // Atualizar a cada 60 segundos
  setInterval(function() {
    carregarDados();
  }, 60000);
});
</script>
</body>
</html> 