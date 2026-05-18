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
$usuarioEmail = $_SESSION['usuario_email'] ?? '';

// Nome do mês atual
$nomesMeses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];
$mesAtual = intval(date('n'));
$anoAtual = intval(date('Y'));
$nomeMesAtual = $nomesMeses[$mesAtual];

// Verificar módulos disponíveis
$temGerenciamento = MenuPermissaoService::podeAcessar('painel_dashboard');
$temCulto = MenuPermissaoService::podeAcessar('culto_dashboard');
$temRefeicoes = MenuPermissaoService::podeAcessar('refeicoes_reserva');
$temFrota = MenuPermissaoService::podeAcessar('frota_dashboard');
$temEstoque = MenuPermissaoService::podeAcessar('estoque_dashboard');
?>
<!DOCTYPE html>
<html lang="pt-br" id="htmlTheme" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Presença</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#0d59f2",
                        "background-light": "#f1f5f9",
                        "background-dark": "#101622",
                        "card-dark": "#1e293b",
                        "card-light": "#ffffff",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                },
            },
        }
    </script>
    <style>
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        /* Modo Claro - Melhor contraste */
        :not(.dark) .glass-panel,
        [data-theme="light"] .glass-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        /* Ajustar fundo do body no modo claro */
        :not(.dark) body,
        [data-theme="light"] body {
            background-color: #f1f5f9 !important;
        }
        /* Melhorar contraste dos textos no modo claro */
        :not(.dark) .text-slate-900,
        [data-theme="light"] .text-slate-900 {
            color: #0f172a !important;
        }
        :not(.dark) .text-slate-500,
        [data-theme="light"] .text-slate-500 {
            color: #475569 !important;
        }
        :not(.dark) .text-slate-400,
        [data-theme="light"] .text-slate-400 {
            color: #64748b !important;
        }
        /* Correção específica apenas para header e título de módulos no modo escuro */
        .dark header h1.text-slate-900,
        [data-theme="dark"] header h1.text-slate-900 {
            color: #f1f5f9 !important;
        }
        .dark main h5.text-slate-900,
        [data-theme="dark"] main h5.text-slate-900 {
            color: #f1f5f9 !important;
        }
        /* Melhorar contraste do sidebar no modo claro */
        :not(.dark) nav,
        [data-theme="light"] nav {
            background-color: #1e293b !important;
        }
        /* Melhorar contraste dos headers do calendário no modo claro */
        :not(.dark) .day-header,
        [data-theme="light"] .day-header {
            background-color: #475569 !important;
            color: white !important;
        }
        @keyframes reveal {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .reveal-item {
            animation: reveal 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        .delay-5 { animation-delay: 0.5s; }
        
        /* Calendário */
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        @media (max-width: 640px) {
            .calendar-day {
                font-size: 0.6rem;
                border-radius: 4px;
            }
        }
        .calendar-day:hover {
            transform: scale(1.1);
            z-index: 10;
        }
        @media (max-width: 640px) {
            .calendar-day:hover {
                transform: scale(1.05);
            }
        }
        .day-header {
            background-color: #475569;
            color: white;
            font-weight: bold;
            font-size: 0.6rem;
            cursor: default;
        }
        @media (max-width: 640px) {
            .day-header {
                font-size: 0.5rem;
            }
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
        .day-reserva { background-color: #22c55e; color: white; }
        .day-reserva-adicional { 
            background: linear-gradient(to right, #22c55e 50%, #3b82f6 50%);
            color: white; 
        }
        .day-apenas-adicional { background-color: #3b82f6; color: white; }
        .day-sem-reserva { background-color: #ef4444; color: white; }
        /* Cores do calendário de culto */
        .day-presente { background-color: #22c55e; color: white; }
        .day-falta { background-color: #ef4444; color: white; }
        .day-atrasado { background-color: #3b82f6; color: white; }
        .day-justificativa-aceita { background-color: #eab308; color: #1e293b; }
        .day-justificativa-pendente { background-color: #ef4444; color: white; }
        .day-justificativa-rejeitada { background-color: #ef4444; color: white; }
        .day-sem-culto { background-color: #334155; color: #94a3b8; cursor: default; }
        .day-nao-culto { background-color: #1e293b; color: #64748b; cursor: default; }
        .day-sem-culto:hover, .day-nao-culto:hover {
            transform: none;
        }
        .dependente-indicator {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 6px;
            height: 6px;
            background-color: #3b82f6;
            border-radius: 50%;
        }
        @media (max-width: 640px) {
            .dependente-indicator {
                width: 4px;
                height: 4px;
                top: 1px;
                right: 1px;
            }
        }
        
        /* Campo de busca - garantir contraste */
        #campoBusca {
            color: #1e293b !important;
            background-color: #ffffff !important;
        }
        .dark #campoBusca {
            color: #ffffff !important;
            background-color: #1e293b !important;
        }
        #campoBusca::placeholder {
            color: #64748b !important;
        }
        .dark #campoBusca::placeholder {
            color: #94a3b8 !important;
        }
        
        /* Sidebar Mobile */
        .sidebar-mobile {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }
        .sidebar-mobile.open {
            transform: translateX(0);
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 30;
        }
        .sidebar-overlay.open {
            display: block;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-white font-display h-screen flex overflow-hidden selection:bg-primary selection:text-white">
    <!-- Overlay para fechar sidebar no mobile -->
    <div class="sidebar-overlay lg:hidden" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Sidebar -->
    <nav class="fixed lg:static w-64 h-full flex flex-col py-6 bg-[#101623] border-r border-white/5 z-40 lg:z-20 shrink-0 transition-all duration-300 sidebar-mobile lg:translate-x-0" id="sidebar">
        <div class="px-6 mb-10 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="bg-primary aspect-square rounded-xl size-8 flex items-center justify-center shadow-lg shadow-primary/30">
                    <span class="material-symbols-outlined text-white text-xl">grid_view</span>
                </div>
                <span class="text-lg font-bold text-white tracking-tight">Intranet</span>
            </div>
            <button onclick="toggleSidebar()" class="lg:hidden p-1 rounded-lg hover:bg-white/10 text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="px-4 mb-8">
            <h3 class="px-2 text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Painéis</h3>
            <a class="group flex items-center gap-3 px-3 py-2.5 rounded-xl bg-primary text-white shadow-lg shadow-primary/25 transition-all" href="#">
                <span class="material-symbols-outlined filled">dashboard</span>
                <span class="text-sm font-medium">Visão Geral</span>
            </a>
        </div>
        <div class="px-4 flex-1 overflow-y-auto">
            <h3 class="px-2 text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Módulos</h3>
            <div class="flex flex-col gap-1">
                <?php
                // ═══════════════════════════════════════════════════════════════════════════
                // RENDERIZAÇÃO DINÂMICA DOS MÓDULOS
                // Busca menus do banco de dados automaticamente
                // Fallback para código hardcoded caso não encontre menus ou ocorra erro
                // ═══════════════════════════════════════════════════════════════════════════
                try {
                    $modulos_dinamicos = MenuPermissaoService::getMenusModulosSidebar();
                    
                    if (!empty($modulos_dinamicos)) {
                        // Renderizar módulos dinamicamente do banco
                        foreach ($modulos_dinamicos as $modulo) {
                            $url = MenuPermissaoService::ajustarUrl($modulo['url']);
                            // Garantir que o ícone seja válido (Material Symbols usa underscore, não hífen)
                            $icone = htmlspecialchars(str_replace('-', '_', $modulo['icone'] ?? 'circle'));
                            $nome = htmlspecialchars($modulo['nome']);
                            $cor_hover = htmlspecialchars($modulo['cor_hover']);
                            
                            echo <<<HTML
<a href="{$url}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all duration-200">
    <span class="material-symbols-outlined text-[20px] group-hover:text-{$cor_hover} transition-colors">{$icone}</span>
    <span class="text-sm font-medium">{$nome}</span>
</a>
HTML;
                        }
                    } else {
                        // Fallback: usar código hardcoded se não encontrar menus no banco
                        if ($temCulto): ?>
                        <a href="<?= MenuPermissaoService::ajustarUrl('/culto/dashboard.php') ?>" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all duration-200">
                            <span class="material-symbols-outlined text-[20px] group-hover:text-primary transition-colors">calendar_month</span>
                            <span class="text-sm font-medium">Presença</span>
                        </a>
                        <?php endif; ?>
                        <?php if ($temRefeicoes): ?>
                        <a href="<?= MenuPermissaoService::ajustarUrl('/reservas/almoco.php') ?>" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all duration-200">
                            <span class="material-symbols-outlined text-[20px] group-hover:text-orange-400 transition-colors">restaurant</span>
                            <span class="text-sm font-medium">Refeições</span>
                        </a>
                        <?php endif; ?>
                        <?php if ($temFrota): ?>
                        <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all duration-200">
                            <span class="material-symbols-outlined text-[20px] group-hover:text-blue-400 transition-colors">directions_car</span>
                            <span class="text-sm font-medium">Veículos</span>
                        </a>
                        <?php endif; ?>
                        <?php if ($temEstoque): ?>
                        <a href="<?= MenuPermissaoService::ajustarUrl('/estoque/dashboard.php') ?>" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all duration-200">
                            <span class="material-symbols-outlined text-[20px] group-hover:text-purple-400 transition-colors">inventory</span>
                            <span class="text-sm font-medium">Estoque</span>
                        </a>
                        <?php endif; ?>
                        <?php if ($temGerenciamento): ?>
                        <a href="<?= MenuPermissaoService::ajustarUrl('/painel/dashboard.php') ?>" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all duration-200">
                            <span class="material-symbols-outlined text-[20px] group-hover:text-purple-400 transition-colors">settings</span>
                            <span class="text-sm font-medium">Sistema</span>
                        </a>
                        <?php endif;
                    }
                } catch (Exception $e) {
                    // Em caso de erro, usar fallback hardcoded
                    error_log("Erro ao buscar módulos dinamicamente: " . $e->getMessage());
                    if ($temCulto): ?>
                    <a href="<?= MenuPermissaoService::ajustarUrl('/culto/dashboard.php') ?>" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all duration-200">
                        <span class="material-symbols-outlined text-[20px] group-hover:text-primary transition-colors">calendar_month</span>
                        <span class="text-sm font-medium">Presença</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($temRefeicoes): ?>
                    <a href="<?= MenuPermissaoService::ajustarUrl('/reservas/almoco.php') ?>" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all duration-200">
                        <span class="material-symbols-outlined text-[20px] group-hover:text-orange-400 transition-colors">restaurant</span>
                        <span class="text-sm font-medium">Refeições</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($temFrota): ?>
                    <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all duration-200">
                        <span class="material-symbols-outlined text-[20px] group-hover:text-blue-400 transition-colors">directions_car</span>
                        <span class="text-sm font-medium">Veículos</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($temEstoque): ?>
                    <a href="<?= MenuPermissaoService::ajustarUrl('/estoque/dashboard.php') ?>" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all duration-200">
                        <span class="material-symbols-outlined text-[20px] group-hover:text-purple-400 transition-colors">inventory</span>
                        <span class="text-sm font-medium">Estoque</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($temGerenciamento): ?>
                    <a href="<?= MenuPermissaoService::ajustarUrl('/painel/dashboard.php') ?>" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all duration-200">
                        <span class="material-symbols-outlined text-[20px] group-hover:text-purple-400 transition-colors">settings</span>
                        <span class="text-sm font-medium">Sistema</span>
                    </a>
                    <?php endif;
                }
                ?>
            </div>
        </div>
        <div class="px-4 mt-4 pt-4 border-t border-white/5">
            <a href="logout.php" class="flex items-center gap-3 w-full p-2 rounded-xl hover:bg-white/5 transition-colors text-left group">
                <div class="size-9 rounded-full bg-gradient-to-br from-primary to-purple-600 flex items-center justify-center shrink-0 group-hover:ring-2 group-hover:ring-primary/50 transition-all">
                    <span class="material-symbols-outlined text-white text-lg">person</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars($nomeUsuario) ?></p>
                    <p class="text-[11px] text-slate-400 truncate"><?= htmlspecialchars($usuarioEmail) ?></p>
                </div>
                <span class="material-symbols-outlined text-slate-500 text-lg">logout</span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-full overflow-hidden relative w-full lg:w-auto">
        <!-- Background Effects -->
        <div class="absolute top-[-20%] right-[-10%] w-[600px] h-[600px] bg-primary/20 rounded-full blur-[120px] pointer-events-none opacity-50 dark:opacity-30 mix-blend-screen"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-[500px] h-[500px] bg-purple-600/20 rounded-full blur-[100px] pointer-events-none opacity-40 dark:opacity-20 mix-blend-screen"></div>
        
        <!-- Header -->
        <header class="flex items-center justify-between px-4 md:px-8 py-4 md:py-6 z-10">
            <div class="flex items-center gap-3 md:gap-0">
                <!-- Botão hambúrguer mobile -->
                <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-white/10 text-white">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div class="flex flex-col">
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Bom dia, <?= htmlspecialchars(explode(' ', $nomeUsuario)[0]) ?></h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs md:text-sm mt-1 hidden sm:block">Veja o que está acontecendo hoje.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 md:gap-4">
                <!-- Botão Toggle Tema -->
                <button id="btnToggleTheme" class="p-2 rounded-lg hover:bg-white/10 text-slate-400 hover:text-white transition-all" title="Alternar tema claro/escuro">
                    <span class="material-symbols-outlined text-xl" id="iconTheme">dark_mode</span>
                </button>
                <div class="relative group hidden md:block">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="material-symbols-outlined text-slate-400 group-focus-within:text-primary transition-colors">search</span>
                    </div>
                    <input id="campoBusca" class="block w-64 lg:w-80 pl-10 pr-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm placeholder-slate-500 dark:placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all shadow-sm" placeholder="Pesquisar módulos, menus..." type="text" autocomplete="off"/>
                    <!-- Dropdown de resultados -->
                    <div id="resultadosBusca" class="hidden absolute top-full left-0 right-0 mt-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl z-50 max-h-96 overflow-y-auto">
                        <div id="conteudoResultadosBusca" class="p-2">
                            <!-- Resultados serão inseridos aqui -->
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8 pt-2">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6 pb-8">
                
                <!-- Card: Presença | Cultos -->
                <div class="glass-panel rounded-2xl md:rounded-3xl p-4 md:p-6 col-span-1 md:col-span-2 lg:col-span-1 xl:col-span-1 row-span-2 flex flex-col reveal-item">
                    <div class="flex items-center justify-between mb-4 md:mb-6">
                        <h2 class="text-base md:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-lg md:text-xl">calendar_month</span>
                            <span class="hidden sm:inline">Presença</span>
                            <span class="sm:hidden">Pres.</span>
                        </h2>
                        <div class="flex gap-1">
                            <button class="p-1 rounded hover:bg-white/10 text-slate-400 hover:text-white" onclick="mudarMesCulto(-1)">
                                <span class="material-symbols-outlined text-sm">chevron_left</span>
                            </button>
                            <span class="text-sm font-medium text-slate-500 dark:text-slate-300 px-2" id="mesAnoCulto"><?= $nomeMesAtual ?></span>
                            <button class="p-1 rounded hover:bg-white/10 text-slate-400 hover:text-white" onclick="mudarMesCulto(1)">
                                <span class="material-symbols-outlined text-sm">chevron_right</span>
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-7 gap-y-2 md:gap-y-4 gap-x-0.5 md:gap-x-1 text-center flex-1 content-start" id="calendario-culto">
                        <!-- Calendário será renderizado aqui -->
                    </div>
                </div>

                <!-- Card: Estatística | Presença -->
                <div class="glass-panel rounded-2xl md:rounded-3xl p-5 md:p-7 col-span-1 md:col-span-1 lg:col-span-1 reveal-item delay-1 flex flex-col justify-between relative overflow-hidden group min-h-[280px] md:min-h-[320px]">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-primary/20 rounded-full blur-[50px] -translate-y-1/2 translate-x-1/2 group-hover:bg-primary/30 transition-all duration-500"></div>
                    <div class="flex items-start justify-between z-10 mb-3">
                        <div>
                            <h2 class="text-lg md:text-xl font-bold text-slate-900 dark:text-white">Frequência</h2>
                            <p class="text-sm md:text-base text-slate-500 dark:text-slate-400">Média mensal</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-center py-4 md:py-6 relative z-10 flex-1">
                        <div class="relative size-32 md:size-40 lg:size-44">
                            <canvas id="graficoPresenca"></canvas>
                        </div>
                    </div>
                    <div class="flex justify-between mt-auto pt-3 md:pt-4 z-10 gap-2" id="legendaGrafico">
                        <!-- Legenda será gerada dinamicamente -->
                    </div>
                </div>

                <!-- Card: Refeições -->
                <div class="glass-panel rounded-2xl md:rounded-3xl p-4 md:p-6 col-span-1 md:col-span-1 lg:col-span-1 reveal-item delay-2 flex flex-col">
                    <div class="flex items-center justify-between mb-3 md:mb-4">
                        <h2 class="text-base md:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-orange-400 text-lg md:text-xl">restaurant</span>
                            <span class="hidden sm:inline">Refeições</span>
                            <span class="sm:hidden">Ref.</span>
                        </h2>
                        <a href="<?= MenuPermissaoService::ajustarUrl('/reservas/almoco.php') ?>" class="text-orange-400 hover:text-orange-300">
                            <span class="material-symbols-outlined text-lg md:text-xl">add_circle</span>
                        </a>
                    </div>
                    <div class="flex flex-col items-center justify-center py-4 md:py-6">
                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">Confirmadas:</p>
                        <div class="text-3xl md:text-4xl font-bold text-slate-900 dark:text-white mb-4 md:mb-6" id="totalConfirmadas">-</div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Saldo Atual:</p>
                        <div class="text-xl md:text-2xl font-semibold text-slate-900 dark:text-white mb-3 md:mb-4" id="saldoAtual">R$ 0,00</div>
                        <div id="statusHorario">
                            <!-- Status do horário será renderizado aqui -->
                        </div>
                    </div>
                </div>

                <!-- Card: Reservas de Almoço -->
                <div class="glass-panel rounded-2xl md:rounded-3xl p-4 md:p-6 col-span-1 md:col-span-2 lg:col-span-1 reveal-item delay-3 flex flex-col">
                    <div class="flex items-center justify-between mb-4 md:mb-6">
                        <h2 class="text-base md:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-green-500 text-lg md:text-xl">event</span>
                            <span class="hidden sm:inline">Reservas Almoço</span>
                            <span class="sm:hidden">Almoço</span>
                        </h2>
                        <div class="flex gap-1">
                            <button class="p-1 rounded hover:bg-white/10 text-slate-400 hover:text-white" onclick="mudarMesAlmoco(-1)">
                                <span class="material-symbols-outlined text-sm">chevron_left</span>
                            </button>
                            <span class="text-sm font-medium text-slate-500 dark:text-slate-300 px-2" id="mesAnoAlmoco"><?= $nomeMesAtual ?></span>
                            <button class="p-1 rounded hover:bg-white/10 text-slate-400 hover:text-white" onclick="mudarMesAlmoco(1)">
                                <span class="material-symbols-outlined text-sm">chevron_right</span>
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-7 gap-y-2 md:gap-y-4 gap-x-0.5 md:gap-x-1 text-center" id="calendario-almoco">
                        <!-- Calendário será renderizado aqui -->
                    </div>
                </div>

                <!-- Card: Veículos -->
                <div class="glass-panel rounded-2xl md:rounded-3xl p-4 md:p-6 col-span-1 md:col-span-1 lg:col-span-2 xl:col-span-2 reveal-item delay-4 flex flex-col">
                    <div class="flex items-center justify-between mb-3 md:mb-4">
                        <h2 class="text-base md:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-lg md:text-xl">directions_car</span>
                            <span class="hidden sm:inline">Disponibilidade da Frota</span>
                            <span class="sm:hidden">Frota</span>
                        </h2>
                        <?php if ($temFrota): ?>
                        <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="text-[10px] md:text-xs font-medium text-primary hover:text-primary/80 transition-colors hidden sm:inline">Ver Tudo</a>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-col gap-3" id="listaVeiculos">
                        <div class="text-center py-4 text-slate-400">
                            <span class="material-symbols-outlined animate-spin">sync</span>
                            <p class="mt-2 text-sm">Carregando...</p>
                        </div>
                    </div>
                </div>

            </div>
            
            <!-- Seção de Módulos do Sistema -->
            <?php
            $totalModulos = ($temGerenciamento ? 1 : 0) + ($temCulto ? 1 : 0) + ($temRefeicoes ? 1 : 0) + ($temFrota ? 1 : 0) + ($temEstoque ? 1 : 0);
            
            if ($totalModulos > 0):
            ?>
            <div class="mt-6 md:mt-8 reveal-item delay-5">
                <h5 class="text-base md:text-lg font-bold text-slate-900 dark:text-white mb-4 md:mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">apps</span>
                    Módulos do Sistema
                </h5>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 md:gap-4">
                    <?php if ($temGerenciamento): ?>
                    <a href="<?= MenuPermissaoService::ajustarUrl('/painel/dashboard.php') ?>" class="glass-panel rounded-2xl p-4 md:p-5 text-center transition-all duration-300 hover:scale-105 hover:shadow-lg border-2 border-slate-600 dark:border-slate-600 hover:border-primary dark:hover:border-primary group">
                        <div class="flex flex-col items-center gap-2 md:gap-3">
                            <div class="p-3 md:p-4 rounded-xl bg-slate-600/20 dark:bg-slate-600/20 group-hover:bg-primary/20 transition-colors">
                                <span class="material-symbols-outlined text-slate-600 dark:text-slate-400 group-hover:text-primary text-2xl md:text-3xl">settings</span>
                            </div>
                            <div class="font-semibold text-sm md:text-base text-slate-900 dark:text-white">Gerenciamento</div>
                            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400">Painel administrativo</p>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($temCulto): ?>
                    <a href="<?= MenuPermissaoService::ajustarUrl('/culto/dashboard.php') ?>" class="glass-panel rounded-2xl p-4 md:p-5 text-center transition-all duration-300 hover:scale-105 hover:shadow-lg border-2 border-purple-600 dark:border-purple-600 hover:border-purple-400 dark:hover:border-purple-400 group">
                        <div class="flex flex-col items-center gap-2 md:gap-3">
                            <div class="p-3 md:p-4 rounded-xl bg-purple-600/20 dark:bg-purple-600/20 group-hover:bg-purple-400/20 transition-colors">
                                <span class="material-symbols-outlined text-purple-600 dark:text-purple-400 group-hover:text-purple-400 text-2xl md:text-3xl">calendar_month</span>
                            </div>
                            <div class="font-semibold text-sm md:text-base text-slate-900 dark:text-white">Culto</div>
                            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400">Presenças</p>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($temRefeicoes): ?>
                    <a href="<?= MenuPermissaoService::ajustarUrl('/reservas/almoco.php') ?>" class="glass-panel rounded-2xl p-4 md:p-5 text-center transition-all duration-300 hover:scale-105 hover:shadow-lg border-2 border-green-600 dark:border-green-600 hover:border-green-400 dark:hover:border-green-400 group">
                        <div class="flex flex-col items-center gap-2 md:gap-3">
                            <div class="p-3 md:p-4 rounded-xl bg-green-600/20 dark:bg-green-600/20 group-hover:bg-green-400/20 transition-colors">
                                <span class="material-symbols-outlined text-green-600 dark:text-green-400 group-hover:text-green-400 text-2xl md:text-3xl">restaurant</span>
                            </div>
                            <div class="font-semibold text-sm md:text-base text-slate-900 dark:text-white">Refeições</div>
                            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400">Reservas</p>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($temFrota): ?>
                    <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="glass-panel rounded-2xl p-4 md:p-5 text-center transition-all duration-300 hover:scale-105 hover:shadow-lg border-2 border-blue-600 dark:border-blue-600 hover:border-blue-400 dark:hover:border-blue-400 group">
                        <div class="flex flex-col items-center gap-2 md:gap-3">
                            <div class="p-3 md:p-4 rounded-xl bg-blue-600/20 dark:bg-blue-600/20 group-hover:bg-blue-400/20 transition-colors">
                                <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 group-hover:text-blue-400 text-2xl md:text-3xl">directions_car</span>
                            </div>
                            <div class="font-semibold text-sm md:text-base text-slate-900 dark:text-white">Frota</div>
                            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400">Veículos</p>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($temEstoque): ?>
                    <a href="<?= MenuPermissaoService::ajustarUrl('/estoque/dashboard.php') ?>" class="glass-panel rounded-2xl p-4 md:p-5 text-center transition-all duration-300 hover:scale-105 hover:shadow-lg border-2 border-orange-600 dark:border-orange-600 hover:border-orange-400 dark:hover:border-orange-400 group">
                        <div class="flex flex-col items-center gap-2 md:gap-3">
                            <div class="p-3 md:p-4 rounded-xl bg-orange-600/20 dark:bg-orange-600/20 group-hover:bg-orange-400/20 transition-colors">
                                <span class="material-symbols-outlined text-orange-600 dark:text-orange-400 group-hover:text-orange-400 text-2xl md:text-3xl">inventory</span>
                            </div>
                            <div class="font-semibold text-sm md:text-base text-slate-900 dark:text-white">Estoque</div>
                            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400">Materiais</p>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modals Bootstrap (mantidos para compatibilidade) -->
    <!-- Modal de Detalhes de Presença -->
    <div class="modal fade" id="modalDetalhesPresenca" tabindex="-1" aria-labelledby="modalDetalhesPresencaLabel">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-white dark:bg-[#1e293b] border border-white/10">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalDetalhesPresencaLabel">
                        <span class="material-symbols-outlined me-2">calendar_month</span>Detalhes de Presença
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body text-black dark:text-white" id="modalDetalhesPresencaBody" style="color: #000000 !important;">
                    <style>
                        #modalDetalhesPresencaBody { color: #000000 !important; }
                        .dark #modalDetalhesPresencaBody { color: #ffffff !important; }
                        #modalDetalhesPresencaBody .card-body { color: #000000 !important; }
                        .dark #modalDetalhesPresencaBody .card-body { color: #ffffff !important; }
                        #modalDetalhesPresencaBody p,
                        #modalDetalhesPresencaBody span:not(.badge),
                        #modalDetalhesPresencaBody strong { color: #000000 !important; }
                        .dark #modalDetalhesPresencaBody p,
                        .dark #modalDetalhesPresencaBody span:not(.badge),
                        .dark #modalDetalhesPresencaBody strong { color: #ffffff !important; }
                    </style>
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-slate-50 dark:bg-[#1e293b]">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="modalDetalhesReservas" tabindex="-1" aria-labelledby="modalDetalhesReservasLabel">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-white dark:bg-[#1e293b] border border-white/10">
                <div class="modal-header bg-green-500 text-white">
                    <h5 class="modal-title" id="modalDetalhesReservasLabel">
                        <span class="material-symbols-outlined me-2">event</span>Reservas do Dia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body text-slate-900 dark:text-white" id="modalDetalhesReservasBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-green-500" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-slate-50 dark:bg-[#1e293b]">
                    <a href="<?= MenuPermissaoService::ajustarUrl('/reservas/almoco.php') ?>" class="btn btn-success">
                        <span class="material-symbols-outlined me-1" style="font-size: 18px;">add_circle</span>Ir para Reservas
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalPrimeiraConfiguracaoNotificacoes" tabindex="-1" aria-labelledby="modalPrimeiraConfiguracaoNotificacoesLabel" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-white dark:bg-[#1e293b] border border-white/10">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalPrimeiraConfiguracaoNotificacoesLabel">
                        <span class="material-symbols-outlined me-2">notifications</span>Configure suas Notificações
                    </h5>
                </div>
                <div class="modal-body text-slate-900 dark:text-white">
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
                        <strong>Como funciona:</strong> Se você tiver telefone cadastrado, receberá por WhatsApp. Caso contrário, receberá por email.
                    </div>
                </div>
                <div class="modal-footer bg-slate-50 dark:bg-[#1e293b]">
                    <button type="button" class="btn btn-primary" onclick="salvarConfiguracaoInicial()">
                        <span class="material-symbols-outlined me-2" style="font-size: 18px;">check_circle</span>Salvar e Continuar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const usuarioId = <?= $usuarioId ?>;
        const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
        
        // Controle de meses
        let mesAtualAlmoco = <?= $mesAtual - 1 ?>;
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
            
            const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
            diasSemana.forEach(dia => {
                html += `<div class="calendar-day day-header">${dia}</div>`;
            });
            
            const primeiroDia = new Date(anoAtualAlmoco, mesAtualAlmoco, 1);
            const ultimoDia = new Date(anoAtualAlmoco, mesAtualAlmoco + 1, 0);
            const diasNoMes = ultimoDia.getDate();
            const diaSemanaInicio = primeiroDia.getDay();
            
            for (let i = 0; i < diaSemanaInicio; i++) {
                html += '<div class="calendar-day day-empty"></div>';
            }
            
            for (let dia = 1; dia <= diasNoMes; dia++) {
                const dataCompleta = `${anoAtualAlmoco}-${String(mesAtualAlmoco + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
                const dataFormatada = `${String(dia).padStart(2, '0')}/${String(mesAtualAlmoco + 1).padStart(2, '0')}/${anoAtualAlmoco}`;
                const dadosDia = dados[dataCompleta] || {};
                
                let classes = 'calendar-day';
                
                // Nova lógica de cores:
                // - Verde: somente reserva própria
                // - Verde + Azul (metade): reserva própria E adicional
                // - Azul: somente reserva adicional
                // - Vermelho: sem reserva
                if (dadosDia.tem_reserva && dadosDia.tem_dependente) {
                    // Tem reserva própria E adicional → metade verde, metade azul
                    classes += ' day-reserva-adicional';
                } else if (dadosDia.tem_reserva) {
                    // Somente reserva própria → verde
                    classes += ' day-reserva';
                } else if (dadosDia.tem_dependente) {
                    // Somente reserva adicional → azul
                    classes += ' day-apenas-adicional';
                } else {
                    // Sem reserva → vermelho
                    classes += ' day-sem-reserva';
                }
                
                html += `<div class="${classes}" title="${dataFormatada}" data-data="${dataCompleta}" onclick="abrirDetalhesReservas('${dataCompleta}')">${dia}</div>`;
            }
            
            container.innerHTML = html;
        }
        
        function abrirDetalhesReservas(data) {
            const modal = new bootstrap.Modal(document.getElementById('modalDetalhesReservas'));
            modal.show();
            
            $('#modalDetalhesReservasBody').html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-green-500" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="mt-2 text-muted">Carregando reservas...</p>
                </div>
            `);
            
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
                                ${response.mensagem || 'Erro ao carregar detalhes'}
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#modalDetalhesReservasBody').html(`
                        <div class="alert alert-danger">
                            Erro de conexão ao buscar detalhes
                        </div>
                    `);
                }
            });
        }
        
        function renderizarDetalhesReservas(data) {
            let html = '';
            html += `
                <div class="text-center mb-4">
                    <h5 class="mb-1">${data.dia_semana}</h5>
                    <span class="badge bg-success fs-6">${data.data_formatada}</span>
                </div>
            `;
            
            if (!data.tem_reservas) {
                html += `
                    <div class="alert alert-warning text-center">
                        Nenhuma reserva para este dia
                    </div>
                `;
            } else {
                if (data.reserva_propria) {
                    html += `
                        <div class="card mb-3 border-success">
                            <div class="card-header bg-success text-white py-2">
                                Reserva Própria
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
                
                if (data.reservas_adicionais && data.reservas_adicionais.length > 0) {
                    html += `
                        <div class="card border-info">
                            <div class="card-header bg-info text-white py-2">
                                Reservas Adicionais (${data.total_adicionais})
                            </div>
                            <ul class="list-group list-group-flush">
                    `;
                    
                    data.reservas_adicionais.forEach(function(reserva) {
                        const valorClass = reserva.valor > 0 ? 'text-danger' : 'text-success';
                        html += `
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <span>${reserva.nome}</span>
                                <span class="fw-bold ${valorClass}">${reserva.valor_formatado}</span>
                            </li>
                        `;
                    });
                    
                    html += `</ul></div>`;
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
            
            const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
            diasSemana.forEach(dia => {
                html += `<div class="calendar-day day-header">${dia}</div>`;
            });
            
            const primeiroDia = new Date(anoAtualCulto, mesAtualCulto, 1);
            const ultimoDia = new Date(anoAtualCulto, mesAtualCulto + 1, 0);
            const diasNoMes = ultimoDia.getDate();
            const diaSemanaInicio = primeiroDia.getDay();
            
            for (let i = 0; i < diaSemanaInicio; i++) {
                html += '<div class="calendar-day day-empty"></div>';
            }
            
            for (let dia = 1; dia <= diasNoMes; dia++) {
                const dataCompleta = `${anoAtualCulto}-${String(mesAtualCulto + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
                const dataFormatada = `${String(dia).padStart(2, '0')}/${String(mesAtualCulto + 1).padStart(2, '0')}/${anoAtualCulto}`;
                const dadosDia = dados[dataCompleta] || {};
                
                let classes = 'calendar-day';
                
                switch(dadosDia.status) {
                    case 'presente': classes += ' day-presente'; break;
                    case 'atrasado': classes += ' day-atrasado'; break;
                    case 'falta': classes += ' day-falta'; break;
                    case 'justificativa_aceita': classes += ' day-justificativa-aceita'; break;
                    case 'justificativa_pendente': classes += ' day-justificativa-pendente'; break;
                    case 'justificativa_rejeitada': classes += ' day-justificativa-rejeitada'; break;
                    case 'sem_culto': classes += ' day-sem-culto'; break;
                    case 'nao_culto': classes += ' day-nao-culto'; break;
                    default: classes += ' day-empty';
                }
                
                // Adicionar onclick apenas para dias clicáveis (não vazios, não "sem_culto", não "nao_culto")
                if (dadosDia.status && dadosDia.status !== 'sem_culto' && dadosDia.status !== 'nao_culto') {
                    html += `<div class="${classes}" title="${dataFormatada}" data-data="${dataCompleta}" onclick="abrirDetalhesPresenca('${dataCompleta}')" style="cursor: pointer;">${dia}</div>`;
                } else {
                    html += `<div class="${classes}" title="${dataFormatada}">${dia}</div>`;
                }
            }
            
            container.innerHTML = html;
        }
        
        function abrirDetalhesPresenca(data) {
            const modal = new bootstrap.Modal(document.getElementById('modalDetalhesPresenca'));
            modal.show();
            
            $('#modalDetalhesPresencaBody').html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="mt-2 text-muted">Carregando informações...</p>
                </div>
            `);
            
            $.ajax({
                url: 'api/calendario/detalhes_presenca_dia.php',
                method: 'GET',
                data: { data: data },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        renderizarDetalhesPresenca(response.detalhes);
                    } else {
                        $('#modalDetalhesPresencaBody').html(`
                            <div class="alert alert-danger">
                                ${response.mensagem || 'Erro ao carregar detalhes'}
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#modalDetalhesPresencaBody').html(`
                        <div class="alert alert-danger">
                            Erro de conexão ao buscar detalhes
                        </div>
                    `);
                }
            });
        }
        
        function renderizarDetalhesPresenca(detalhes) {
            let html = '';
            
            // Cabeçalho com data
            html += `
                <div class="text-center mb-4">
                    <h5 class="mb-1 text-black dark:text-white">${detalhes.dia_semana}</h5>
                    <span class="badge bg-primary fs-6">${detalhes.data_formatada}</span>
                </div>
            `;
            
            // Informações sobre o dia
            if (!detalhes.eh_dia_culto) {
                html += `
                    <div class="alert alert-info text-center">
                        <span class="material-symbols-outlined me-2">event_busy</span>
                        Este não é um dia de culto configurado.
                    </div>
                `;
            } else if (!detalhes.houve_culto) {
                html += `
                    <div class="alert alert-warning text-center">
                        <span class="material-symbols-outlined me-2">event_available</span>
                        Não houve culto neste dia.
                    </div>
                `;
            } else {
                // Card de Presença
                if (detalhes.presenca) {
                    const statusConfig = {
                        'presente': { bg: 'bg-success', icon: 'check_circle', label: 'Presente', text: 'text-white' },
                        'atrasado': { bg: 'bg-primary', icon: 'schedule', label: 'Atrasado', text: 'text-white' },
                        'falta': { bg: 'bg-danger', icon: 'cancel', label: 'Falta', text: 'text-white' }
                    };
                    const status = statusConfig[detalhes.presenca.status] || statusConfig['falta'];
                    
                    html += `
                        <div class="card mb-3 border-0 shadow-sm">
                            <div class="card-header ${status.bg} ${status.text} py-2">
                                <div class="d-flex align-items-center">
                                    <span class="material-symbols-outlined me-2">${status.icon}</span>
                                    <strong>${status.label}</strong>
                                </div>
                            </div>
                            <div class="card-body text-black dark:text-white">
                    `;
                    
                    if (detalhes.presenca.horario) {
                        const horarioFormatado = detalhes.presenca.horario ? new Date('2000-01-01 ' + detalhes.presenca.horario).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '-';
                        html += `
                            <div class="mb-2">
                                <strong class="text-black dark:text-white">Horário de confirmação:</strong>
                                <span class="text-black dark:text-white">${horarioFormatado}</span>
                            </div>
                        `;
                    }
                    
                    if (detalhes.presenca.tipo_confirmacao) {
                        const tipoLabel = {
                            'facial': 'Reconhecimento Facial',
                            'manual': 'Confirmação Manual',
                            'sistema': 'Sistema'
                        }[detalhes.presenca.tipo_confirmacao] || detalhes.presenca.tipo_confirmacao;
                        
                        html += `
                            <div>
                                <strong class="text-black dark:text-white">Tipo de confirmação:</strong>
                                <span class="text-black dark:text-white">${tipoLabel}</span>
                            </div>
                        `;
                    }
                    
                    html += `</div></div>`;
                }
                
                // Card de Justificativa
                if (detalhes.justificativa) {
                    const statusJustConfig = {
                        'aprovada': { bg: 'bg-success', icon: 'check_circle', label: 'Justificativa Aprovada' },
                        'pendente': { bg: 'bg-warning', icon: 'pending', label: 'Justificativa Pendente' },
                        'rejeitada': { bg: 'bg-danger', icon: 'cancel', label: 'Justificativa Rejeitada' }
                    };
                    const statusJust = statusJustConfig[detalhes.justificativa.status] || statusJustConfig['pendente'];
                    
                    html += `
                        <div class="card border-0 shadow-sm">
                            <div class="card-header ${statusJust.bg} text-white py-2">
                                <div class="d-flex align-items-center">
                                    <span class="material-symbols-outlined me-2">${statusJust.icon}</span>
                                    <strong>${statusJust.label}</strong>
                                </div>
                            </div>
                            <div class="card-body text-black dark:text-white">
                                <div class="mb-3">
                                    <strong class="text-black dark:text-white">Motivo:</strong>
                                    <p class="text-black dark:text-white mb-0">${detalhes.justificativa.motivo || 'Não informado'}</p>
                                </div>
                    `;
                    
                    if (detalhes.justificativa.observacoes) {
                        html += `
                            <div class="mb-3">
                                <strong class="text-black dark:text-white">Observações:</strong>
                                <p class="text-black dark:text-white mb-0">${detalhes.justificativa.observacoes}</p>
                            </div>
                        `;
                    }
                    
                    if (detalhes.justificativa.observacoes_admin) {
                        html += `
                            <div class="mb-3">
                                <strong class="text-black dark:text-white">Observações do Administrador:</strong>
                                <p class="text-black dark:text-white mb-0">${detalhes.justificativa.observacoes_admin}</p>
                            </div>
                        `;
                    }
                    
                    if (detalhes.justificativa.data_aprovacao) {
                        const dataAprovacao = new Date(detalhes.justificativa.data_aprovacao).toLocaleDateString('pt-BR');
                        html += `
                            <div>
                                <strong class="text-black dark:text-white">Data de aprovação:</strong>
                                <span class="text-black dark:text-white">${dataAprovacao}</span>
                            </div>
                        `;
                    }
                    
                    html += `</div></div>`;
                }
                
                // Se não tem presença nem justificativa, mas é dia de culto
                if (!detalhes.presenca && !detalhes.justificativa && detalhes.status_geral === 'falta') {
                    html += `
                        <div class="alert alert-danger text-center">
                            <span class="material-symbols-outlined me-2">cancel</span>
                            <strong>Falta registrada</strong>
                            <p class="mb-0 mt-2">Você não compareceu ao culto neste dia.</p>
                        </div>
                    `;
                }
            }
            
            $('#modalDetalhesPresencaBody').html(html);
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
            const ctx = document.getElementById('graficoPresenca');
            if (!ctx) return;
            
            if (graficoPresenca) {
                graficoPresenca.destroy();
            }
            
            if (estatisticas.total_dias_culto === 0) {
                ctx.parentElement.innerHTML = `
                    <div class="text-center text-slate-400">
                        <span class="material-symbols-outlined text-4xl mb-2">bar_chart</span>
                        <p class="text-sm">Sem dados</p>
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
            
            const cores = ['#22c55e', '#3b82f6', '#ef4444', '#eab308'];
            const labels = ['Presente', 'Atraso', 'Falta', 'Just.'];
            
            // Calcular porcentagem de presença (presentes + atrasados + justificativas aceitas)
            // Justificativas aceitas contam como presença
            // Usar valores absolutos para evitar erros de arredondamento
            const totalPresencas = estatisticas.presentes + estatisticas.atrasados + estatisticas.justificativas;
            const percentualPresenca = estatisticas.total_dias_culto > 0 
                ? Math.round((totalPresencas / estatisticas.total_dias_culto) * 100) 
                : 0;
            
            // Plugin customizado para mostrar texto no centro
            const centerTextPlugin = {
                id: 'centerText',
                afterDraw: function(chart) {
                    const ctx = chart.ctx;
                    const centerX = chart.chartArea.left + (chart.chartArea.right - chart.chartArea.left) / 2;
                    const centerY = chart.chartArea.top + (chart.chartArea.bottom - chart.chartArea.top) / 2;
                    
                    ctx.save();
                    // Texto principal (porcentagem) - sempre preto para melhor contraste
                    ctx.font = 'bold 28px Inter, sans-serif';
                    ctx.fillStyle = '#000000';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(percentualPresenca + '%', centerX, centerY - 10);
                    
                    // Texto secundário (label) - sempre preto para melhor contraste
                    ctx.font = '13px Inter, sans-serif';
                    ctx.fillStyle = '#000000';
                    ctx.fillText('Presença', centerX, centerY + 14);
                    ctx.restore();
                }
            };
            
            graficoPresenca = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: dados,
                        backgroundColor: cores,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
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
                    cutout: '70%',
                    animation: {
                        onComplete: function() {
                            // Garantir que o texto seja atualizado após animação
                            graficoPresenca.update('none');
                        }
                    }
                },
                plugins: [centerTextPlugin]
            });
            
            let legendaHtml = '';
            for (let i = 0; i < labels.length; i++) {
                legendaHtml += `
                    <div class="flex flex-col items-center flex-1">
                        <div class="h-1.5 w-10 md:w-12 rounded-full mb-1.5" style="background-color: ${cores[i]}"></div>
                        <span class="text-xs md:text-sm uppercase text-slate-600 dark:text-slate-400 font-semibold">${labels[i].substring(0,3)}</span>
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
                cache: false,
                data: { 
                    mes: <?= $mesAtual ?>,
                    ano: <?= $anoAtual ?>,
                    _t: Date.now()
                },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        $('#totalConfirmadas').text(data.resumo.total_confirmadas);
                        $('#saldoAtual').text(data.resumo.valor_formatado);
                        
                        const urlReservas = '<?= MenuPermissaoService::ajustarUrl("/reservas/almoco.php") ?>';
                        let statusHtml = '';
                        
                        if (data.resumo.permitir_reserva_atraso == 1) {
                            statusHtml = `
                                <a href="${urlReservas}" class="inline-flex items-center gap-1 md:gap-2 px-3 md:px-4 py-1.5 md:py-2 rounded-lg md:rounded-xl bg-blue-500/10 text-blue-500 hover:bg-blue-500/20 transition-colors text-xs md:text-sm">
                                    <span class="material-symbols-outlined text-xs md:text-sm">arrow_forward</span>
                                    <span class="hidden sm:inline">Ir para Reservas</span>
                                    <span class="sm:hidden">Reservas</span>
                                </a>
                            `;
                        } else if (data.resumo.horario_limite_passado) {
                            statusHtml = `
                                <a href="${urlReservas}" class="inline-flex items-center gap-1 md:gap-2 px-3 md:px-4 py-1.5 md:py-2 rounded-lg md:rounded-xl bg-slate-500/10 text-slate-400 hover:bg-slate-500/20 transition-colors text-xs md:text-sm">
                                    <span class="material-symbols-outlined text-xs md:text-sm">schedule</span>
                                    <span class="hidden sm:inline">Horário Limite Atingido</span>
                                    <span class="sm:hidden">Limite</span>
                                </a>
                            `;
                        } else {
                            statusHtml = `
                                <a href="${urlReservas}" class="inline-flex items-center gap-1 md:gap-2 px-3 md:px-4 py-1.5 md:py-2 rounded-lg md:rounded-xl bg-green-500/10 text-green-500 hover:bg-green-500/20 transition-colors text-xs md:text-sm">
                                    <span class="material-symbols-outlined text-xs md:text-sm">add_circle</span>
                                    <span class="hidden sm:inline">Fazer Reserva</span>
                                    <span class="sm:hidden">Reservar</span>
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
                data: { status: '' },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        renderizarVeiculos(data.veiculos.slice(0, 5));
                    }
                },
                error: function() {
                    $('#listaVeiculos').html('<div class="text-center py-4 text-slate-400">Não foi possível carregar</div>');
                }
            });
        }
        
        function renderizarVeiculos(veiculos) {
            const container = $('#listaVeiculos');
            
            if (veiculos.length === 0) {
                container.html('<div class="text-center py-4 text-slate-400">Nenhum veículo cadastrado</div>');
                return;
            }
            
            let html = '';
            veiculos.forEach(v => {
                const statusConfig = {
                    'disponivel': { bg: 'bg-green-500/10', text: 'text-green-500', border: 'border-green-500/20', label: 'Disponível' },
                    'em_uso': { bg: 'bg-yellow-500/10', text: 'text-yellow-500', border: 'border-yellow-500/20', label: 'Em Uso' },
                    'manutencao': { bg: 'bg-red-500/10', text: 'text-red-500', border: 'border-red-500/20', label: 'Manutenção' },
                    'inativo': { bg: 'bg-slate-500/10', text: 'text-slate-500', border: 'border-slate-500/20', label: 'Inativo' }
                };
                const status = statusConfig[v.status] || statusConfig['inativo'];
                
                html += `
                    <div class="group flex items-center justify-between p-2 md:p-3 rounded-xl md:rounded-2xl bg-white dark:bg-slate-800/80 border border-slate-100 dark:border-slate-700/50 hover:border-primary/50 transition-all cursor-pointer">
                        <div class="flex items-center gap-2 md:gap-4 flex-1 min-w-0">
                            <div class="size-10 md:size-12 rounded-lg md:rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-slate-600 dark:text-slate-200 text-xl md:text-2xl">directions_car</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-xs md:text-sm font-bold text-black dark:text-white group-hover:text-primary dark:group-hover:text-primary transition-colors truncate">${v.modelo || 'Veículo'}</h3>
                                <p class="text-[10px] md:text-xs text-black dark:text-slate-300 truncate">Placa: ${v.placa}</p>
                            </div>
                        </div>
                        <span class="px-2 md:px-3 py-1 rounded-full ${status.bg} ${status.text} text-[10px] md:text-xs font-medium border ${status.border} shrink-0 ml-2">${status.label}</span>
                    </div>
                `;
            });
            
            container.html(html);
        }
        
        // ═══════════════════════════════════════════════════════════════════
        // CONFIGURAÇÃO DE NOTIFICAÇÕES
        // ═══════════════════════════════════════════════════════════════════
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
        
        setTimeout(verificarConfiguracaoNotificacoes, 1000);
        
        // ═══════════════════════════════════════════════════════════════════
        // BUSCA DE MENUS E MÓDULOS
        // ═══════════════════════════════════════════════════════════════════
        let timeoutBusca = null;
        const campoBusca = document.getElementById('campoBusca');
        const resultadosBusca = document.getElementById('resultadosBusca');
        const conteudoResultadosBusca = document.getElementById('conteudoResultadosBusca');
        
        if (campoBusca) {
            campoBusca.addEventListener('input', function(e) {
                const termo = e.target.value.trim();
                
                // Limpar timeout anterior
                if (timeoutBusca) {
                    clearTimeout(timeoutBusca);
                }
                
                // Se termo muito curto, esconder resultados
                if (termo.length < 2) {
                    resultadosBusca.classList.add('hidden');
                    return;
                }
                
                // Aguardar 300ms antes de buscar (debounce)
                timeoutBusca = setTimeout(() => {
                    buscarMenusModulos(termo);
                }, 300);
            });
            
            // Fechar resultados ao clicar fora
            document.addEventListener('click', function(e) {
                if (!campoBusca.contains(e.target) && !resultadosBusca.contains(e.target)) {
                    resultadosBusca.classList.add('hidden');
                }
            });
            
            // Manter foco no campo ao clicar nos resultados
            resultadosBusca.addEventListener('mousedown', function(e) {
                e.preventDefault();
            });
        }
        
        function buscarMenusModulos(termo) {
            $.ajax({
                url: 'api/buscar_menus_modulos.php',
                method: 'GET',
                data: { termo: termo },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        renderizarResultadosBusca(data.resultados);
                        resultadosBusca.classList.remove('hidden');
                    } else {
                        resultadosBusca.classList.add('hidden');
                    }
                },
                error: function() {
                    resultadosBusca.classList.add('hidden');
                }
            });
        }
        
        function renderizarResultadosBusca(resultados) {
            if (resultados.length === 0) {
                conteudoResultadosBusca.innerHTML = `
                    <div class="p-4 text-center text-slate-500 dark:text-slate-400">
                        <span class="material-symbols-outlined text-2xl mb-2">search_off</span>
                        <p class="text-sm">Nenhum resultado encontrado</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            resultados.forEach(item => {
                html += `
                    <a href="${item.url}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors group">
                        <div class="p-2 rounded-lg bg-primary/10 dark:bg-primary/20 group-hover:bg-primary/20 transition-colors">
                            <span class="material-symbols-outlined text-primary text-lg">${item.icone}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm text-slate-900 dark:text-white group-hover:text-primary transition-colors truncate">${item.nome}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 truncate">${item.descricao || item.categoria}</div>
                        </div>
                        <span class="material-symbols-outlined text-slate-400 text-sm">arrow_forward</span>
                    </a>
                `;
            });
            
            conteudoResultadosBusca.innerHTML = html;
        }
        
        // ═══════════════════════════════════════════════════════════════════
        // CONTROLE DE SIDEBAR MOBILE
        // ═══════════════════════════════════════════════════════════════════
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        }
        
        // Fechar sidebar ao clicar em link no mobile
        document.querySelectorAll('#sidebar a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    toggleSidebar();
                }
            });
        });
        
        // Fechar sidebar ao redimensionar para desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) {
                document.getElementById('sidebar').classList.remove('open');
                document.getElementById('sidebarOverlay').classList.remove('open');
            }
        });
        
        // ═══════════════════════════════════════════════════════════════════
        // GERENCIAMENTO DE TEMA (CLARO/ESCURO)
        // ═══════════════════════════════════════════════════════════════════
        
        let temaAtual = 'dark'; // Padrão é dark neste arquivo
        
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
                        // Se não encontrar, usar tema padrão (dark neste arquivo)
                        aplicarTema('dark');
                    }
                },
                error: function() {
                    // Em caso de erro, usar tema padrão
                    aplicarTema('dark');
                }
            });
        }
        
        function aplicarTema(tema) {
            temaAtual = tema;
            const html = document.documentElement;
            const body = document.body;
            
            if (tema === 'dark') {
                html.setAttribute('data-theme', 'dark');
                html.classList.add('dark');
                body.classList.add('dark');
                $('#iconTheme').text('dark_mode');
                $('#btnToggleTheme').attr('title', 'Alternar para tema claro');
            } else {
                html.setAttribute('data-theme', 'light');
                html.classList.remove('dark');
                body.classList.remove('dark');
                $('#iconTheme').text('light_mode');
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