<?php
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');
include_once(__DIR__ . '/../utils/acesso_especial.php');
include_once(__DIR__ . '/../auth/verifica_permissao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: culto_relatorios (acesso_padrao=0, requer_culto=1)     ║
// ║  Acesso: Grupo "Líder de Culto" ou Admin                      ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('culto_relatorios');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios de Culto - Sistema de Presença</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/feedback-system.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .report-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        .report-card.active {
            border: 3px solid #0d6efd;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .report-card.active .report-icon {
            color: white;
        }
        .report-icon {
            font-size: 3rem;
            color: #0d6efd;
        }
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table-responsive {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 40px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../culto/dashboard.php">
                <i class="bi bi-house-door me-2"></i>Sistema de Presença
            </a>
            <div class="navbar-nav ms-auto">
                <a href="../culto/dashboard.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-arrow-left me-1"></i>Voltar
                </a>
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário') ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="bi bi-file-earmark-text me-2"></i>Relatórios de Culto</h2>
                <p class="text-muted">Gerencie e exporte relatórios profissionais de presença de culto</p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-card">
            <h5 class="mb-3"><i class="bi bi-funnel me-2"></i>Configurações do Relatório</h5>
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Tipo de Relatório:</label>
                    <select class="form-select" id="tipo_relatorio">
                        <option value="presencas">Presenças</option>
                        <option value="faltas">Faltas</option>
                        <option value="justificativas">Justificativas</option>
                        <option value="estatisticas">Estatísticas</option>
                        <option value="usuario">Por Usuário</option>
                        <option value="frequencia">Frequência</option>
                        <option value="atrasos">Atrasos</option>
                        <option value="comparativo">Comparativo</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Início:</label>
                    <input type="date" class="form-control" id="data_inicio" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Fim:</label>
                    <input type="date" class="form-control" id="data_fim" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Usuário:</label>
                    <select class="form-select" id="filtro_usuario" style="width: 100%;">
                        <option value="">Todos os usuários</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="btn-group-vertical w-100" role="group">
                        <button type="button" class="btn btn-danger mb-2" onclick="gerarRelatorio('pdf')">
                            <i class="bi bi-file-pdf me-2"></i>Gerar PDF
                        </button>
                        <button type="button" class="btn btn-success mb-2" onclick="gerarRelatorio('excel')">
                            <i class="bi bi-file-excel me-2"></i>Gerar Excel
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="gerarRelatorio('csv')">
                            <i class="bi bi-filetype-csv me-2"></i>Gerar CSV
                        </button>
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="definirPeriodo('hoje')">Hoje</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="definirPeriodo('semana')">Esta Semana</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="definirPeriodo('mes')">Este Mês</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="definirPeriodo('ano')">Este Ano</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../js/feedback-system.js"></script>
    <script src="../js/relatorios_cultos.js?v=<?= time() ?>"></script>
</body>
</html>
