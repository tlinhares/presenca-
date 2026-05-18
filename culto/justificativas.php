<?php
session_start();
require_once '../auth/verifica_sessao.php';
require_once '../auth/verifica_permissao.php';
require_once '../config/timezone.php';

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: culto_justificativas_admin (acesso_padrao=0, requer_culto=1) ║
// ║  Acesso: Grupo "Líder de Culto" ou Admin                      ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('culto_justificativas_admin');

$nome_usuario = $_SESSION['usuario_nome'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Justificativas de Culto - Sistema de Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/feedback-system.css" rel="stylesheet">
    <style>
        .justificativa-card {
            transition: all 0.3s ease;
        }
        
        .justificativa-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .status-pendente {
            border-left: 4px solid #ffc107;
        }
        
        .status-aprovada {
            border-left: 4px solid #198754;
        }
        
        .status-rejeitada {
            border-left: 4px solid #dc3545;
        }
        
        .foto-usuario {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .motivo-badge {
            font-size: 0.8em;
        }
        
        .observacoes-text {
            max-height: 100px;
            overflow-y: auto;
        }
        
        .filtro-ativo {
            background-color: #0d6efd !important;
            color: white !important;
        }
        
        /* Estilos para seleção em lote */
        .checkbox-justificativa {
            width: 1.3em;
            height: 1.3em;
            cursor: pointer;
        }
        
        .checkbox-justificativa:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        .justificativa-card:has(.checkbox-justificativa:checked) {
            background-color: #e7f1ff;
            border-color: #0d6efd !important;
        }
        
        #barra-acoes-lote {
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from {
                transform: translateX(-50%) translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
        }
        
        #selecionar-todas {
            width: 1.2em;
            height: 1.2em;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../culto/dashboard.php">
                <i class="bi bi-shield-check me-2"></i>Justificativas de Culto
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($nome_usuario); ?>
                    <span class="badge bg-warning text-dark ms-1">Admin</span>
                </span>
                <a href="../dashboard.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-house-door me-1"></i>Dashboard
                </a>
                <a href="admin.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-gear me-1"></i>Admin Culto
                </a>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-file-text me-2"></i>Justificativas de Culto
                    <small class="text-muted">Gerenciar justificativas de faltas</small>
                </h2>
            </div>
        </div>

        <!-- Filtros -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <label for="filtro-status" class="form-label">Status:</label>
                                <select class="form-select" id="filtro-status">
                                    <option value="todos">Todos</option>
                                    <option value="pendente" selected>Pendentes</option>
                                    <option value="aprovada">Aprovadas</option>
                                    <option value="rejeitada">Rejeitadas</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filtro-nome" class="form-label">Nome do Funcionário:</label>
                                <input type="text" class="form-control" id="filtro-nome" placeholder="Digite o nome...">
                            </div>
                            <div class="col-md-2">
                                <label for="filtro-data-inicio" class="form-label">Data Início:</label>
                                <input type="date" class="form-control" id="filtro-data-inicio">
                            </div>
                            <div class="col-md-2">
                                <label for="filtro-data-fim" class="form-label">Data Fim:</label>
                                <input type="date" class="form-control" id="filtro-data-fim">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button class="btn btn-primary me-2" id="btn-atualizar">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
                                </button>
                                <button class="btn btn-outline-secondary" id="btn-limpar-filtros">
                                    <i class="bi bi-x-circle me-1"></i>Limpar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <i class="bi bi-clock-history text-warning fs-1"></i>
                        <h5 class="card-title mt-2">Pendentes</h5>
                        <h3 class="text-warning" id="total-pendentes">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <i class="bi bi-check-circle text-success fs-1"></i>
                        <h5 class="card-title mt-2">Aprovadas</h5>
                        <h3 class="text-success" id="total-aprovadas">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-danger">
                    <div class="card-body">
                        <i class="bi bi-x-circle text-danger fs-1"></i>
                        <h5 class="card-title mt-2">Rejeitadas</h5>
                        <h3 class="text-danger" id="total-rejeitadas">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-info">
                    <div class="card-body">
                        <i class="bi bi-list-ul text-info fs-1"></i>
                        <h5 class="card-title mt-2">Total</h5>
                        <h3 class="text-info" id="total-geral">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Controles de Seleção -->
        <div class="row mb-3" id="controles-selecao" style="display: none;">
            <div class="col-12">
                <div class="card bg-light border-0">
                    <div class="card-body py-2">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selecionar-todas">
                                <label class="form-check-label fw-semibold" for="selecionar-todas">
                                    Selecionar todas as pendentes
                                </label>
                            </div>
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Use os checkboxes para selecionar múltiplas justificativas e decidir em lote
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Justificativas -->
        <div class="row">
            <div class="col-12">
                <div id="lista-justificativas">
                    <!-- Justificativas serão carregadas aqui -->
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Ações em Lote (Fixa no rodapé) -->
    <div id="barra-acoes-lote" class="d-none position-fixed bottom-0 start-50 translate-middle-x mb-4 bg-dark text-white rounded-pill shadow-lg px-4 py-2 align-items-center" style="z-index: 1050;">
        <span class="me-3">
            <i class="bi bi-check2-square me-1"></i>
            <strong id="contador-selecionadas">0</strong> selecionada(s)
        </span>
        <button type="button" class="btn btn-success btn-sm me-2" id="btn-aprovar-lote">
            <i class="bi bi-check-circle me-1"></i>Aprovar
        </button>
        <button type="button" class="btn btn-danger btn-sm me-2" id="btn-rejeitar-lote">
            <i class="bi bi-x-circle me-1"></i>Rejeitar
        </button>
        <button type="button" class="btn btn-outline-light btn-sm" onclick="limparSelecao()">
            <i class="bi bi-x me-1"></i>Limpar
        </button>
    </div>

    <!-- Modal para Aprovar/Rejeitar Individual -->
    <div class="modal fade" id="modalDecisao" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-gavel me-2"></i>Decisão sobre Justificativa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="detalhes-justificativa">
                        <!-- Detalhes serão carregados aqui -->
                    </div>
                    
                    <hr>
                    
                    <form id="formDecisao">
                        <input type="hidden" id="justificativa-id" name="justificativa_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Decisão:</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="decisao" id="aprovada" value="aprovada">
                                <label class="form-check-label text-success" for="aprovada">
                                    <i class="bi bi-check-circle me-1"></i>Aprovar Justificativa
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="decisao" id="rejeitada" value="rejeitada">
                                <label class="form-check-label text-danger" for="rejeitada">
                                    <i class="bi bi-x-circle me-1"></i>Rejeitar Justificativa
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="observacoes-admin" class="form-label">Observações do Administrador:</label>
                            <textarea class="form-control" id="observacoes-admin" name="observacoes_admin" rows="3" placeholder="Adicione observações sobre sua decisão..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-confirmar-decisao">
                        <i class="bi bi-check-lg me-1"></i>Confirmar Decisão
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Decisão em Lote -->
    <div class="modal fade" id="modalDecisaoLote" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-collection me-2"></i>Decisão em Lote
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="decisao-lote-tipo">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Você está prestes a <span id="decisao-lote-texto" class="fw-bold"></span> 
                        <strong><span id="quantidade-selecionadas">0</span></strong> justificativa(s).
                    </div>
                    
                    <div class="mb-3">
                        <label for="observacoes-lote" class="form-label">
                            Observações (aplicada a todas as selecionadas):
                        </label>
                        <textarea class="form-control" id="observacoes-lote" rows="3" 
                            placeholder="Adicione observações sobre esta decisão em lote..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Atenção:</strong> Esta ação não pode ser desfeita.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-confirmar-decisao-lote">
                        <i class="bi bi-check-lg me-1"></i>Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="../js/feedback-system.js"></script>
    <script src="js/justificativas-admin.js?v=<?= time() ?>"></script>
</body>
</html>
