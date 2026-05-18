<?php
// Iniciar sessão e incluir arquivos de autenticação e configuração
session_start();
require_once 'auth/verifica_sessao.php';
require_once 'config/timezone.php';

// Verificar se o usuário é administrador
$isAdmin = $_SESSION['usuario_categoria'] === 'admin';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <!-- Configurações básicas da página -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor de Leitura Facial - Sistema de Presença</title>
    
    <!-- Incluir Bootstrap CSS e ícones -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* ===== ESTILOS PARA CONTAINER DE LOGS ===== */
        .log-container {
            background-color: #1e1e1e;        /* Fundo escuro para simular terminal */
            color: #ffffff;                   /* Texto branco */
            font-family: 'Courier New', monospace;  /* Fonte monospace para logs */
            font-size: 14px;
            max-height: 500px;                /* Altura máxima com scroll */
            overflow-y: auto;                 /* Scroll vertical quando necessário */
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #333;
        }
        
        /* ===== CORES PARA DIFERENTES TIPOS DE LOG ===== */
        .log-success {
            color: #28a745;                   /* Verde para logs de sucesso */
        }
        
        .log-warning {
            color: #ffc107;                   /* Amarelo para logs de aviso */
        }
        
        .log-error {
            color: #dc3545;                   /* Vermelho para logs de erro */
        }
        
        .log-info {
            color: #17a2b8;                   /* Azul para logs informativos */
        }
        
        /* ===== CORES PARA STATUS DO SISTEMA ===== */
        .status-online {
            color: #28a745;                   /* Verde para sistema online */
        }
        
        .status-offline {
            color: #dc3545;                   /* Vermelho para sistema offline */
        }
        
        /* ===== EFEITOS HOVER PARA CARDS DE ESTATÍSTICAS ===== */
        .card-stats {
            transition: all 0.3s ease;        /* Transição suave */
        }
        
        .card-stats:hover {
            transform: translateY(-2px);      /* Eleva o card no hover */
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);  /* Sombra no hover */
        }
        
        /* ===== ANIMAÇÃO DE PULSO PARA AUTO-REFRESH ===== */
        .auto-refresh {
            animation: pulse 2s infinite;     /* Animação de pulso contínua */
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        /* ===== ESTILOS PARA CONTAINER DE ACOMPANHAMENTO ===== */
        .acompanhamento-container {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);  /* Gradiente sutil */
            min-height: 400px;                /* Altura mínima */
            max-height: 600px;                /* Altura máxima com scroll */
            overflow-y: auto;                 /* Scroll vertical quando necessário */
            padding: 20px;
        }
        
        /* ===== ESTILOS PARA CARDS DE PRESENÇA ===== */
        .presenca-card {
            background: white;                /* Fundo branco */
            border-radius: 12px;              /* Bordas arredondadas */
            padding: 20px;                    /* Espaçamento interno */
            margin-bottom: 15px;              /* Espaçamento entre cards */
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);  /* Sombra sutil */
            border-left: 5px solid;           /* Borda esquerda colorida */
            transition: all 0.3s ease;        /* Transição suave */
            animation: slideIn 0.5s ease-out; /* Animação de entrada */
        }
        
        .presenca-card:hover {
            transform: translateY(-2px);      /* Eleva no hover */
            box-shadow: 0 8px 15px rgba(0,0,0,0.15);  /* Sombra mais intensa no hover */
        }
        
        /* ===== CORES DAS BORDAS POR STATUS ===== */
        .presenca-card.presente {
            border-left-color: #28a745;       /* Verde para presente */
        }
        
        .presenca-card.atrasado {
            border-left-color: #ffc107;       /* Amarelo para atrasado */
        }
        
        .presenca-card.fora-horario {
            border-left-color: #dc3545;       /* Vermelho para fora de horário */
        }
        
        /* ===== CABEÇALHO DO CARD DE PRESENÇA ===== */
        .presenca-header {
            display: flex;
            justify-content: between;         /* Espaçamento entre nome e status */
            align-items: center;
            margin-bottom: 10px;
        }
        
        .presenca-nome {
            font-size: 1.2rem;               /* Tamanho maior para o nome */
            font-weight: 600;                /* Negrito */
            color: #2c3e50;                  /* Cor escura */
            margin: 0;
        }
        
        /* ===== BADGE DE STATUS ===== */
        .presenca-status {
            padding: 6px 12px;               /* Espaçamento interno */
            border-radius: 20px;             /* Bordas arredondadas */
            font-size: 0.9rem;               /* Tamanho da fonte */
            font-weight: 500;                /* Peso da fonte */
            text-transform: uppercase;       /* Texto em maiúsculas */
            letter-spacing: 0.5px;           /* Espaçamento entre letras */
        }
        
        /* ===== CORES DOS BADGES POR STATUS ===== */
        .presenca-status.presente {
            background: #d4edda;             /* Fundo verde claro */
            color: #155724;                  /* Texto verde escuro */
        }
        
        .presenca-status.atrasado {
            background: #fff3cd;             /* Fundo amarelo claro */
            color: #856404;                  /* Texto amarelo escuro */
        }
        
        .presenca-status.fora-horario {
            background: #f8d7da;             /* Fundo vermelho claro */
            color: #721c24;                  /* Texto vermelho escuro */
        }
        
        /* ===== DETALHES DO CARD (HORÁRIO E TIPO) ===== */
        .presenca-detalhes {
            display: flex;
            justify-content: space-between;   /* Espaçamento entre horário e tipo */
            align-items: center;
            color: #6c757d;                  /* Cor cinza */
            font-size: 0.9rem;               /* Tamanho menor */
        }
        
        .presenca-horario {
            display: flex;
            align-items: center;
            gap: 5px;                        /* Espaçamento entre ícone e texto */
        }
        
        .presenca-tipo {
            display: flex;
            align-items: center;
            gap: 5px;                        /* Espaçamento entre ícone e texto */
        }
        
        /* ===== ANIMAÇÃO DE ENTRADA DOS CARDS ===== */
        @keyframes slideIn {
            from {
                opacity: 0;                  /* Inicia invisível */
                transform: translateX(-20px); /* Inicia deslocado para a esquerda */
            }
            to {
                opacity: 1;                  /* Termina visível */
                transform: translateX(0);    /* Termina na posição normal */
            }
        }
        
        /* ===== GRADIENTE PARA CABEÇALHO ===== */
        .bg-gradient-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }
    </style>
