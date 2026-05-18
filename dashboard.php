<?php
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/auth/verifica_sessao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: dashboard_relatorios                                   ║
// ║  Acesso: Grupos com permissão (ex: Contabilidade) ou Admin    ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('dashboard_relatorios');
?>

<!DOCTYPE html>
<html lang="pt-br" id="htmlTheme" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Administrativo - Sistema de Presença</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* Modo Claro (padrão) */
    body {
      background-color: #f0f2f5;
      color: #212529;
      transition: background-color 0.3s ease, color 0.3s ease;
    }
    
    /* Modo Escuro */
    [data-theme="dark"] body,
    body.dark-mode {
      background-color: #1a1d29;
      color: #e9ecef;
    }
    
    [data-theme="dark"] .filter-card,
    .dark-mode .filter-card {
      background: #2d3142;
      color: #e9ecef;
      border: 1px solid #3d4154;
    }
    
    [data-theme="dark"] .chart-card,
    .dark-mode .chart-card {
      background: #2d3142;
      color: #e9ecef;
      border: 1px solid #3d4154;
    }
    
    [data-theme="dark"] .stat-card,
    .dark-mode .stat-card {
      background: #2d3142;
      color: #e9ecef;
      border: 1px solid #3d4154;
    }
    
    [data-theme="dark"] .stat-card .text-muted,
    .dark-mode .stat-card .text-muted {
      color: #adb5bd !important;
    }
    
    [data-theme="dark"] .form-control,
    [data-theme="dark"] .form-select,
    .dark-mode .form-control,
    .dark-mode .form-select {
      background-color: #1a1d29;
      color: #e9ecef;
      border-color: #3d4154;
    }
    
    [data-theme="dark"] .form-control:focus,
    [data-theme="dark"] .form-select:focus,
    .dark-mode .form-control:focus,
    .dark-mode .form-select:focus {
      background-color: #1a1d29;
      color: #e9ecef;
      border-color: #007bff;
    }
    
    [data-theme="dark"] .form-label,
    .dark-mode .form-label {
      color: #e9ecef;
    }
    
    /* Botão Toggle Tema */
    .btn-toggle-theme {
      background: rgba(255, 255, 255, 0.2);
      border: 1px solid rgba(255, 255, 255, 0.3);
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      transition: all 0.3s ease;
    }
    
    .btn-toggle-theme:hover {
      background: rgba(255, 255, 255, 0.3);
      color: white;
    }
    
    .btn-toggle-theme i {
      font-size: 1.1rem;
    }
    .header-dashboard {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: white;
      padding: 1.5rem 0;
      margin-bottom: 1.5rem;
    }
    .stat-card {
      border-radius: 12px;
      overflow: hidden;
      transition: all 0.3s ease;
      border: none;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      height: 100%;
    }
    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    .stat-card .card-body {
      padding: 1.25rem;
    }
    .stat-card.success { border-left: 4px solid #28a745; }
    .stat-card.primary { border-left: 4px solid #007bff; }
    .stat-card.warning { border-left: 4px solid #ffc107; }
    .stat-card.info { border-left: 4px solid #17a2b8; }
    .filter-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }
    .chart-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      padding: 1.5rem;
    }
    .form-control, .form-select {
      border-radius: 8px;
    }
    .btn {
      border-radius: 8px;
    }
  </style>
</head>
<body>
  <!-- Header -->
  <div class="header-dashboard">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <h3 class="mb-1"><i class="bi bi-graph-up-arrow me-2"></i>Dashboard Administrativo</h3>
          <small class="opacity-75">Relatórios e Estatísticas de Refeições</small>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <button class="btn btn-toggle-theme btn-sm" id="btnToggleTheme" title="Alternar tema claro/escuro">
            <i class="bi bi-sun-fill" id="iconTheme"></i>
          </button>
          <a href="painel/dashboard.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Voltar
          </a>
          <a href="logout.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-power me-1"></i>Sair
          </a>
        </div>
      </div>
    </div>
  </div>

<div class="container pb-5">

  <!-- Filtros -->
  <div class="filter-card">
    <div class="row align-items-end g-3">
      <div class="col-md-2">
        <label for="dataInicio" class="form-label fw-semibold">Data Início</label>
        <input type="date" class="form-control" id="dataInicio" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-md-2">
        <label for="dataFim" class="form-label fw-semibold">Data Fim</label>
        <input type="date" class="form-control" id="dataFim" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-md-3">
        <label for="tipoRelatorio" class="form-label fw-semibold">Tipo Relatório</label>
        <select class="form-select" id="tipoRelatorio">
          <option value="diario">Diário</option>
          <option value="diario_completo">Diário Completo</option>
          <option value="mensal">Entre datas</option>
        </select>
      </div>
      <div class="col-md-2">
        <label for="tipoExportacao" class="form-label fw-semibold">Formato</label>
        <select class="form-select" id="tipoExportacao">
          <option value="pdf">PDF</option>
          <option value="excel">Excel (CSV)</option>
        </select>
      </div>
      <div class="col-md-3">
        <div class="d-flex gap-2">
          <button class="btn btn-primary flex-grow-1" id="btnAtualizar">
            <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
          </button>
          <button class="btn btn-success flex-grow-1" id="btnExportar">
            <i class="bi bi-download me-1"></i>Exportar
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Cards de Estatísticas -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card stat-card success">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="text-muted mb-1">Total de Refeições</h6>
              <h3 class="mb-1 text-success" id="totalRefeicoes">-</h3>
              <small class="text-muted" id="detalhesRefeicoes">Carregando...</small>
            </div>
            <div class="bg-success bg-opacity-10 rounded-circle p-2">
              <i class="bi bi-egg-fried fs-4 text-success"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-md-3">
      <div class="card stat-card primary">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="text-muted mb-1">Valor Estimado</h6>
              <h3 class="mb-1 text-primary" id="valorEstimado">-</h3>
              <small class="text-muted" id="detalhesValor">Carregando...</small>
            </div>
            <div class="bg-primary bg-opacity-10 rounded-circle p-2">
              <i class="bi bi-currency-dollar fs-4 text-primary"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-md-3">
      <div class="card stat-card warning">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="text-muted mb-1">Departamentos</h6>
              <h3 class="mb-1 text-warning" id="totalDepartamentos">-</h3>
              <small class="text-muted">Eventos e reuniões</small>
            </div>
            <div class="bg-warning bg-opacity-10 rounded-circle p-2">
              <i class="bi bi-building fs-4 text-warning"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-md-3">
      <div class="card stat-card info">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="text-muted mb-1">Últimas Reservas</h6>
              <div id="ultimasReservas">
                <small class="text-muted">Carregando...</small>
              </div>
            </div>
            <div class="bg-info bg-opacity-10 rounded-circle p-2">
              <i class="bi bi-people fs-4 text-info"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Gráfico -->
  <div class="chart-card">
    <h5 class="mb-3"><i class="bi bi-bar-chart me-2"></i>Refeições dos Últimos 7 Dias</h5>
    <div style="height: 400px;">
      <canvas id="graficoSemana"></canvas>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/dashboard.js"></script>
<script>
  // ═══════════════════════════════════════════════════════════════════
  // GERENCIAMENTO DE TEMA (CLARO/ESCURO)
  // ═══════════════════════════════════════════════════════════════════
  
  let temaAtual = 'light';
  
  // Carregar tema salvo ao carregar a página
  $(document).ready(function() {
    carregarTema();
  });
  
  function carregarTema() {
    $.ajax({
      url: 'api/usuarios/buscar_tema.php',
      method: 'GET',
      dataType: 'json',
      success: function(response) {
        if (response.status === 'ok' && response.tema) {
          temaAtual = response.tema;
          aplicarTema(temaAtual);
        } else {
          // Se não encontrar, usar tema padrão
          aplicarTema('light');
        }
      },
      error: function() {
        // Em caso de erro, usar tema padrão
        aplicarTema('light');
      }
    });
  }
  
  function aplicarTema(tema) {
    temaAtual = tema;
    const html = document.documentElement;
    const body = document.body;
    
    if (tema === 'dark') {
      html.setAttribute('data-theme', 'dark');
      body.classList.add('dark-mode');
      $('#iconTheme').removeClass('bi-sun-fill').addClass('bi-moon-fill');
      $('#btnToggleTheme').attr('title', 'Alternar para tema claro');
    } else {
      html.setAttribute('data-theme', 'light');
      body.classList.remove('dark-mode');
      $('#iconTheme').removeClass('bi-moon-fill').addClass('bi-sun-fill');
      $('#btnToggleTheme').attr('title', 'Alternar para tema escuro');
    }
  }
  
  function salvarTema(tema) {
    $.ajax({
      url: 'api/usuarios/salvar_tema.php',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ tema: tema }),
      dataType: 'json',
      success: function(response) {
        if (response.status === 'ok') {
          console.log('Tema salvo com sucesso:', tema);
        } else {
          console.error('Erro ao salvar tema:', response.mensagem);
        }
      },
      error: function(xhr, status, error) {
        console.error('Erro ao salvar tema:', error);
      }
    });
  }
  
  // Toggle tema ao clicar no botão
  $('#btnToggleTheme').on('click', function() {
    const novoTema = temaAtual === 'light' ? 'dark' : 'light';
    aplicarTema(novoTema);
    salvarTema(novoTema);
  });
</script>
</body>
</html>