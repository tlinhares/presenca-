<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  MÓDULO DE FROTA - DASHBOARD                                  ║
// ║  Menu: frota_dashboard                                        ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('frota_dashboard');

$isAdmin = MenuPermissaoService::isAdmin();
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuarioId = $_SESSION['usuario_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frota - Dashboard</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary-container": "#198374",
                        "on-secondary": "#ffffff",
                        "primary": "#00685c",
                        "secondary": "#795900",
                        "on-error-container": "#93000a",
                        "on-surface-variant": "#3d4946",
                        "primary-fixed": "#97f3e2",
                        "tertiary-fixed-dim": "#a2c9ff",
                        "tertiary-container": "#0077ce",
                        "surface-dim": "#d6dade",
                        "on-primary-fixed-variant": "#005047",
                        "tertiary-fixed": "#d3e4ff",
                        "on-error": "#ffffff",
                        "surface-variant": "#dfe3e7",
                        "background": "#f1f5f9",
                        "on-secondary-fixed-variant": "#5c4300",
                        "primary-fixed-dim": "#7ad7c6",
                        "on-primary": "#ffffff",
                        "on-tertiary-fixed": "#001c38",
                        "on-secondary-fixed": "#261a00",
                        "outline-variant": "#bcc9c5",
                        "surface-tint": "#006b5e",
                        "secondary-fixed": "#ffdfa0",
                        "error": "#ba1a1a",
                        "surface": "#f1f5f9",
                        "tertiary": "#005ea4",
                        "surface-container-lowest": "#ffffff",
                        "on-tertiary": "#ffffff",
                        "on-primary-fixed": "#00201b",
                        "on-tertiary-fixed-variant": "#004881",
                        "error-container": "#ffdad6",
                        "on-surface": "#0f172a",
                        "on-background": "#0f172a",
                        "inverse-surface": "#2c3134",
                        "surface-bright": "#f8fafc",
                        "inverse-on-surface": "#edf1f5",
                        "surface-container-high": "#e2e8f0",
                        "on-tertiary-container": "#fdfcff",
                        "on-primary-container": "#f4fffb",
                        "secondary-container": "#fec330",
                        "outline": "#6d7a77",
                        "surface-container": "#e2e8f0",
                        "secondary-fixed-dim": "#f8bd2a",
                        "inverse-primary": "#7ad7c6",
                        "surface-container-low": "#f8fafc",
                        "on-secondary-container": "#6f5100",
                        "surface-container-highest": "#cbd5e1"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Inter", "sans-serif"],
                        "label": ["Inter", "sans-serif"]
                    },
                    borderRadius: { "DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem" },
                },
            },
        }
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, h4, h5, .brand-font { font-family: 'Manrope', sans-serif; }
    </style>