</head>
<body class="bg-light">
    <!-- ===== NAVEGAÇÃO PRINCIPAL ===== -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <!-- Logo e título do sistema -->
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-eye me-2"></i>Monitor de Leitura Facial
            </a>
            
            <!-- Menu do usuário (lado direito) -->
            <div class="navbar-nav ms-auto">
                <!-- Informações do usuário logado -->
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['usuario_nome']) ?>
                    <?php if ($isAdmin): ?>
                    <span class="badge bg-warning text-dark ms-1">Admin</span>
                    <?php endif; ?>
                </span>
                
                <!-- Botão para voltar ao dashboard -->
                <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-house-door me-1"></i>Dashboard
                </a>
                
                <!-- Botão para sair do sistema -->
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- ===== CABEÇALHO DA PÁGINA ===== -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <!-- Título e informações de status -->
                    <div>
                        <h2 class="mb-1">
                            <i class="bi bi-eye-fill me-2"></i>Monitor de Leitura Facial
                        </h2>
                        <p class="text-muted mb-0">
                            <!-- Badge de status do auto-refresh -->
                            <span id="status-auto-refresh" class="badge bg-success me-2">
                                <i class="bi bi-arrow-clockwise me-1"></i>Auto-refresh Ativo
                            </span>
                            <!-- Badge de filtro ativo -->
                            <span class="badge bg-info me-2">
                                <i class="bi bi-funnel me-1"></i>Filtro: Resultados de leitura
                            </span>
                            <!-- Timestamp da última atualização -->
                            <span id="ultima-atualizacao">Carregando...</span>
                        </p>
                    </div>
                    
                    <!-- Botões de controle -->
                    <div>
                        <!-- Botão para pausar/retomar auto-refresh -->
                        <button class="btn btn-outline-primary me-2" id="btn-pausar">
                            <i class="bi bi-pause-fill me-1"></i>Pausar
                        </button>
                        <!-- Botão para atualização manual -->
                        <button class="btn btn-primary" id="btn-atualizar">
                            <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
                        </button>
                    </div>
                </div>
            </div>
        </div>


        <!-- ===== CARDS DE ESTATÍSTICAS ===== -->
        <div class="row mb-4">
            <!-- Card: Total de Leituras Hoje -->
            <div class="col-md-3">
                <div class="card card-stats border-success">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle-fill text-success fs-1"></i>
                        <h5 class="card-title mt-2">Leituras Hoje</h5>
                        <h3 class="text-success" id="total-leituras">0</h3>
                    </div>
                </div>
            </div>
            
            <!-- Card: Dispositivos Ativos -->
            <div class="col-md-3">
                <div class="card card-stats border-info">
                    <div class="card-body text-center">
                        <i class="bi bi-people-fill text-info fs-1"></i>
                        <h5 class="card-title mt-2">Dispositivos Ativos</h5>
                        <h3 class="text-info" id="dispositivos-ativos">0</h3>
                    </div>
                </div>
            </div>
            
            <!-- Card: Última Leitura -->
            <div class="col-md-3">
                <div class="card card-stats border-warning">
                    <div class="card-body text-center">
                        <i class="bi bi-clock-fill text-warning fs-1"></i>
                        <h5 class="card-title mt-2">Última Leitura</h5>
                        <small class="text-muted" id="ultima-leitura">Nenhuma</small>
                    </div>
                </div>
            </div>
            
            <!-- Card: Status do Sistema -->
            <div class="col-md-3">
                <div class="card card-stats border-primary">
                    <div class="card-body text-center">
                        <i class="bi bi-activity text-primary fs-1"></i>
                        <h5 class="card-title mt-2">Status Sistema</h5>
                        <span class="badge bg-success" id="status-sistema">Online</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== SEÇÃO DE LOGS EM TEMPO REAL ===== -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <!-- Cabeçalho da seção de logs -->
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-terminal me-2"></i>Logs em Tempo Real
                            <!-- Contador de logs processados -->
                            <span class="badge bg-secondary ms-2" id="total-linhas">0 linhas</span>
                        </h5>
                    </div>
                    
                    <!-- Container dos logs com scroll -->
                    <div class="card-body p-0">
                        <div id="logs-container" class="log-container">
                            <!-- Estado inicial: carregando -->
                            <div class="text-center text-muted">
                                <i class="bi bi-hourglass-split fs-1"></i>
                                <p>Carregando logs...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== TELA DE ACOMPANHAMENTO DE PRESENÇAS ===== -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-lg">
                    <!-- Cabeçalho com gradiente azul -->
                    <div class="card-header bg-gradient-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-people-fill me-2"></i>Acompanhamento de Presenças
                            <!-- Contador de presenças -->
                            <span class="badge bg-light text-dark ms-2" id="total-presencas">0 presenças</span>
                        </h5>
                    </div>
                    
                    <!-- Container dos cards de presença -->
                    <div class="card-body p-0">
                        <div id="acompanhamento-container" class="acompanhamento-container">
                            <!-- Estado inicial: aguardando leituras -->
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-person-check fs-1 text-muted"></i>
                                <p class="mt-3">Aguardando leituras faciais...</p>
                                <small class="text-muted">As presenças aparecerão aqui em tempo real</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Incluir jQuery e Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ===== VARIÁVEIS GLOBAIS =====
        let autoRefresh = true;        // Flag para controlar auto-refresh
        let refreshInterval;           // ID do intervalo de atualização
        
        // ===== INICIALIZAÇÃO DA PÁGINA =====
        $(document).ready(function() {
            carregarDados();           // Carregar dados iniciais
            iniciarAutoRefresh();      // Iniciar atualização automática
            
            // Configurar eventos dos botões
            $('#btn-pausar').click(toggleAutoRefresh);    // Pausar/retomar auto-refresh
            $('#btn-atualizar').click(carregarDados);      // Atualização manual
        });
        
        // ===== FUNÇÃO PARA INICIAR AUTO-REFRESH =====
        function iniciarAutoRefresh() {
            if (autoRefresh) {
                // Configurar intervalo de 5 segundos para atualizar dados
                refreshInterval = setInterval(carregarDados, 5000);
                
                // Atualizar interface: badge verde e botão "Pausar"
                $('#status-auto-refresh').removeClass('bg-secondary').addClass('bg-success');
                $('#btn-pausar').html('<i class="bi bi-pause-fill me-1"></i>Pausar');
            }
        }
        
        // ===== FUNÇÃO PARA PAUSAR/RETOMAR AUTO-REFRESH =====
        function toggleAutoRefresh() {
            autoRefresh = !autoRefresh;  // Alternar estado
            
            if (autoRefresh) {
                // Se ativado, iniciar auto-refresh
                iniciarAutoRefresh();
            } else {
                // Se pausado, parar intervalo e atualizar interface
                clearInterval(refreshInterval);
                $('#status-auto-refresh').removeClass('bg-success').addClass('bg-secondary');
                $('#btn-pausar').html('<i class="bi bi-play-fill me-1"></i>Retomar');
            }
        }
        
        // ===== FUNÇÃO PRINCIPAL PARA CARREGAR DADOS =====
        function carregarDados() {
            // Fazer requisição AJAX para buscar dados do dispositivo facial
            $.ajax({
                url: 'api/monitor/fetch_dispositivo_tempo_real.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Se sucesso, atualizar todas as seções da interface
                        atualizarEstatisticas(response.estatisticas);    // Cards de estatísticas
                        atualizarLogs(response.logs);                    // Logs em tempo real
                        atualizarAcompanhamento(response.presencas);     // Cards de presença
                        $('#ultima-atualizacao').text('Última atualização: ' + new Date().toLocaleTimeString());
                    } else {
                        // Se erro na API, mostrar mensagem de erro
                        console.error('Erro na captura:', response.message);
                        atualizarLogs([{
                            timestamp: new Date().toLocaleTimeString(),
                            mensagem: 'Erro na captura: ' + response.message,
                            tipo: 'error'
                        }]);
                    }
                },
                error: function() {
                    // Se erro de conexão, mostrar mensagem de erro
                    console.error('Erro ao conectar com o dispositivo');
                    atualizarLogs([{
                        timestamp: new Date().toLocaleTimeString(),
                        mensagem: 'Erro de conexão com o dispositivo',
                        tipo: 'error'
                    }]);
                }
            });
        }
        
        // ===== FUNÇÃO PARA ATUALIZAR CARDS DE ESTATÍSTICAS =====
        function atualizarEstatisticas(stats) {
            // Atualizar contador de leituras do dia
            $('#total-leituras').text(stats.total_leituras || 0);
            
            // Atualizar contador de dispositivos ativos
            $('#dispositivos-ativos').text(stats.dispositivos_ativos || 0);
            
            // Atualizar horário da última leitura
            $('#ultima-leitura').text(stats.ultima_leitura || 'Nenhuma');
            
            // Atualizar status do sistema
            $('#status-sistema').text(stats.status_sistema || 'Online');
        }
        
        // ===== FUNÇÃO PARA ATUALIZAR LOGS EM TEMPO REAL =====
        function atualizarLogs(logs) {
            const container = $('#logs-container');
            
            // Filtrar apenas mensagens de resultado de leitura facial
            // (ignorar logs técnicos, mostrar apenas resultados de presença)
            const logsFiltrados = logs.filter(function(log) {
                const mensagem = log.mensagem || '';
                return mensagem.includes('Usuário ') && (
                       mensagem.includes(' - Presente') ||
                       mensagem.includes(' - Atrasado') ||
                       mensagem.includes(' - Fora de horário') ||
                       mensagem.includes(' - Falta')
                );
            });
            
            // Contar logs filtrados e originais
            const totalLinhas = logsFiltrados.length;
            const totalOriginal = logs.length;
            
            // Atualizar contador no cabeçalho
            $('#total-linhas').text(`${totalLinhas} leituras processadas (${totalOriginal} total)`);
            
            // Se não há logs filtrados, mostrar mensagem de aguardo
            if (totalLinhas === 0) {
                container.html(`
                    <div class="text-center text-muted">
                        <i class="bi bi-inbox fs-1"></i>
                        <p>Nenhuma leitura facial processada</p>
                        <small>Aguardando leituras faciais...</small>
                    </div>
                `);
                return;
            }
            
            // Construir HTML dos logs filtrados
            let html = '';
            logsFiltrados.forEach(function(log) {
                const timestamp = log.timestamp || '';
                const mensagem = log.mensagem || '';
                const tipo = log.tipo || 'info';
                
                // Determinar classe CSS baseada no tipo de log
                let classeCor = '';
                switch(tipo) {
                    case 'success':
                        classeCor = 'log-success';    // Verde para sucesso
                        break;
                    case 'warning':
                        classeCor = 'log-warning';    // Amarelo para aviso
                        break;
                    case 'error':
                        classeCor = 'log-error';      // Vermelho para erro
                        break;
                    default:
                        classeCor = 'log-info';       // Azul para info
                }
                
                // Adicionar linha de log ao HTML
                html += `<div class="${classeCor}">[${timestamp}] ${mensagem}</div>`;
            });
            
            // Atualizar container e fazer scroll para o final
            container.html(html);
            container.scrollTop(container[0].scrollHeight);
        }
        
        // ===== FUNÇÃO PARA ATUALIZAR TELA DE ACOMPANHAMENTO =====
        function atualizarAcompanhamento(presencas) {
            const container = $('#acompanhamento-container');
            const totalPresencas = $('#total-presencas');
            
            // Se não há presenças, mostrar mensagem de aguardo
            if (!presencas || presencas.length === 0) {
                container.html(`
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-person-check fs-1 text-muted"></i>
                        <p class="mt-3">Aguardando leituras faciais...</p>
                        <small class="text-muted">As presenças aparecerão aqui em tempo real</small>
                    </div>
                `);
                totalPresencas.text('0 presenças');
                return;
            }
            
            // Construir HTML dos cards de presença
            let html = '';
            presencas.forEach(function(presenca) {
                // Extrair dados da presença
                const nome = presenca.nome || 'Usuário';
                const status = presenca.status || 'presente';
                const horario = presenca.horario_confirmacao || '';
                const tipo = presenca.tipo_confirmacao || 'facial';
                const observacoes = presenca.observacoes || '';
                
                // Determinar classe CSS, texto e ícone baseado no status
                let statusClass = 'presente';
                let statusText = 'Presente';
                let statusIcon = 'bi-check-circle-fill';
                
                if (status === 'atrasado') {
                    statusClass = 'atrasado';
                    statusText = 'Atrasado';
                    statusIcon = 'bi-clock-fill';
                } else if (status === 'falta' || status === 'ausente') {
                    statusClass = 'fora-horario';
                    statusText = 'Fora de horário';
                    statusIcon = 'bi-x-circle-fill';
                }
                
                // Construir card de presença com animação
                html += `
                    <div class="presenca-card ${statusClass}">
                        <div class="presenca-header">
                            <h6 class="presenca-nome">${nome}</h6>
                            <span class="presenca-status ${statusClass}">
                                <i class="bi ${statusIcon} me-1"></i>${statusText}
                            </span>
                        </div>
                        <div class="presenca-detalhes">
                            <div class="presenca-horario">
                                <i class="bi bi-clock me-1"></i>
                                <span>${horario}</span>
                            </div>
                            <div class="presenca-tipo">
                                <i class="bi bi-${tipo === 'facial' ? 'camera' : 'person'} me-1"></i>
                                <span>${tipo === 'facial' ? 'Facial' : 'Manual'}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            // Atualizar container e contador
            container.html(html);
            totalPresencas.text(`${presencas.length} presenças`);
        }
    </script>
</body>
</html>
