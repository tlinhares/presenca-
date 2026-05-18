<?php
session_start();
require_once '../auth/verifica_sessao.php';
require_once '../auth/verifica_permissao.php';
require_once '../config/timezone.php';

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: culto_presencas (acesso_padrao=0, requer_culto=1)      ║
// ║  Acesso: Grupo "Líder de Culto" ou Admin                      ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('culto_presencas');

$isAdmin = true;
$data_hoje = date('Y-m-d');
$data_formatada = date('d/m/Y');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Presenças de Culto - <?= $data_formatada ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/feedback-system.css" rel="stylesheet">
    <style>
        .usuario-item {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .usuario-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .status-sem-presenca {
            background-color: #f8f9fa;
            border-color: #dee2e6;
        }
        
        .status-presente {
            background-color: #d1edff;
            border-color: #28a745;
            color: #155724;
        }
        
        .status-atrasado {
            background-color: #fff3cd;
            border-color: #007bff;
            color: #004085;
        }
        
        .status-falta {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .foto-usuario {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dee2e6;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stats-card .card-body {
            padding: 1.5rem;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .data-futura .usuario-item {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .data-futura .usuario-item:hover {
            transform: none;
            box-shadow: none;
        }
        
        .justificativa-bloqueada {
            opacity: 0.7;
            background-color: #f8f9fa !important;
            border: 2px dashed #6c757d !important;
        }
        
        .justificativa-bloqueada:hover {
            transform: none !important;
            box-shadow: none !important;
        }
        
        .cursor-not-allowed {
            cursor: not-allowed !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../culto/dashboard.php">
                <i class="bi bi-house-door me-2"></i>Sistema de Presença
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['usuario_nome']) ?>
                    <span class="badge bg-warning text-dark ms-1">Admin</span>
                </span>
                <a href="configuracoes.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-gear me-1"></i>Configurações
                </a>
                <a href="justificativas.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-file-text me-1"></i>Justificativas
                </a>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">
                            <i class="bi bi-people-fill me-2"></i>Gerenciar Presenças de Culto
                        </h2>
                        <p class="text-muted mb-0">
                            <span id="data-exibida"><?= $data_formatada ?></span>
                            <span id="indicador-data" class="badge bg-primary ms-2">Hoje</span>
                            - Clique no nome para alterar presença
                        </p>
                    </div>
                    <div>
                        <a href="../culto/dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Voltar
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-people-fill fs-1 mb-2"></i>
                        <div class="stats-number" id="total-usuarios">0</div>
                        <div>Total de Usuários</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle-fill fs-1 mb-2"></i>
                        <div class="stats-number" id="total-presentes">0</div>
                        <div>Presentes</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-clock-fill fs-1 mb-2"></i>
                        <div class="stats-number" id="total-atrasados">0</div>
                        <div>Atrasados</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-x-circle-fill fs-1 mb-2"></i>
                        <div class="stats-number" id="total-faltas">0</div>
                        <div>Faltas</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <label for="seletor-data" class="form-label">Data do Culto:</label>
                                <input type="date" class="form-control" id="seletor-data" value="<?= $data_hoje ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="filtro-status" class="form-label">Filtrar por Status:</label>
                                <select class="form-select" id="filtro-status">
                                    <option value="todos">Todos</option>
                                    <option value="sem-presenca">Sem Presença</option>
                                    <option value="presente">Presentes</option>
                                    <option value="atrasado">Atrasados</option>
                                    <option value="falta">Faltas</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="busca-usuario" class="form-label">Buscar Usuário:</label>
                                <input type="text" class="form-control" id="busca-usuario" placeholder="Digite o nome...">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button class="btn btn-primary me-2" id="btn-atualizar">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
                                </button>
                                <button class="btn btn-outline-secondary" id="btn-hoje">
                                    <i class="bi bi-calendar-day me-1"></i>Hoje
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Usuários -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-list-ul me-2"></i>Lista de Usuários
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="lista-usuarios" class="row">
                            <!-- Usuários serão carregados aqui -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="../js/feedback-system.js"></script>
    <script src="../js/culto-admin.js?v=<?= time() ?>"></script>
</body>
</html>