</head>
<body class="bg-background text-on-surface min-h-screen">

    <!-- Sidebar -->
    <aside class="hidden lg:flex fixed left-0 top-0 h-full flex-col p-4 z-40 w-20 hover:w-64 transition-all duration-300 group/sidebar bg-white/90 backdrop-blur-xl border-r border-slate-200 hover:shadow-2xl	">
        <div class="flex items-center gap-4 mb-10 px-2">
            <div class="w-10 h-10 bg-primary flex items-center justify-center rounded-xl shrink-0">
                <span class="material-symbols-outlined text-white" style="font-variation-settings: 'FILL' 1;">local_shipping</span>
            </div>
            <div class="opacity-0 group-hover/sidebar:opacity-100 transition-opacity whitespace-nowrap overflow-hidden">
                <span class="text-lg font-black text-[#00685c]">Frota AOM</span>
                <p class="text-[10px] uppercase

 font-bold text-slate-500 leading-none">Painel de Controle</p>
            </div>
        </div>
        <nav class="flex flex-col gap-2 flex-grow">
            <a class="flex items-center gap-4 p-3 bg-[#00897B] text-white rounded-xl shadow-lg shadow-[#00897B]/20 transition-all brightness-110" href="#">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="opacity-0 group-hover/sidebar:opacity-100 transition-opacity whitespace-nowrap font-label font-medium">Dashboard</span>
            </a>
            <?php if (MenuPermissaoService::podeAcessar('frota_historico')): ?>
            <a class="flex items-center gap-4 p-3 text-slate-600 hover:bg-slate-100 rounded-xl transition-all group-hover/sidebar:translate-x-1" href="<?= MenuPermissaoService::ajustarUrl('/frota/historico.php') ?>">
                <span class="material-symbols-outlined">history</span>
                <span class="opacity-0 group-hover/sidebar:opacity-100 transition-opacity whitespace-nowrap font-label font-medium">Meu Histórico</span>
            </a>
            <?php endif; ?>
            <?php if (MenuPermissaoService::podeAcessar('frota_admin_veiculos')): ?>
            <a class="flex items-center gap-4 p-3 text-slate-600 hover:bg-slate-100 rounded-xl transition-all group-hover/sidebar:translate-x-1" href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/veiculos.php') ?>">
                <span class="material-symbols-outlined">local_shipping</span>
                <span class="opacity-0 group-hover/sidebar:opacity-100 transition-opacity whitespace-nowrap font-label font-medium">Veículos</span>
            </a>
            <?php endif; ?>
            <?php if (MenuPermissaoService::podeAcessar('frota_relatorios')): ?>
            <a class="flex items-center gap-4 p-3 text-slate-600 hover:bg-slate-100 rounded-xl transition-all group-hover/sidebar:translate-x-1" href="<?= MenuPermissaoService::ajustarUrl('/frota/relatorios.php') ?>">
                <span class="material-symbols-outlined">analytics</span>
                <span class="opacity-0 group-hover/sidebar:opacity-100 transition-opacity whitespace-nowrap font-label font-medium">Relatórios</span>
            </a>
            <?php endif; ?>
            <?php if (MenuPermissaoService::podeAcessar('frota_admin_manutencoes')): ?>
            <a class="flex items-center gap-4 p-3 text-slate-600 hover:bg-slate-100 rounded-xl transition-all group-hover/sidebar:translate-x-1" href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/manutencoes.php') ?>">
                <span class="material-symbols-outlined">build</span>
                <span class="opacity-0 group-hover/sidebar:opacity-100 transition-opacity whitespace-nowrap font-label font-medium">Manutenções</span>
            </a>
            <?php endif; ?>
            <?php if (MenuPermissaoService::podeAcessar('frota_departamentos')): ?>
            <a class="flex items-center gap-4 p-3 text-slate-600 hover:bg-slate-100 rounded-xl transition-all group-hover/sidebar:translate-x-1" href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/departamentos.php') ?>">
                <span class="material-symbols-outlined">apartment</span>
                <span class="opacity-0 group-hover/sidebar:opacity-100 transition-opacity whitespace-nowrap font-label font-medium">Departamentos</span>
            </a>
            <?php endif; ?>
            <?php if (MenuPermissaoService::podeAcessar('frota_configuracoes')): ?>
            <a class="flex items-center gap-4 p-3 text-slate-600 hover:bg-slate-100 rounded-xl transition-all group-hover/sidebar:translate-x-1" href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/configuracoes.php') ?>">
                <span class="material-symbols-outlined">settings</span>
                <span class="opacity-0 group-hover/sidebar:opacity-100 transition-opacity whitespace-nowrap font-label font-medium">Configurações</span>
            </a>
            <?php endif; ?>
        </nav>
        <div class="mt-auto border-t border-slate-200 pt-4 flex flex-col gap-2">
            <a class="flex items-center gap-4 p-3 text-slate-600 hover:bg-slate-100 rounded-xl transition-all" href="<?= MenuPermissaoService::ajustarUrl('/resumo.php') ?>">
                <span class="material-symbols-outlined">arrow_back</span>
                <span class="opacity-0 group-hover/sidebar:opacity-100 transition-opacity whitespace-nowrap font-label font-medium">Voltar ao Início</span>
            </a>
            <a class="flex items-center gap-4 p-3 text-error hover:bg-red-50 rounded-xl transition-all" href="<?= MenuPermissaoService::ajustarUrl('/logout.php') ?>">
                <span class="material-symbols-outlined">logout</span>
                <span class="opacity-0 group-hover/sidebar:opacity-100 transition-opacity whitespace-nowrap font-label font-medium">Sair</span>
            </a>
        </div>
    </aside>

    <!-- Top Navigation Bar -->
    <header class="flex justify-between items-center w-full px-6 lg:pl-24 py-3 sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div class="flex items-center gap-8">
            <span class="text-xl font-bold tracking-tight text-[#00685c]">Frota AOM</span>
            <nav class="hidden md:flex items-center gap-6">
                <a class="text-[#00685c] font-bold border-b-2 border-[#00685c] pb-1 font-label text-sm" href="#">Dashboard</a>
                <?php if (MenuPermissaoService::podeAcessar('frota_historico')): ?>
                <a class="text-slate-600 font-medium hover:text-[#00897B] transition-colors duration-200 font-label text-sm" href="<?= MenuPermissaoService::ajustarUrl('/frota/historico.php') ?>">Histórico</a>
                <?php endif; ?>
                <?php if (MenuPermissaoService::podeAcessar('frota_admin_manutencoes')): ?>
                <a class="text-slate-600 font-medium hover:text-[#00897B] transition-colors duration-200 font-label text-sm" href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/manutencoes.php') ?>">Manutenções</a>
                <?php endif; ?>
                <?php if (MenuPermissaoService::podeAcessar('frota_relatorios')): ?>
                <a class="text-slate-600 font-medium hover:text-[#00897B] transition-colors duration-200 font-label text-sm" href="<?= MenuPermissaoService::ajustarUrl('/frota/relatorios.php') ?>">Relatórios</a>
                <?php endif; ?>
                <?php if (MenuPermissaoService::podeAcessar('frota_departamentos')): ?>
                <a class="text-slate-600 font-medium hover:text-[#00897B] transition-colors duration-200 font-label text-sm" href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/departamentos.php') ?>">Departamentos</a>
                <?php endif; ?>
                <?php if (MenuPermissaoService::podeAcessar('frota_configuracoes')): ?>
                <a class="text-slate-600 font-medium hover:text-[#00897B] transition-colors duration-200 font-label text-sm" href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/configuracoes.php') ?>">Configurações</a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="flex items-center gap-4">
            <button class="p-2 hover:bg-slate-100 transition-colors duration-200 rounded-full">
                <span class="material-symbols-outlined text-slate-600">notifications</span>
            </button>
            <div class="w-8 h-8 rounded-full overflow-hidden border border-slate-300 bg-primary flex items-center justify-center text-white text-xs font-bold">
                <?= mb_substr($nomeUsuario, 0, 1) ?>
            </div>
        </div>
    </header>

    <!-- Mobile Nav -->
    <div class="md:hidden px-4 py-3 flex gap-2 overflow-x-auto bg-white border-b border-slate-200">
        <a href="<?= MenuPermissaoService::ajustarUrl('/resumo.php') ?>" class="flex-shrink-0 px-3 py-1.5 bg-white text-slate-700 font-medium rounded-xl text-xs border border-slate-200">Voltar</a>
        <?php if (MenuPermissaoService::podeAcessar('frota_historico')): ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/frota/historico.php') ?>" class="flex-shrink-0 px-3 py-1.5 bg-white text-slate-700 font-medium rounded-xl text-xs border border-slate-200">Histórico</a>
        <?php endif; ?>
        <?php if (MenuPermissaoService::podeAcessar('frota_relatorios')): ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/frota/relatorios.php') ?>" class="flex-shrink-0 px-3 py-1.5 bg-white text-slate-700 font-medium rounded-xl text-xs border border-slate-200">Relatórios</a>
        <?php endif; ?>
        <?php if (MenuPermissaoService::podeAcessar('frota_admin_veiculos')): ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/veiculos.php') ?>" class="flex-shrink-0 px-3 py-1.5 bg-primary text-on-primary font-medium rounded-xl text-xs">Veículos</a>
        <?php endif; ?>
        <?php if (MenuPermissaoService::podeAcessar('frota_admin_manutencoes')): ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/manutencoes.php') ?>" class="flex-shrink-0 px-3 py-1.5 bg-white text-slate-700 font-medium rounded-xl text-xs border border-slate-200">Manutenções</a>
        <?php endif; ?>
        <?php if (MenuPermissaoService::podeAcessar('frota_departamentos')): ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/departamentos.php') ?>" class="flex-shrink-0 px-3 py-1.5 bg-white text-slate-700 font-medium rounded-xl text-xs border border-slate-200">Departamentos</a>
        <?php endif; ?>
        <?php if (MenuPermissaoService::podeAcessar('frota_configuracoes')): ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/configuracoes.php') ?>" class="flex-shrink-0 px-3 py-1.5 bg-white text-slate-700 font-medium rounded-xl text-xs border border-slate-200">Configurações</a>
        <?php endif; ?>
    </div>

    <main class="max-w-[1440px] mx-auto p-6 lg:p-10 lg:pl-24">

        <!-- Module Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-6">
            <div>
                <div class="flex items-center gap-2 text-primary mb-2">
                    <span class="material-symbols-outlined">local_shipping</span>
                    <span class="font-label font-semibold text-sm tracking-wider uppercase">Módulo de Frota</span>
                </div>
                <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight mb-1 font-headline">Controle de Veículos</h1>
                <p class="text-slate-600 font-body">Gestão centralizada de ativos e disponibilidade em tempo real.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= MenuPermissaoService::ajustarUrl('/resumo.php') ?>" class="flex items-center gap-2 px-4 py-2 bg-white text-slate-700 font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all shadow-sm text-sm">
                    <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                    <span>Voltar</span>
                </a>
                <?php if (MenuPermissaoService::podeAcessar('frota_historico')): ?>
                <a href="<?= MenuPermissaoService::ajustarUrl('/frota/historico.php') ?>" class="flex items-center gap-2 px-4 py-2 bg-white text-slate-700 font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all shadow-sm text-sm">
                    <span>Meu Histórico</span>
                </a>
                <?php endif; ?>
                <?php if (MenuPermissaoService::podeAcessar('frota_relatorios')): ?>
                <a href="<?= MenuPermissaoService::ajustarUrl('/frota/relatorios.php') ?>" class="flex items-center gap-2 px-4 py-2 bg-white text-slate-700 font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all shadow-sm text-sm">
                    <span>Relatórios</span>
                </a>
                <?php endif; ?>
                <?php if (MenuPermissaoService::podeAcessar('frota_admin_veiculos')): ?>
                <a href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/veiculos.php') ?>" class="flex items-center gap-2 px-4 py-2 bg-primary text-on-primary font-semibold rounded-xl shadow-lg shadow-primary/20 hover:brightness-110 transition-all text-sm">
                    <span>Veículos</span>
                </a>
                <?php endif; ?>
                <?php if (MenuPermissaoService::podeAcessar('frota_admin_manutencoes')): ?>
                <a href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/manutencoes.php') ?>" class="flex items-center gap-2 px-4 py-2 bg-white text-slate-700 font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all shadow-sm text-sm">
                    <span>Manutenções</span>
                </a>
                <?php endif; ?>
                <?php if (MenuPermissaoService::podeAcessar('frota_departamentos')): ?>
                <a href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/departamentos.php') ?>" class="flex items-center gap-2 px-4 py-2 bg-white text-slate-700 font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all shadow-sm text-sm">
                    <span class="material-symbols-outlined text-[18px]">apartment</span>
                    <span>Departamentos</span>
                </a>
                <?php endif; ?>
                <?php if (MenuPermissaoService::podeAcessar('frota_configuracoes')): ?>
                <a href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/configuracoes.php') ?>" class="flex items-center gap-2 px-4 py-2 bg-white text-slate-700 font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all shadow-sm text-sm">
                    <span class="material-symbols-outlined text-[18px]">settings</span>
                    <span>Configurações</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status Overview Cards -->
        <section class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-12" id="estatisticas">
            <div class="bg-white p-6 rounded-xl flex flex-col justify-between border border-slate-200 border-l-4 border-l-primary shadow-sm hover:shadow-md transition-shadow">
                <div>
                    <p class="text-slate-500 font-label text-sm font-semibold mb-1">Total Veículos</p>
                    <h3 class="text-4xl font-extrabold text-slate-900 font-headline" id="totalVeiculos">-</h3>
                </div>
                <div class="mt-4 flex items-center text-primary-container text-xs font-bold">
                    <span class="material-symbols-outlined text-sm mr-1">analytics</span>
                    Ativos Totais
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl flex flex-col justify-between border border-slate-200 border-l-4 border-l-primary-fixed shadow-sm hover:shadow-md transition-shadow">
                <div>
                    <p class="text-slate-500 font-label text-sm font-semibold mb-1">Disponíveis</p>
                    <h3 class="text-4xl font-extrabold text-slate-900 font-headline" id="disponiveis">-</h3>
                </div>
                <div class="mt-4 flex items-center text-primary-container text-xs font-bold">
                    <span class="material-symbols-outlined text-sm mr-1">check_circle</span>
                    Pronto para uso
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl flex flex-col justify-between border border-slate-200 border-l-4 border-l-secondary-container shadow-sm hover:shadow-md transition-shadow">
                <div>
                    <p class="text-slate-500 font-label text-sm font-semibold mb-1">Em Uso</p>
                    <h3 class="text-4xl font-extrabold text-slate-900 font-headline" id="emUso">-</h3>
                </div>
                <div class="mt-4 flex items-center text-secondary text-xs font-bold">
                    <span class="material-symbols-outlined text-sm mr-1">route</span>
                    Em trânsito
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl flex flex-col justify-between border border-slate-200 border-l-4 border-l-error shadow-sm hover:shadow-md transition-shadow">
                <div>
                    <p class="text-slate-500 font-label text-sm font-semibold mb-1">Manutenção</p>
                    <h3 class="text-4xl font-extrabold text-slate-900 font-headline" id="manutencao">-</h3>
                </div>
                <div class="mt-4 flex items-center text-error text-xs font-bold">
                    <span class="material-symbols-outlined text-sm mr-1">build</span>
                    Na oficina
                </div>
            </div>
        </section>

        <!-- Meu veículo em uso -->
        <div id="meuVeiculoContainer" style="display: none;" class="mb-8">
            <div class="bg-[#fff9e6] border-2 border-secondary-container rounded-xl p-6 flex flex-col md:flex-row items-start md:items-center gap-4">
                <div class="flex items-center gap-4 flex-1 min-w-0">
                    <div class="w-12 h-12 bg-secondary-container rounded-xl flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-on-secondary-container text-2xl">warning</span>
                    </div>
                    <div class="min-w-0">
                        <h5 class="text-lg font-bold text-slate-900 font-headline">Você está com um veículo</h5>
                        <p class="text-slate-600 text-sm truncate" id="meuVeiculoInfo">-</p>
                    </div>
                </div>
                <div class="flex gap-2 flex-shrink-0 w-full md:w-auto">
                    <a href="<?= MenuPermissaoService::ajustarUrl('/frota/detalhes.php') ?>" id="btnDetalhes" class="flex-1 md:flex-none flex items-center justify-center gap-2 px-5 py-3 bg-white text-slate-700 font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all text-sm shadow-sm">
                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                        <span>Ver Detalhes</span>
                    </a>
                    <a href="<?= MenuPermissaoService::ajustarUrl('/frota/devolver.php') ?>" id="btnDevolver" class="flex-1 md:flex-none flex items-center justify-center gap-2 px-5 py-3 bg-secondary-container text-on-secondary-container font-bold rounded-xl hover:brightness-110 transition-all text-sm shadow-lg shadow-secondary-container/30">
                        <span class="material-symbols-outlined text-[20px]">keyboard_return</span>
                        <span>Devolver</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Vehicle Grid Section -->
        <section>
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-4">
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-bold font-headline text-slate-900">Veículos Disponíveis</h2>
                    <span class="bg-primary-fixed text-on-primary-fixed px-3 py-1 rounded-full text-xs font-bold" id="badgeContagem">Carregando...</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-slate-600 text-sm font-medium">Filtrar por:</span>
                    <div class="relative">
                        <select id="filtroStatus" class="appearance-none bg-white border border-slate-200 rounded-lg py-2 pl-4 pr-10 text-sm font-medium focus:ring-primary focus:ring-1 cursor-pointer shadow-sm">
                            <option value="">Todos</option>
                            <option value="disponivel" selected>Disponíveis</option>
                            <option value="em_uso">Em Uso</option>
                            <option value="manutencao">Manutenção</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">expand_more</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8" id="listaVeiculos">
                <div class="col-span-full flex flex-col items-center justify-center py-16">
                    <div class="w-10 h-10 border-4 border-primary/30 border-t-primary rounded-full animate-spin mb-4"></div>
                    <p class="text-slate-500 text-sm font-medium">Carregando veículos...</p>
                </div>
            </div>

            <div id="semVeiculos" style="display: none;" class="flex flex-col items-center justify-center py-20 text-center">
                <span class="material-symbols-outlined text-slate-300 text-7xl mb-4">local_shipping</span>
                <h5 class="text-lg font-bold text-slate-900 font-headline mb-1">Nenhum veículo encontrado</h5>
                <p class="text-slate-500 text-sm">Não há veículos cadastrados ou disponíveis no momento.</p>
            </div>
        </section>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const usuarioId = <?= $usuarioId ?>;
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';

        function imgFallback(img) {
            img.onerror = null;
            img.parentElement.innerHTML = '<div class="w-full h-full flex items-center justify-center bg-slate-100"><span class="material-symbols-outlined text-slate-300 text-6xl">local_shipping</span></div>';
        }
        
        $(document).ready(function() {
            carregarDados();
            
            $('#filtroStatus').change(function() {
                carregarVeiculos($(this).val());
            });
        });
        
        function carregarDados() {
            carregarEstatisticas();
            verificarMeuVeiculo();
            carregarVeiculos($('#filtroStatus').val());
        }
        
        function carregarEstatisticas() {
            $.ajax({
                url: baseUrl + '/api/frota/estatisticas.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        $('#totalVeiculos').text(data.estatisticas.total);
                        $('#disponiveis').text(data.estatisticas.disponiveis);
                        $('#emUso').text(data.estatisticas.em_uso);
                        $('#manutencao').text(data.estatisticas.manutencao);
                    }
                }
            });
        }
        
        function verificarMeuVeiculo() {
            $.ajax({
                url: baseUrl + '/api/frota/minha_utilizacao.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok' && data.utilizacao) {
                        const u = data.utilizacao;
                        $('#meuVeiculoInfo').html(
                            `<strong>${u.modelo}</strong> (${u.placa}) - Saída: ${u.data_saida_formatada} - KM: ${u.km_saida.toLocaleString()}`
                        );
                        $('#btnDevolver').attr('href', baseUrl + '/frota/devolver.php?id=' + u.id);
                        $('#btnDetalhes').attr('href', baseUrl + '/frota/detalhes.php?id=' + u.id);
                        $('#meuVeiculoContainer').show();
                    } else {
                        $('#meuVeiculoContainer').hide();
                    }
                }
            });
        }
        
        function carregarVeiculos(status) {
            $.ajax({
                url: baseUrl + '/api/frota/listar_veiculos.php',
                method: 'GET',
                data: { status: status },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        renderizarVeiculos(data.veiculos);
                    }
                }
            });
        }
        
        function renderizarVeiculos(veiculos) {
            const container = $('#listaVeiculos');
            container.empty();
            
            if (veiculos.length === 0) {
                $('#semVeiculos').show();
                $('#badgeContagem').text('0 Unidades');
                return;
            }
            
            $('#semVeiculos').hide();
            $('#badgeContagem').text(veiculos.length + ' Unidade' + (veiculos.length !== 1 ? 's' : ''));
            
            veiculos.forEach(function(v) {
                const statusText = {
                    'disponivel': 'Disponível',
                    'em_uso': 'Em Uso',
                    'manutencao': 'Manutenção',
                    'inativo': 'Inativo'
                }[v.status] || v.status;

                const statusColor = {
                    'disponivel': 'bg-primary text-white shadow-lg',
                    'em_uso': 'bg-secondary-container text-on-secondary-container shadow-lg',
                    'manutencao': 'bg-error text-on-error shadow-lg',
                    'inativo': 'bg-slate-200 text-slate-600'
                }[v.status] || 'bg-slate-200 text-slate-600';
                
                let btnHtml = '';
                if (v.status === 'disponivel') {
                    btnHtml = `
                        <a href="${baseUrl}/frota/retirar.php?id=${v.id}" class="w-full py-3 bg-primary text-on-primary rounded-xl font-bold flex items-center justify-center gap-2 transition-all hover:bg-primary-container hover:shadow-lg active:scale-[0.98] no-underline">
                            <span class="material-symbols-outlined">key</span>
                            Retirar Veículo
                        </a>
                    `;
                } else if (v.status === 'em_uso' && v.usuario_atual) {
                    btnHtml = `
                        <div class="w-full py-3 bg-secondary-container/20 text-on-secondary-container rounded-xl font-bold flex items-center justify-center gap-2 text-sm border border-secondary-container/30">
                            <span class="material-symbols-outlined text-lg">person</span>
                            ${v.usuario_atual}
                        </div>
                    `;
                } else if (v.status === 'manutencao') {
                    btnHtml = `
                        <div class="w-full py-3 bg-error-container text-on-error-container rounded-xl font-bold flex items-center justify-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-lg">build</span>
                            Em Manutenção
                        </div>
                    `;
                }
                
                const fallbackHtml = '<div class="w-full h-full flex items-center justify-center bg-slate-100"><span class="material-symbols-outlined text-slate-300 text-6xl">local_shipping</span></div>';
                const imgHtml = v.foto_veiculo 
                    ? `<img class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" src="${baseUrl}/uploads/frota/${v.foto_veiculo}" alt="${v.modelo}" onerror="imgFallback(this)">`
                    : fallbackHtml;
                
                const cardHtml = `
                    <div class="bg-white rounded-xl overflow-hidden flex flex-col md:flex-row group transition-all duration-300 border border-slate-200 hover:shadow-xl hover:border-slate-300">
                        <div class="md:w-2/5 relative h-48 md:h-auto overflow-hidden bg-slate-100">
                            ${imgHtml}
                            <div class="absolute top-3 left-3">
                                <span class="${statusColor} text-[10px] font-bold px-2 py-1 rounded-md uppercase tracking-widest">${statusText}</span>
                            </div>
                        </div>
                        <div class="md:w-3/5 p-6 flex flex-col justify-between">
                            <div>
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h4 class="text-xl font-extrabold font-headline text-slate-900">${v.modelo}</h4>
                                        <p class="text-slate-500 font-label text-xs font-bold uppercase tracking-wider">${v.marca || ''}</p>
                                    </div>
                                    <div class="bg-slate-100 border border-slate-200 px-3 py-1 rounded-lg">
                                        <span class="font-mono text-sm font-bold text-slate-700">${v.placa}</span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 mt-6">
                                    ${v.cor ? `
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-primary text-xl">palette</span>
                                        <div>
                                            <p class="text-[10px] text-slate-500 font-bold uppercase">Cor</p>
                                            <p class="text-sm font-semibold text-slate-800">${v.cor}</p>
                                        </div>
                                    </div>` : ''}
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-primary text-xl">speed</span>
                                        <div>
                                            <p class="text-[10px] text-slate-500 font-bold uppercase">Km Atual</p>
                                            <p class="text-sm font-semibold text-slate-800">${Number(v.km_atual).toLocaleString('pt-BR')} km</p>
                                        </div>
                                    </div>
                                    ${v.ano ? `
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-primary text-xl">calendar_month</span>
                                        <div>
                                            <p class="text-[10px] text-slate-500 font-bold uppercase">Ano</p>
                                            <p class="text-sm font-semibold text-slate-800">${v.ano}</p>
                                        </div>
                                    </div>` : ''}
                                </div>
                            </div>
                            <div class="mt-8">
                                ${btnHtml}
                            </div>
                        </div>
                    </div>
                `;
                
                container.append(cardHtml);
            });
        }
    </script>
</body>
</html>
