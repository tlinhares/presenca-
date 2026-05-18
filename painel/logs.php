<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}
include_once(__DIR__ . '/../auth/verifica_permissao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: logs_sistema                                           ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('logs_sistema');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs do Sistema - Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; }
        .header-page {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
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
        .log-content {
            background-color: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            max-height: 500px;
            overflow-y: auto;
            padding: 15px;
            border-radius: 5px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .log-line {
            margin-bottom: 2px;
        }
        .log-timestamp {
            color: #569cd6;
        }
        .log-error {
            color: #f44747;
        }
        .log-warning {
            color: #ffcc02;
        }
        .log-success {
            color: #4ec9b0;
        }
        .log-info {
            color: #9cdcfe;
        }
        .file-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .file-card:hover {
            transform: translateY(-2px);
        }
        .file-size {
            font-size: 0.85em;
            color: #6c757d;
        }
        .category-badge {
            font-size: 0.75em;
        }
        .log-controls {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
        }
        .search-box {
            max-width: 300px;
        }
        .filter-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0" style="min-height: 100vh;">
                <div class="bg-dark text-white p-3 h-100">
                    <h5 class="mb-4"><i class="bi bi-journal-text me-2"></i>Logs</h5>
                    <nav class="nav flex-column">
                        <a class="nav-link text-white-50" href="dashboard.php">
                            <i class="bi bi-house-door me-2"></i>Dashboard
                        </a>
                        <a class="nav-link text-white-50" href="usuarios.php">
                            <i class="bi bi-people me-2"></i>Usuários
                        </a>
                        <a class="nav-link text-white active bg-secondary rounded" href="logs.php">
                            <i class="bi bi-file-text me-2"></i>Logs
                        </a>
                        <hr class="text-white-50">
                        <a class="nav-link text-white-50" href="../logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Sair
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="content-card mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1"><i class="bi bi-file-text me-2 text-secondary"></i>Logs do Sistema</h4>
                            <small class="text-muted">Monitoramento de eventos e erros</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left me-1"></i>Voltar
                            </a>
                            <button class="btn btn-primary btn-sm" onclick="atualizarLogs()">
                                <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Controles -->
                <div class="log-controls">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group search-box">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="searchInput" placeholder="Buscar arquivo...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="filter-buttons">
                                <button class="btn btn-outline-secondary btn-sm" onclick="filtrarPorCategoria('')">Todos</button>
                                <button class="btn btn-outline-primary btn-sm" onclick="filtrarPorCategoria('Sincronização')">Sincronização</button>
                                <button class="btn btn-outline-success btn-sm" onclick="filtrarPorCategoria('Leitura Facial')">Leitura</button>
                                <button class="btn btn-outline-warning btn-sm" onclick="filtrarPorCategoria('Limpeza Facial')">Limpeza</button>
                                <button class="btn btn-outline-danger btn-sm" onclick="filtrarPorCategoria('Remoções')">Remoções</button>
                                <button class="btn btn-outline-info btn-sm" onclick="filtrarPorCategoria('Processamento')">Processamento</button>
                                <button class="btn btn-outline-dark btn-sm" onclick="filtrarPorCategoria('Sistema')">Sistema</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Logs -->
                <div class="row" id="logsContainer">
                    <div class="col-12 text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para visualizar log -->
    <div class="modal fade" id="modalLog" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLogTitle">
                        <i class="bi bi-file-text me-2"></i>Visualizar Log
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Linhas a exibir:</label>
                                <select class="form-select" id="linhasSelect">
                                    <option value="50">50 linhas</option>
                                    <option value="100" selected>100 linhas</option>
                                    <option value="200">200 linhas</option>
                                    <option value="500">500 linhas</option>
                                    <option value="1000">1000 linhas</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Buscar no conteúdo:</label>
                                <input type="text" class="form-control" id="searchContent" placeholder="Digite para buscar...">
                            </div>
                        </div>
                    </div>
                    <div class="log-content" id="logContent">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" onclick="atualizarConteudoLog()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
    <script>
        let logsData = [];
        let filtroCategoria = '';
        let arquivoAtual = '';

        $(document).ready(function() {
            carregarLogs();
            
            // Busca em tempo real
            $('#searchInput').on('input', function() {
                filtrarLogs();
            });
            
            // Busca no conteúdo do log
            $('#searchContent').on('input', function() {
                buscarNoConteudo();
            });
            
            // Atualizar quando mudar número de linhas
            $('#linhasSelect').on('change', function() {
                if (arquivoAtual) {
                    carregarConteudoLog(arquivoAtual);
                }
            });
        });

        function carregarLogs() {
            $.ajax({
                url: '../api/logs/listar.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'sucesso') {
                        logsData = response.logs;
                        exibirLogs();
                    } else {
                        exibirToast('Erro ao carregar logs: ' + response.mensagem, 'error');
                    }
                },
                error: function() {
                    exibirToast('Erro ao carregar logs', 'error');
                }
            });
        }

        function exibirLogs() {
            let html = '';
            let logsFiltrados = logsData;
            
            // Aplicar filtro de categoria
            if (filtroCategoria) {
                logsFiltrados = logsFiltrados.filter(log => log.categoria === filtroCategoria);
            }
            
            // Aplicar filtro de busca
            const termoBusca = $('#searchInput').val().toLowerCase();
            if (termoBusca) {
                logsFiltrados = logsFiltrados.filter(log => 
                    log.nome.toLowerCase().includes(termoBusca)
                );
            }
            
            if (logsFiltrados.length === 0) {
                html = '<div class="col-12 text-center"><p class="text-muted">Nenhum log encontrado</p></div>';
            } else {
                logsFiltrados.forEach(log => {
                    const tamanhoFormatado = formatarTamanho(log.tamanho);
                    const dataFormatada = new Date(log.modificado).toLocaleString('pt-BR');
                    
                    html += `
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card file-card h-100" onclick="abrirLog('${log.nome}')">
                                <div class="card-body">
                                    <div class="d-flex align-items-start mb-2">
                                        <i class="bi ${log.icone} text-${log.cor} me-2 fs-4"></i>
                                        <div class="flex-grow-1">
                                            <h6 class="card-title mb-1">${log.nome}</h6>
                                            <span class="badge bg-${log.cor} category-badge">${log.categoria}</span>
                                        </div>
                                    </div>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i>${dataFormatada}<br>
                                            <i class="bi bi-file-earmark me-1"></i>${tamanhoFormatado}
                                        </small>
                                    </p>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            
            $('#logsContainer').html(html);
        }

        function filtrarPorCategoria(categoria) {
            filtroCategoria = categoria;
            exibirLogs();
        }

        function filtrarLogs() {
            exibirLogs();
        }

        function abrirLog(nomeArquivo) {
            arquivoAtual = nomeArquivo;
            $('#modalLogTitle').html(`<i class="bi bi-file-text me-2"></i>${nomeArquivo}`);
            $('#modalLog').modal('show');
            carregarConteudoLog(nomeArquivo);
        }

        function carregarConteudoLog(nomeArquivo) {
            const linhas = $('#linhasSelect').val();
            
            $('#logContent').html(`
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            `);
            
            $.ajax({
                url: '../api/logs/ler.php',
                method: 'GET',
                data: { arquivo: nomeArquivo, linhas: linhas },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'sucesso') {
                        exibirConteudoLog(response.conteudo, response.linhas, response.linhas_exibidas);
                    } else {
                        $('#logContent').html(`<div class="text-danger">Erro: ${response.mensagem}</div>`);
                    }
                },
                error: function() {
                    $('#logContent').html('<div class="text-danger">Erro ao carregar conteúdo do log</div>');
                }
            });
        }

        function exibirConteudoLog(conteudo, totalLinhas, linhasExibidas) {
            const linhas = conteudo.split('\n');
            let html = '';
            
            linhas.forEach((linha, index) => {
                if (linha.trim()) {
                    let classeLinha = 'log-line';
                    let conteudoLinha = linha;
                    
                    // Detectar tipo de log por conteúdo
                    if (linha.includes('ERROR') || linha.includes('FATAL') || linha.includes('ERRO')) {
                        classeLinha += ' log-error';
                    } else if (linha.includes('WARNING') || linha.includes('WARN')) {
                        classeLinha += ' log-warning';
                    } else if (linha.includes('SUCCESS') || linha.includes('OK')) {
                        classeLinha += ' log-success';
                    } else if (linha.includes('INFO') || linha.includes('DEBUG')) {
                        classeLinha += ' log-info';
                    }
                    
                    // Destacar timestamp
                    const timestampMatch = linha.match(/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/);
                    if (timestampMatch) {
                        conteudoLinha = linha.replace(
                            timestampMatch[0], 
                            `<span class="log-timestamp">${timestampMatch[0]}</span>`
                        );
                    }
                    
                    html += `<div class="${classeLinha}">${conteudoLinha}</div>`;
                }
            });
            
            const infoLinhas = totalLinhas > linhasExibidas ? 
                `Mostrando as últimas ${linhasExibidas} de ${totalLinhas} linhas` : 
                `${totalLinhas} linhas`;
            
            $('#logContent').html(`
                <div class="mb-2 text-muted small">${infoLinhas}</div>
                <div>${html}</div>
            `);
        }

        function buscarNoConteudo() {
            const termo = $('#searchContent').val().toLowerCase();
            if (!termo) {
                // Remover destaques
                $('.log-line').removeClass('bg-warning');
                return;
            }
            
            $('.log-line').each(function() {
                const texto = $(this).text().toLowerCase();
                if (texto.includes(termo)) {
                    $(this).addClass('bg-warning');
                } else {
                    $(this).removeClass('bg-warning');
                }
            });
        }

        function atualizarConteudoLog() {
            if (arquivoAtual) {
                carregarConteudoLog(arquivoAtual);
            }
        }

        function atualizarLogs() {
            carregarLogs();
        }

        function formatarTamanho(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }

        function exibirToast(mensagem, tipo) {
            // Implementar sistema de toast se necessário
            console.log(`${tipo.toUpperCase()}: ${mensagem}`);
        }
    </script>
</body>
</html>
