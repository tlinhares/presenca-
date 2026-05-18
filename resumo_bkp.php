<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/auth/verifica_sessao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: resumo (acesso_padrao=1, todos podem acessar)          ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('resumo');

$isAdmin = $_SESSION['usuario_categoria'] === 'admin';
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuarioId = $_SESSION['usuario_id'] ?? 0;

// Nome do mês atual
$nomesMeses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];
$mesAtual = intval(date('n'));
$anoAtual = intval(date('Y'));
$nomeMesAtual = $nomesMeses[$mesAtual];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumo - Sistema de Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <style>
        /* ═══════════════════════════════════════════════════════════════════ */
        /* LAYOUT PRINCIPAL - ESTILO INTRANET MODERNO                          */
        /* ═══════════════════════════════════════════════════════════════════ */
        body {
            background-color: #e9ecef;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 2rem 0;
            margin-bottom: 1.5rem;
        }
        
        .welcome-title {
            color: #dc3545;
            font-size: 2.2rem;
            font-weight: 300;
            font-style: italic;
            margin-bottom: 0.25rem;
        }
        
        .subtitle {
            color: #6c757d;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        /* ═══════════════════════════════════════════════════════════════════ */
        /* CARDS PRINCIPAIS                                                     */
        /* ═══════════════════════════════════════════════════════════════════ */
        .dashboard-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            height: 100%;
            overflow: hidden;
        }
        
        .card-header-custom {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header-custom h6 {
            margin: 0;
            font-weight: 600;
            font-size: 0.9rem;
            color: #495057;
        }
        
        .card-header-custom .header-icon {
            font-size: 0.9rem;
        }
        
        .card-body-custom {
            padding: 1rem;
        }
        
        /* ═══════════════════════════════════════════════════════════════════ */
        /* CALENDÁRIO COMPACTO                                                  */
        /* ═══════════════════════════════════════════════════════════════════ */
        .calendar-mini-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .calendar-mini-header span {
            font-size: 0.85rem;
            color: #495057;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.7rem;
            cursor: pointer;
            transition: transform 0.2s;
            position: relative;
        }
        
        .calendar-day:hover {
            transform: scale(1.1);
            z-index: 10;
        }
        
        .day-header {
            background-color: #6c757d;
            color: white;
            font-weight: bold;
            font-size: 0.6rem;
            cursor: default;
        }
        
        .day-header:hover {
            transform: none;
        }
        
        .day-empty {
            background-color: transparent;
            cursor: default;
        }
        
        .day-empty:hover {
            transform: none;
        }
        
        /* Cores do calendário de almoço */
        .day-reserva { background-color: #28a745; color: white; }
        .day-sem-reserva { background-color: #dc3545; color: white; }
        
        /* Cores do calendário de culto */
        .day-presente { background-color: #28a745; color: white; }
        .day-falta { background-color: #dc3545; color: white; }
        .day-atrasado { background-color: #007bff; color: white; }
        .day-justificativa-aceita { background-color: #ffc107; color: #212529; }
        .day-justificativa-pendente { background-color: #dc3545; color: white; }
        .day-justificativa-rejeitada { background-color: #dc3545; color: white; }
        .day-sem-culto { background-color: #e9ecef; color: #6c757d; cursor: default; }
        .day-nao-culto { background-color: #f8f9fa; color: #adb5bd; cursor: default; }
        
        .day-sem-culto:hover, .day-nao-culto:hover {
            transform: none;
        }
        
        /* Estilo para dias clicáveis do calendário de almoço */
        .day-reserva, .day-sem-reserva {
            cursor: pointer;
        }
        .day-reserva:hover, .day-sem-reserva:hover {
            opacity: 0.85;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .dependente-indicator {
            position: absolute;
            top: 1px;
            right: 1px;
            width: 6px;
            height: 6px;
            background-color: #007bff;
            border-radius: 50%;
        }
        
        /* ═══════════════════════════════════════════════════════════════════ */
        /* CARD DE REFEIÇÕES                                                    */
        /* ═══════════════════════════════════════════════════════════════════ */
        .refeicoes-info {
            text-align: center;
            padding: 1rem 0;
        }
        
        .refeicoes-confirmadas {
            font-size: 2.5rem;
            font-weight: 300;
            color: #495057;
            line-height: 1;
        }
        
        .refeicoes-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .refeicoes-saldo {
            font-size: 1.5rem;
            font-weight: 300;
            color: #495057;
        }
        
        .refeicoes-saldo-label {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .horario-limite-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-top: 1rem;
        }
        
        .horario-limite-ok {
            background-color: #d1ecf1;
            color: #0c5460;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .horario-limite-ok:hover {
            background-color: #bee5eb;
            color: #0c5460;
            transform: translateY(-2px);
        }
        
        .horario-limite-passado {
            background-color: #cce5ff;
            color: #004085;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .horario-limite-passado:hover {
            background-color: #b8daff;
            color: #004085;
            transform: translateY(-2px);
        }
        
        /* ═══════════════════════════════════════════════════════════════════ */
        /* CARD DE VEÍCULOS                                                     */
        /* ═══════════════════════════════════════════════════════════════════ */
        .veiculos-table {
            width: 100%;
            font-size: 0.85rem;
        }
        
        .veiculos-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            padding: 0.5rem;
            border-bottom: 2px solid #dee2e6;
            color: #495057;
        }
        
        .veiculos-table td {
            padding: 0.5rem;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .veiculo-icon {
            width: 40px;
            text-align: center;
        }
        
        .veiculo-icon i {
            font-size: 1.2rem;
            color: #6c757d;
        }
        
        .veiculo-status {
            color: #28a745;
            font-size: 0.8rem;
        }
        
        /* ═══════════════════════════════════════════════════════════════════ */
        /* GRÁFICO DE ESTATÍSTICAS                                              */
        /* ═══════════════════════════════════════════════════════════════════ */
        .chart-container {
            position: relative;
            height: 200px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.75rem;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid #e9ecef;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            font-size: 0.75rem;
            color: #495057;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
            margin-right: 4px;
        }
        
        .no-data-message {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        /* ═══════════════════════════════════════════════════════════════════ */
        /* SEÇÃO DE MÓDULOS                                                     */
        /* ═══════════════════════════════════════════════════════════════════ */
        .modulos-section {
            margin-top: 1.5rem;
        }
        
        .modulos-title {
            font-size: 1rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 1rem;
        }
        
        .modulo-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 2px solid transparent;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-decoration: none !important;
        }
        
        .modulo-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        
        .modulo-card i {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .modulo-card .modulo-nome {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }
        
        .modulo-card .modulo-desc {
            font-size: 0.75rem;
            color: #6c757d;
            margin: 0;
        }
        
        /* Cores dos módulos */
        .modulo-gerenciamento { border-color: #6c757d; }
        .modulo-gerenciamento:hover { border-color: #495057; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); }
        .modulo-gerenciamento i { color: #6c757d; }
        
        .modulo-culto { border-color: #6f42c1; }
        .modulo-culto:hover { border-color: #5a32a3; background: linear-gradient(135deg, #f8f5ff 0%, #ede5ff 100%); }
        .modulo-culto i { color: #6f42c1; }
        
        .modulo-refeicoes { border-color: #28a745; }
        .modulo-refeicoes:hover { border-color: #1e7e34; background: linear-gradient(135deg, #f0fff4 0%, #d4edda 100%); }
        .modulo-refeicoes i { color: #28a745; }
        
        .modulo-frota { border-color: #17a2b8; }
        .modulo-frota:hover { border-color: #138496; background: linear-gradient(135deg, #e8f7fa 0%, #d1ecf1 100%); }
        .modulo-frota i { color: #17a2b8; }
        
        .modulo-estoque { border-color: #fd7e14; }
        .modulo-estoque:hover { border-color: #e96b02; background: linear-gradient(135deg, #fff5eb 0%, #ffe5cc 100%); }
        .modulo-estoque i { color: #fd7e14; }
        
        /* ═══════════════════════════════════════════════════════════════════ */
        /* RESPONSIVIDADE                                                       */
        /* ═══════════════════════════════════════════════════════════════════ */
        @media (max-width: 991px) {
            .welcome-title {
                font-size: 1.8rem;
            }
            
            .dashboard-card {
                margin-bottom: 1rem;
            }
        }
        
        @media (max-width: 768px) {
            .welcome-title {
                font-size: 1.5rem;
            }
            
            .calendar-day {
                font-size: 0.6rem;
            }
            
            .day-header {
                font-size: 0.5rem;
            }
            
            .refeicoes-confirmadas {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header Principal -->
    <div class="main-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="welcome-title">Olá <?= htmlspecialchars($nomeUsuario) ?>, Bem Vindo</h1>
                    <span class="subtitle">INTRANET - Módulos de Gestão</span>
                </div>
                <div>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-power me-1"></i>Sair
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Linha Principal: Calendário Culto + Estatísticas + Refeições -->
        <div class="row g-3">
            <!-- Card: Presença | Cultos -->
            <div class="col-lg-4 col-md-6">
                <div class="dashboard-card">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-people-fill text-primary me-2"></i>Presença | Cultos - <?= $nomeMesAtual ?></h6>
                        <a href="<?= MenuPermissaoService::ajustarUrl('/culto/dashboard.php') ?>" class="text-primary header-icon" title="Ver mais">
                            <i class="bi bi-search"></i>
                        </a>
                    </div>
                    <div class="card-body-custom">
                        <div class="calendar-mini-header">
                            <button class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="mudarMesCulto(-1)">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <span id="mesAnoCulto"><?= $nomeMesAtual ?></span>
                            <button class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="mudarMesCulto(1)">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <div id="calendario-culto" class="calendar-grid">
                            <!-- Calendário será renderizado aqui -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: Estatística | Presença -->
            <div class="col-lg-4 col-md-6">
                <div class="dashboard-card">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-bar-chart-fill text-success me-2"></i>Estatística | Presença</h6>
                    </div>
                    <div class="card-body-custom">
                        <div class="chart-container">
                            <canvas id="graficoPresenca"></canvas>
                        </div>
                        <div class="chart-legend" id="legendaGrafico">
                            <!-- Legenda será gerada dinamicamente -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: Refeições -->
            <div class="col-lg-4 col-md-12">
                <div class="dashboard-card">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-egg-fried text-warning me-2"></i>Refeições <?= $nomeMesAtual ?> <?= $anoAtual ?></h6>
                        <a href="<?= MenuPermissaoService::ajustarUrl('/reservas/almoco.php') ?>" class="text-warning header-icon" title="Reservar">
                            <i class="bi bi-plus-circle"></i>
                        </a>
                    </div>
                    <div class="card-body-custom">
                        <div class="refeicoes-info">
                            <div class="refeicoes-label">Confirmadas:</div>
                            <div class="refeicoes-confirmadas" id="totalConfirmadas">-</div>
                            <div class="refeicoes-saldo-label mt-3">Saldo Atual:</div>
                            <div class="refeicoes-saldo" id="saldoAtual">R$ 0,00</div>
                            <div id="statusHorario">
                                <!-- Status do horário será renderizado aqui -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Linha: Calendário Almoço + Veículos -->
        <div class="row g-3 mt-2">
            <!-- Card: Reservas de Almoço -->
            <div class="col-lg-4 col-md-6">
                <div class="dashboard-card">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-calendar-check text-success me-2"></i>Reservas | Almoço - <?= $nomeMesAtual ?></h6>
                        <a href="<?= MenuPermissaoService::ajustarUrl('/reservas/almoco.php') ?>" class="text-success header-icon" title="Ver mais">
                            <i class="bi bi-search"></i>
                        </a>
                    </div>
                    <div class="card-body-custom">
                        <div class="calendar-mini-header">
                            <button class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="mudarMesAlmoco(-1)">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <span id="mesAnoAlmoco"><?= $nomeMesAtual ?></span>
                            <button class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="mudarMesAlmoco(1)">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <div id="calendario-almoco" class="calendar-grid">
                            <!-- Calendário será renderizado aqui -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: Veículos (preparado para módulo de frota) -->
            <div class="col-lg-8 col-md-6">
                <div class="dashboard-card">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-truck text-info me-2"></i>Veículos</h6>
                    </div>
                    <div class="card-body-custom">
                        <div id="veiculosContainer">
                            <table class="veiculos-table">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Veículo</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="listaVeiculos">
                                    <!-- Veículos serão carregados via AJAX -->
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">
                                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                            Carregando...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção de Módulos do Sistema -->
        <?php
        $temGerenciamento = MenuPermissaoService::podeAcessar('painel_dashboard');
        $temCulto = MenuPermissaoService::podeAcessar('culto_dashboard');
        $temRefeicoes = MenuPermissaoService::podeAcessar('refeicoes_reserva');
        $temFrota = MenuPermissaoService::podeAcessar('frota_dashboard');
        $temEstoque = MenuPermissaoService::podeAcessar('estoque_dashboard');
        $totalModulos = ($temGerenciamento ? 1 : 0) + ($temCulto ? 1 : 0) + ($temRefeicoes ? 1 : 0) + ($temFrota ? 1 : 0) + ($temEstoque ? 1 : 0);
        
        if ($totalModulos > 0):
        ?>
        <div class="modulos-section">
            <h5 class="modulos-title">
                <i class="bi bi-grid-3x3-gap-fill me-2"></i>Módulos do Sistema
            </h5>
            <div class="row g-3">
                <?php if ($temGerenciamento): ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <a href="<?= MenuPermissaoService::ajustarUrl('/painel/dashboard.php') ?>" class="modulo-card modulo-gerenciamento">
                        <i class="bi bi-gear-wide-connected"></i>
                        <div class="modulo-nome">Gerenciamento</div>
                        <p class="modulo-desc">Painel administrativo</p>
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if ($temCulto): ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <a href="<?= MenuPermissaoService::ajustarUrl('/culto/dashboard.php') ?>" class="modulo-card modulo-culto">
                        <i class="bi bi-people-fill"></i>
                        <div class="modulo-nome">Culto</div>
                        <p class="modulo-desc">Presenças</p>
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if ($temRefeicoes): ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <a href="<?= MenuPermissaoService::ajustarUrl('/reservas/almoco.php') ?>" class="modulo-card modulo-refeicoes">
                        <i class="bi bi-egg-fried"></i>
                        <div class="modulo-nome">Refeições</div>
                        <p class="modulo-desc">Reservas</p>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Módulo Frota -->
                <?php if ($temFrota): ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="modulo-card modulo-frota">
                        <i class="bi bi-truck"></i>
                        <div class="modulo-nome">Frota</div>
                        <p class="modulo-desc">Veículos</p>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Módulo Estoque -->
                <?php if ($temEstoque): ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <a href="<?= MenuPermissaoService::ajustarUrl('/estoque/dashboard.php') ?>" class="modulo-card modulo-estoque">
                        <i class="bi bi-boxes"></i>
                        <div class="modulo-nome">Estoque</div>
                        <p class="modulo-desc">Materiais</p>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const usuarioId = <?= $usuarioId ?>;
        const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
        
        // Controle de meses
        let mesAtualAlmoco = <?= $mesAtual - 1 ?>; // JavaScript usa 0-11
        let anoAtualAlmoco = <?= $anoAtual ?>;
        let mesAtualCulto = <?= $mesAtual - 1 ?>;
        let anoAtualCulto = <?= $anoAtual ?>;
        
        const nomesMeses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                           'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        
        // Gráfico de presença
        let graficoPresenca = null;
        
        // ═══════════════════════════════════════════════════════════════════
        // CARREGAR DADOS INICIAIS
        // ═══════════════════════════════════════════════════════════════════
        $(document).ready(function() {
            carregarCalendarioAlmoco();
            carregarCalendarioCulto();
            carregarEstatisticasPresenca();
            carregarResumoRefeicoes();
            carregarVeiculosFrota();
        });
        
        // ═══════════════════════════════════════════════════════════════════
        // CALENDÁRIO DE ALMOÇO
        // ═══════════════════════════════════════════════════════════════════
        function carregarCalendarioAlmoco() {
            $.ajax({
                url: 'api/calendario/dados_almoco.php',
                method: 'GET',
                data: { 
                    usuario_id: usuarioId,
                    mes: mesAtualAlmoco + 1,
                    ano: anoAtualAlmoco
                },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        renderizarCalendarioAlmoco(data.dados);
                        $('#mesAnoAlmoco').text(nomesMeses[mesAtualAlmoco]);
                    }
                }
            });
        }
        
        function renderizarCalendarioAlmoco(dados) {
            const container = document.getElementById('calendario-almoco');
            let html = '';
            
            // Cabeçalhos
            const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
            diasSemana.forEach(dia => {
                html += `<div class="calendar-day day-header">${dia}</div>`;
            });
            
            // Dias do mês
            const primeiroDia = new Date(anoAtualAlmoco, mesAtualAlmoco, 1);
            const ultimoDia = new Date(anoAtualAlmoco, mesAtualAlmoco + 1, 0);
            const diasNoMes = ultimoDia.getDate();
            const diaSemanaInicio = primeiroDia.getDay();
            
            // Dias vazios
            for (let i = 0; i < diaSemanaInicio; i++) {
                html += '<div class="calendar-day day-empty"></div>';
            }
            
            // Dias do mês
            for (let dia = 1; dia <= diasNoMes; dia++) {
                const dataCompleta = `${anoAtualAlmoco}-${String(mesAtualAlmoco + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
                const dataFormatada = `${String(dia).padStart(2, '0')}/${String(mesAtualAlmoco + 1).padStart(2, '0')}/${anoAtualAlmoco}`;
                const dadosDia = dados[dataCompleta] || {};
                
                let classes = 'calendar-day';
                let indicador = '';
                
                if (dadosDia.tem_reserva) {
                    classes += ' day-reserva';
                } else {
                    classes += ' day-sem-reserva';
                }
                
                if (dadosDia.tem_dependente) {
                    indicador = '<div class="dependente-indicator"></div>';
                }
                
                html += `<div class="${classes}" title="${dataFormatada}" data-data="${dataCompleta}" onclick="abrirDetalhesReservas('${dataCompleta}')">${dia}${indicador}</div>`;
            }
            
            container.innerHTML = html;
        }
        
        // ═══════════════════════════════════════════════════════════════════
        // DETALHES DAS RESERVAS DO DIA
        // ═══════════════════════════════════════════════════════════════════
        function abrirDetalhesReservas(data) {
            // Mostrar modal com loading
            const modal = new bootstrap.Modal(document.getElementById('modalDetalhesReservas'));
            modal.show();
            
            $('#modalDetalhesReservasBody').html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="mt-2 text-muted">Carregando reservas...</p>
                </div>
            `);
            
            // Buscar detalhes via API
            $.ajax({
                url: 'api/calendario/detalhes_reservas_dia.php',
                method: 'GET',
                data: { data: data },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        renderizarDetalhesReservas(response);
                    } else {
                        $('#modalDetalhesReservasBody').html(`
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                ${response.mensagem || 'Erro ao carregar detalhes'}
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#modalDetalhesReservasBody').html(`
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Erro de conexão ao buscar detalhes
                        </div>
                    `);
                }
            });
        }
        
        function renderizarDetalhesReservas(data) {
            let html = '';
            
            // Cabeçalho com data
            html += `
                <div class="text-center mb-4">
                    <h5 class="mb-1">${data.dia_semana}</h5>
                    <span class="badge bg-success fs-6">${data.data_formatada}</span>
                </div>
            `;
            
            if (!data.tem_reservas) {
                html += `
                    <div class="alert alert-warning text-center">
                        <i class="bi bi-calendar-x me-2"></i>
                        Nenhuma reserva para este dia
                    </div>
                `;
            } else {
                // Reserva Própria
                if (data.reserva_propria) {
                    html += `
                        <div class="card mb-3 border-success">
                            <div class="card-header bg-success text-white py-2">
                                <i class="bi bi-person-check me-2"></i>Reserva Própria
                            </div>
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">${data.reserva_propria.nome}</span>
                                    <span class="badge bg-success">Confirmada</span>
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                // Reservas Adicionais (Dependentes)
                if (data.reservas_adicionais && data.reservas_adicionais.length > 0) {
                    html += `
                        <div class="card border-info">
                            <div class="card-header bg-info text-white py-2">
                                <i class="bi bi-people me-2"></i>Reservas Adicionais (${data.total_adicionais})
                            </div>
                            <ul class="list-group list-group-flush">
                    `;
                    
                    data.reservas_adicionais.forEach(function(reserva) {
                        const valorClass = reserva.valor > 0 ? 'text-danger' : 'text-success';
                        html += `
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <span>
                                    <i class="bi bi-person me-2 text-info"></i>
                                    ${reserva.nome}
                                </span>
                                <span class="fw-bold ${valorClass}">${reserva.valor_formatado}</span>
                            </li>
                        `;
                    });
                    
                    html += `
                            </ul>
                        </div>
                    `;
                }
            }
            
            $('#modalDetalhesReservasBody').html(html);
        }
        
        function mudarMesAlmoco(direcao) {
            mesAtualAlmoco += direcao;
            if (mesAtualAlmoco < 0) {
                mesAtualAlmoco = 11;
                anoAtualAlmoco--;
            } else if (mesAtualAlmoco > 11) {
                mesAtualAlmoco = 0;
                anoAtualAlmoco++;
            }
            carregarCalendarioAlmoco();
        }
        
        // ═══════════════════════════════════════════════════════════════════
        // CALENDÁRIO DE CULTO
        // ═══════════════════════════════════════════════════════════════════
        function carregarCalendarioCulto() {
            $.ajax({
                url: 'api/calendario/dados_culto.php',
                method: 'GET',
                data: { 
                    usuario_id: usuarioId,
                    mes: mesAtualCulto + 1,
                    ano: anoAtualCulto
                },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        renderizarCalendarioCulto(data.dados);
                        $('#mesAnoCulto').text(nomesMeses[mesAtualCulto]);
                    }
                }
            });
        }
        
        function renderizarCalendarioCulto(dados) {
            const container = document.getElementById('calendario-culto');
            let html = '';
            
            // Cabeçalhos
            const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
            diasSemana.forEach(dia => {
                html += `<div class="calendar-day day-header">${dia}</div>`;
            });
            
            // Dias do mês
            const primeiroDia = new Date(anoAtualCulto, mesAtualCulto, 1);
            const ultimoDia = new Date(anoAtualCulto, mesAtualCulto + 1, 0);
            const diasNoMes = ultimoDia.getDate();
            const diaSemanaInicio = primeiroDia.getDay();
            
            // Dias vazios
            for (let i = 0; i < diaSemanaInicio; i++) {
                html += '<div class="calendar-day day-empty"></div>';
            }
            
            // Dias do mês
            for (let dia = 1; dia <= diasNoMes; dia++) {
                const dataCompleta = `${anoAtualCulto}-${String(mesAtualCulto + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
                const dataFormatada = `${String(dia).padStart(2, '0')}/${String(mesAtualCulto + 1).padStart(2, '0')}/${anoAtualCulto}`;
                const dadosDia = dados[dataCompleta] || {};
                
                let classes = 'calendar-day';
                
                switch(dadosDia.status) {
                    case 'presente':
                        classes += ' day-presente';
                        break;
                    case 'atrasado':
                        classes += ' day-atrasado';
                        break;
                    case 'falta':
                        classes += ' day-falta';
                        break;
                    case 'justificativa_aceita':
                        classes += ' day-justificativa-aceita';
                        break;
                    case 'justificativa_pendente':
                        classes += ' day-justificativa-pendente';
                        break;
                    case 'justificativa_rejeitada':
                        classes += ' day-justificativa-rejeitada';
                        break;
                    case 'sem_culto':
                        classes += ' day-sem-culto';
                        break;
                    case 'nao_culto':
                        classes += ' day-nao-culto';
                        break;
                    default:
                        classes += ' day-empty';
                }
                
                html += `<div class="${classes}" title="${dataFormatada}">${dia}</div>`;
            }
            
            container.innerHTML = html;
        }
        
        function mudarMesCulto(direcao) {
            mesAtualCulto += direcao;
            if (mesAtualCulto < 0) {
                mesAtualCulto = 11;
                anoAtualCulto--;
            } else if (mesAtualCulto > 11) {
                mesAtualCulto = 0;
                anoAtualCulto++;
            }
            carregarCalendarioCulto();
            carregarEstatisticasPresenca();
        }
        
        // ═══════════════════════════════════════════════════════════════════
        // ESTATÍSTICAS DE PRESENÇA (GRÁFICO)
        // ═══════════════════════════════════════════════════════════════════
        function carregarEstatisticasPresenca() {
            $.ajax({
                url: 'api/calendario/estatisticas_presenca.php',
                method: 'GET',
                data: { 
                    mes: mesAtualCulto + 1,
                    ano: anoAtualCulto
                },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        renderizarGraficoPresenca(data.estatisticas);
                    }
                }
            });
        }
        
        function renderizarGraficoPresenca(estatisticas) {
            const ctx = document.getElementById('graficoPresenca').getContext('2d');
            
            // Destruir gráfico anterior se existir
            if (graficoPresenca) {
                graficoPresenca.destroy();
            }
            
            // Se não há dados
            if (estatisticas.total_dias_culto === 0) {
                document.getElementById('graficoPresenca').parentElement.innerHTML = `
                    <div class="no-data-message">
                        <i class="bi bi-bar-chart-line display-4 text-muted"></i>
                        <p class="mt-2">Sem dados de presença neste mês</p>
                    </div>
                `;
                document.getElementById('legendaGrafico').innerHTML = '';
                return;
            }
            
            const dados = [
                estatisticas.percentual_presentes,
                estatisticas.percentual_atrasados,
                estatisticas.percentual_faltas,
                estatisticas.percentual_justificativas
            ];
            
            const cores = ['#28a745', '#007bff', '#dc3545', '#ffc107'];
            const labels = ['Presente', 'Atraso', 'Falta', 'Just.'];
            
            graficoPresenca = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: dados,
                        backgroundColor: cores,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.raw + '%';
                                }
                            }
                        }
                    },
                    cutout: '50%'
                }
            });
            
            // Renderizar legenda
            let legendaHtml = '';
            for (let i = 0; i < labels.length; i++) {
                legendaHtml += `
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: ${cores[i]}"></div>
                        ${dados[i]}% ${labels[i]}
                    </div>
                `;
            }
            document.getElementById('legendaGrafico').innerHTML = legendaHtml;
        }
        
        // ═══════════════════════════════════════════════════════════════════
        // RESUMO DE REFEIÇÕES
        // ═══════════════════════════════════════════════════════════════════
        function carregarResumoRefeicoes() {
            $.ajax({
                url: 'api/calendario/resumo_refeicoes.php',
                method: 'GET',
                cache: false, // Desabilitar cache
                data: { 
                    mes: <?= $mesAtual ?>,
                    ano: <?= $anoAtual ?>,
                    _t: Date.now() // Cache buster
                },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        $('#totalConfirmadas').text(data.resumo.total_confirmadas);
                        $('#saldoAtual').text(data.resumo.valor_formatado);
                        
                        // Status do horário - Clicável para ir à tela de reservas
                        const urlReservas = '<?= MenuPermissaoService::ajustarUrl("/reservas/almoco.php") ?>';
                        let statusHtml = '';
                        
                        // Lógica:
                        // - Se permitir_reserva_atraso = 1 → "Ir para a tela de Reservas"
                        // - Se permitir_reserva_atraso = 0 e fora do horário → "Horário Limite Atingido"
                        // - Se permitir_reserva_atraso = 0 e dentro do horário → "Fazer Reserva"
                        
                        if (data.resumo.permitir_reserva_atraso == 1) {
                            // Permite reserva a qualquer momento
                            statusHtml = `
                                <a href="${urlReservas}" class="horario-limite-badge horario-limite-ok">
                                    <i class="bi bi-arrow-right-circle me-1"></i>
                                    Ir para a tela de Reservas
                                </a>
                            `;
                        } else if (data.resumo.horario_limite_passado) {
                            // Não permite e passou do horário
                            statusHtml = `
                                <a href="${urlReservas}" class="horario-limite-badge horario-limite-passado">
                                    <i class="bi bi-clock-history me-1"></i>
                                    Horário Limite Atingido
                                </a>
                            `;
                        } else {
                            // Não permite mas está dentro do horário
                            statusHtml = `
                                <a href="${urlReservas}" class="horario-limite-badge horario-limite-ok">
                                    <i class="bi bi-calendar-plus me-1"></i>
                                    Fazer Reserva
                                </a>
                            `;
                        }
                        $('#statusHorario').html(statusHtml);
                    }
                }
            });
        }
        
        // ═══════════════════════════════════════════════════════════════════
        // VEÍCULOS DA FROTA
        // ═══════════════════════════════════════════════════════════════════
        function carregarVeiculosFrota() {
            $.ajax({
                url: 'api/frota/listar_veiculos.php',
                method: 'GET',
                data: { status: '' }, // Todos os veículos
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        renderizarVeiculos(data.veiculos.slice(0, 5)); // Mostrar só 5
                    }
                },
                error: function() {
                    $('#listaVeiculos').html('<tr><td colspan="3" class="text-center text-muted py-3"><i class="bi bi-exclamation-circle me-2"></i>Não foi possível carregar</td></tr>');
                }
            });
        }
        
        function renderizarVeiculos(veiculos) {
            const tbody = $('#listaVeiculos');
            
            if (veiculos.length === 0) {
                tbody.html('<tr><td colspan="3" class="text-center text-muted py-3"><i class="bi bi-truck me-2"></i>Nenhum veículo cadastrado</td></tr>');
                return;
            }
            
            let html = '';
            veiculos.forEach(v => {
                const statusBadge = {
                    'disponivel': '<span class="badge bg-success">Disponível</span>',
                    'em_uso': '<span class="badge bg-warning text-dark">Em Uso</span>',
                    'manutencao': '<span class="badge bg-danger">Manutenção</span>',
                    'inativo': '<span class="badge bg-secondary">Inativo</span>'
                }[v.status] || '<span class="badge bg-secondary">-</span>';
                
                html += `
                    <tr>
                        <td><strong>${v.placa}</strong></td>
                        <td>${v.modelo}</td>
                        <td>${statusBadge}</td>
                    </tr>
                `;
            });
            
            tbody.html(html);
        }
    </script>
    
    <!-- Modal de Detalhes das Reservas do Dia -->
    <div class="modal fade" id="modalDetalhesReservas" tabindex="-1" aria-labelledby="modalDetalhesReservasLabel">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalDetalhesReservasLabel">
                        <i class="bi bi-calendar-check me-2"></i>Reservas do Dia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" id="modalDetalhesReservasBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="<?= MenuPermissaoService::ajustarUrl('/reservas/almoco.php') ?>" class="btn btn-success">
                        <i class="bi bi-plus-circle me-1"></i>Ir para Reservas
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Primeira Configuração de Notificações -->
    <div class="modal fade" id="modalPrimeiraConfiguracaoNotificacoes" tabindex="-1" aria-labelledby="modalPrimeiraConfiguracaoNotificacoesLabel" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalPrimeiraConfiguracaoNotificacoesLabel">
                        <i class="bi bi-bell-fill me-2"></i>Configure suas Notificações
                    </h5>
                </div>
                <div class="modal-body">
                    <p class="mb-4">Escolha os tipos de notificações que você deseja receber quando fizer reservas:</p>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="notif_propria_inicial" checked>
                        <label class="form-check-label" for="notif_propria_inicial">
                            <strong>📧 Notificar reserva própria</strong>
                            <br><small class="text-muted">Receba notificação quando fizer uma reserva de almoço para você mesmo</small>
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="notif_adicional_inicial" checked>
                        <label class="form-check-label" for="notif_adicional_inicial">
                            <strong>👤 Notificar reserva adicional</strong>
                            <br><small class="text-muted">Receba notificação quando fizer uma reserva para um dependente</small>
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="notif_multipla_inicial" checked>
                        <label class="form-check-label" for="notif_multipla_inicial">
                            <strong>📅 Notificar reservas múltiplas</strong>
                            <br><small class="text-muted">Receba notificação quando fizer reservas para vários dias</small>
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="notif_cancelada_inicial" checked>
                        <label class="form-check-label" for="notif_cancelada_inicial">
                            <strong>❌ Notificar cancelamento de reserva</strong>
                            <br><small class="text-muted">Receba notificação quando cancelar uma reserva</small>
                        </label>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Como funciona:</strong> Se você tiver telefone cadastrado, receberá por WhatsApp. Caso contrário, receberá por email.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" onclick="salvarConfiguracaoInicial()">
                        <i class="bi bi-check-circle me-2"></i>Salvar e Continuar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Verificar configuração de notificações
        function verificarConfiguracaoNotificacoes() {
            $.ajax({
                url: 'api/notificacao/buscar_configuracao.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok' && !response.configurado) {
                        $('#modalPrimeiraConfiguracaoNotificacoes').modal('show');
                    }
                }
            });
        }
        
        // Salvar configuração inicial
        function salvarConfiguracaoInicial() {
            $.ajax({
                url: 'api/notificacao/salvar_configuracao.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    notificar_reserva_propria: $('#notif_propria_inicial').is(':checked') ? 1 : 0,
                    notificar_reserva_adicional: $('#notif_adicional_inicial').is(':checked') ? 1 : 0,
                    notificar_reserva_multipla: $('#notif_multipla_inicial').is(':checked') ? 1 : 0,
                    notificar_reserva_cancelada: $('#notif_cancelada_inicial').is(':checked') ? 1 : 0
                }),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        $('#modalPrimeiraConfiguracaoNotificacoes').modal('hide');
                    }
                }
            });
        }
        
        // Verificar notificações após 1 segundo
        setTimeout(verificarConfiguracaoNotificacoes, 1000);
    </script>
</body>
</html>
