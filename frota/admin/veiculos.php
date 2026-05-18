<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../../auth/verifica_sessao.php');

require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('frota_admin_veiculos');

$isAdmin = MenuPermissaoService::isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Veículos - Frota</title>
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

        /* Bootstrap modal compatibility */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1055; overflow-y: auto; background-color: rgba(0,0,0,0.5); }
        .modal.show { display: flex; align-items: center; justify-content: center; }
        .modal-dialog { width: 100%; max-width: 800px; margin: 1.75rem auto; padding: 0 1rem; }
        .modal-dialog-centered { display: flex; align-items: center; min-height: calc(100% - 3.5rem); }
        .modal-content { position: relative; display: flex; flex-direction: column; background: white; border-radius: 1rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .modal-lg { max-width: 800px; }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; }
        .modal-backdrop { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; background-color: rgba(0,0,0,0.5); }
        .btn-close { background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #64748b; padding: 0.25rem; border-radius: 0.375rem; transition: background-color 0.15s; }
        .btn-close:hover { background-color: #f1f5f9; }
        .btn-close::after { content: '✕'; }
        .btn-close-white { color: white; }
        .fade { transition: opacity 0.15s linear; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 600; color: #334155; margin-bottom: 0.375rem; }
        .form-control, .form-select { width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; font-size: 0.875rem; outline: none; transition: border-color 0.15s; background: white; }
        .form-control:focus, .form-select:focus { border-color: #00685c; box-shadow: 0 0 0 2px rgba(0,104,92,0.15); }
        textarea.form-control { resize: vertical; }
        .text-uppercase { text-transform: uppercase; }
        .text-danger { color: #dc2626; }

        /* Grid helpers for modal form */
        .row { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); }
        .g-3 { gap: 0.75rem; }
        .col-md-3 { grid-column: span 3; }
        .col-md-4 { grid-column: span 4; }
        .col-12 { grid-column: span 12; }
        @media (max-width: 767px) {
            .col-md-3, .col-md-4 { grid-column: span 12; }
        }
    </style>
</head>
<body class="bg-background text-on-surface min-h-screen">

    <!-- Sidebar -->
    <aside class="hidden lg:flex fixed left-0 top-0 h-full flex-col p-4 z-40 w-20 hover:w-64 transition-all duration-300 group/sidebar bg-white/90 backdrop-blur-xl border-r border-slate-200 hover:shadow-2xl">
        <div class="flex items-center gap-4 mb-10 px-2">
            <div class="w-10 h-10 bg-primary flex items-center justify-center rounded-xl shrink-0">
                <span class="material-symbols-outlined text-white" style="font-variation-settings: 'FILL' 1;">local_shipping</span>
            </div>
            <div class="opacity-0 group-hover/sidebar:opacity-100 transition-opacity whitespace-nowrap overflow-hidden">
                <span class="text-lg font-black text-[#00685c]">Frota AOM</span>
                <p class="text-[10px] uppercase font-bold text-slate-500 leading-none">Painel de Controle</p>
            </div>
        </div>
        <nav class="flex flex-col gap-2 flex-grow">
            <a class="flex items-center gap-4 p-3 text-slate-600 hover:bg-slate-100 rounded-xl transition-all group-hover/sidebar:translate-x-1" href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="opacity-0 group-hover/sidebar:opacity-100 transition-opacity whitespace-nowrap font-label font-medium">Dashboard</span>
            </a>
            <?php if (MenuPermissaoService::podeAcessar('frota_historico')): ?>
            <a class="flex items-center gap-4 p-3 text-slate-600 hover:bg-slate-100 rounded-xl transition-all group-hover/sidebar:translate-x-1" href="<?= MenuPermissaoService::ajustarUrl('/frota/historico.php') ?>">
                <span class="material-symbols-outlined">history</span>
                <span class="opacity-0 group-hover/sidebar:opacity-100 transition-opacity whitespace-nowrap font-label font-medium">Meu Histórico</span>
            </a>
            <?php endif; ?>
            <a class="flex items-center gap-4 p-3 bg-[#00897B] text-white rounded-xl shadow-lg shadow-[#00897B]/20 transition-all brightness-110" href="#">
                <span class="material-symbols-outlined">local_shipping</span>
                <span class="opacity-0 group-hover/sidebar:opacity-100 transition-opacity whitespace-nowrap font-label font-medium">Veículos</span>
            </a>
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
                <a class="text-slate-600 font-medium hover:text-[#00897B] transition-colors duration-200 font-label text-sm" href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>">Dashboard</a>
                <?php if (MenuPermissaoService::podeAcessar('frota_historico')): ?>
                <a class="text-slate-600 font-medium hover:text-[#00897B] transition-colors duration-200 font-label text-sm" href="<?= MenuPermissaoService::ajustarUrl('/frota/historico.php') ?>">Histórico</a>
                <?php endif; ?>
                <a class="text-[#00685c] font-bold border-b-2 border-[#00685c] pb-1 font-label text-sm" href="#">Veículos</a>
                <?php if (MenuPermissaoService::podeAcessar('frota_admin_manutencoes')): ?>
                <a class="text-slate-600 font-medium hover:text-[#00897B] transition-colors duration-200 font-label text-sm" href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/manutencoes.php') ?>">Manutenções</a>
                <?php endif; ?>
                <?php if (MenuPermissaoService::podeAcessar('frota_relatorios')): ?>
                <a class="text-slate-600 font-medium hover:text-[#00897B] transition-colors duration-200 font-label text-sm" href="<?= MenuPermissaoService::ajustarUrl('/frota/relatorios.php') ?>">Relatórios</a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="flex items-center gap-3">
            <button class="hidden md:flex items-center gap-2 px-4 py-2 bg-primary text-on-primary font-semibold rounded-xl shadow-lg shadow-primary/20 hover:brightness-110 transition-all text-sm" data-bs-toggle="modal" data-bs-target="#modalVeiculo" onclick="novoVeiculo()">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Novo Veículo
            </button>
        </div>
    </header>

    <!-- Mobile Nav -->
    <div class="md:hidden px-4 py-3 flex gap-2 overflow-x-auto bg-white border-b border-slate-200">
        <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="flex-shrink-0 px-3 py-1.5 bg-white text-slate-700 font-medium rounded-xl text-xs border border-slate-200">Dashboard</a>
        <?php if (MenuPermissaoService::podeAcessar('frota_historico')): ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/frota/historico.php') ?>" class="flex-shrink-0 px-3 py-1.5 bg-white text-slate-700 font-medium rounded-xl text-xs border border-slate-200">Histórico</a>
        <?php endif; ?>
        <a href="#" class="flex-shrink-0 px-3 py-1.5 bg-primary text-on-primary font-medium rounded-xl text-xs">Veículos</a>
        <?php if (MenuPermissaoService::podeAcessar('frota_admin_manutencoes')): ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/manutencoes.php') ?>" class="flex-shrink-0 px-3 py-1.5 bg-white text-slate-700 font-medium rounded-xl text-xs border border-slate-200">Manutenções</a>
        <?php endif; ?>
        <?php if (MenuPermissaoService::podeAcessar('frota_relatorios')): ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/frota/relatorios.php') ?>" class="flex-shrink-0 px-3 py-1.5 bg-white text-slate-700 font-medium rounded-xl text-xs border border-slate-200">Relatórios</a>
        <?php endif; ?>
    </div>

    <main class="max-w-[1440px] mx-auto p-6 lg:p-10 lg:pl-24">

        <!-- Module Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-6">
            <div>
                <div class="flex items-center gap-2 text-primary mb-2">
                    <span class="material-symbols-outlined">local_shipping</span>
                    <span class="font-label font-semibold text-sm tracking-wider uppercase">Administração de Frota</span>
                </div>
                <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight mb-1 font-headline">Gerenciar Veículos</h1>
                <p class="text-slate-600 font-body">Cadastro, edição e controle de status dos veículos da frota.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="flex items-center gap-2 px-4 py-2 bg-white text-slate-700 font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all shadow-sm text-sm">
                    <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                    <span>Voltar</span>
                </a>
                <button class="flex items-center gap-2 px-4 py-2 bg-primary text-on-primary font-semibold rounded-xl shadow-lg shadow-primary/20 hover:brightness-110 transition-all text-sm" data-bs-toggle="modal" data-bs-target="#modalVeiculo" onclick="novoVeiculo()">
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    <span>Novo Veículo</span>
                </button>
            </div>
        </div>

        <!-- Stat Cards -->
        <section class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-12">
            <div class="bg-white p-6 rounded-xl flex flex-col justify-between border border-slate-200 border-l-4 border-l-primary shadow-sm hover:shadow-md transition-shadow">
                <div>
                    <p class="text-slate-500 font-label text-sm font-semibold mb-1">Total Veículos</p>
                    <h3 class="text-4xl font-extrabold text-slate-900 font-headline" id="statTotal">0</h3>
                </div>
                <div class="mt-4 flex items-center text-primary-container text-xs font-bold">
                    <span class="material-symbols-outlined text-sm mr-1">analytics</span>
                    Ativos Totais
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl flex flex-col justify-between border border-slate-200 border-l-4 border-l-primary-fixed shadow-sm hover:shadow-md transition-shadow">
                <div>
                    <p class="text-slate-500 font-label text-sm font-semibold mb-1">Disponíveis</p>
                    <h3 class="text-4xl font-extrabold text-slate-900 font-headline" id="statDisponiveis">0</h3>
                </div>
                <div class="mt-4 flex items-center text-primary-container text-xs font-bold">
                    <span class="material-symbols-outlined text-sm mr-1">check_circle</span>
                    Pronto para uso
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl flex flex-col justify-between border border-slate-200 border-l-4 border-l-secondary-container shadow-sm hover:shadow-md transition-shadow">
                <div>
                    <p class="text-slate-500 font-label text-sm font-semibold mb-1">Em Uso</p>
                    <h3 class="text-4xl font-extrabold text-slate-900 font-headline" id="statEmUso">0</h3>
                </div>
                <div class="mt-4 flex items-center text-secondary text-xs font-bold">
                    <span class="material-symbols-outlined text-sm mr-1">route</span>
                    Em trânsito
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl flex flex-col justify-between border border-slate-200 border-l-4 border-l-error shadow-sm hover:shadow-md transition-shadow">
                <div>
                    <p class="text-slate-500 font-label text-sm font-semibold mb-1">Manutenção</p>
                    <h3 class="text-4xl font-extrabold text-slate-900 font-headline" id="statManutencao">0</h3>
                </div>
                <div class="mt-4 flex items-center text-error text-xs font-bold">
                    <span class="material-symbols-outlined text-sm mr-1">build</span>
                    Na oficina
                </div>
            </div>
        </section>

        <!-- Table Section -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between px-6 py-5 border-b border-slate-200 gap-4">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary">list_alt</span>
                    <h2 class="text-lg font-bold font-headline text-slate-900">Lista de Veículos</h2>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-slate-500 text-sm font-medium">Filtrar:</span>
                    <div class="relative">
                        <select id="filtroStatus" class="appearance-none bg-white border border-slate-200 rounded-lg py-2 pl-4 pr-10 text-sm font-medium focus:ring-1 focus:ring-primary focus:border-primary cursor-pointer shadow-sm">
                            <option value="">Todos</option>
                            <option value="disponivel">Disponíveis</option>
                            <option value="em_uso">Em Uso</option>
                            <option value="manutencao">Manutenção</option>
                            <option value="inativo">Inativos</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 text-[18px]">expand_more</span>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-left">
                            <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Placa</th>
                            <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Modelo</th>
                            <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Marca</th>
                            <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Ano</th>
                            <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">KM Atual</th>
                            <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider text-center w-[120px]">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaVeiculos">
                        <tr>
                            <td colspan="7" class="text-center py-12 text-slate-400">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-8 h-8 border-4 border-primary/30 border-t-primary rounded-full animate-spin"></div>
                                    <span class="text-sm font-medium">Carregando veículos...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Veículo -->
    <div class="modal fade" id="modalVeiculo" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #00685c; border-radius: 1rem 1rem 0 0;">
                    <h5 class="modal-title text-white font-bold font-headline flex items-center gap-2" id="modalVeiculoTitulo">
                        <span class="material-symbols-outlined">local_shipping</span>
                        Novo Veículo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formVeiculo">
                    <div class="modal-body">
                        <input type="hidden" id="veiculoId" name="id">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Placa <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-uppercase" id="placa" name="placa" required maxlength="10" placeholder="ABC-1234">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Modelo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="modelo" name="modelo" required placeholder="Ex: HR, Caminhão, etc">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Marca <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="marca" name="marca" required placeholder="Ex: Hyundai, Mercedes">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Ano</label>
                                <input type="number" class="form-control" id="ano" name="ano" min="1900" max="2099" placeholder="2024">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cor</label>
                                <input type="text" class="form-control" id="cor" name="cor" placeholder="Branco">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">KM Atual</label>
                                <input type="number" class="form-control" id="km_atual" name="km_atual" min="0" placeholder="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="disponivel">Disponível</option>
                                    <option value="manutencao">Em Manutenção</option>
                                    <option value="inativo">Inativo</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Foto do Veículo</label>
                                <div id="fotoContainer" style="border: 2px dashed #cbd5e1; border-radius: 0.75rem; padding: 1.25rem; text-align: center; cursor: pointer; transition: all 0.3s; min-height: 160px; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; overflow: hidden; background: #f8fafc;" onclick="document.getElementById('inputFotoVeiculo').click()">
                                    <span class="material-symbols-outlined" style="font-size: 2.5rem; color: #94a3b8;">photo_camera</span>
                                    <p style="color: #94a3b8; margin: 0.5rem 0 0; font-size: 0.875rem;">Clique para selecionar uma foto</p>
                                </div>
                                <input type="file" id="inputFotoVeiculo" accept="image/*" style="display: none;" onchange="previewFoto(this)">
                                <input type="hidden" id="foto_veiculo_base64" name="foto_veiculo">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observações</label>
                                <textarea class="form-control" id="observacoes" name="observacoes" rows="2" placeholder="Informações adicionais sobre o veículo..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="px-4 py-2 text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="px-5 py-2 text-sm font-semibold text-white bg-primary hover:brightness-110 rounded-lg transition-all flex items-center gap-1.5 shadow-md shadow-primary/20" id="btnSalvar">
                            <span class="material-symbols-outlined text-[18px]">check</span>
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação -->
    <div class="modal fade" id="modalConfirmar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #f59e0b; border-radius: 1rem 1rem 0 0;">
                    <h5 class="modal-title text-white font-bold font-headline flex items-center gap-2">
                        <span class="material-symbols-outlined">warning</span>
                        Confirmar Remoção
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-4 text-slate-700 font-medium">Tem certeza que deseja remover este veículo?</p>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
                        <div class="flex items-start gap-2">
                            <span class="material-symbols-outlined text-blue-500 text-[20px] mt-0.5 shrink-0">info</span>
                            <div>
                                <strong class="block mb-1.5">Importante:</strong>
                                <ul class="list-disc pl-4 space-y-1 text-blue-700">
                                    <li>Se o veículo possuir <strong>histórico</strong> (utilizações, manutenções, abastecimentos), ele será <strong>desativado</strong> para preservar os registros.</li>
                                    <li>Se o veículo <strong>não possuir histórico</strong>, será excluído permanentemente.</li>
                                    <li>Veículos <strong>em uso</strong> não podem ser removidos.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="px-4 py-2 text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="px-5 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors flex items-center gap-1.5 shadow-md" id="btnConfirmarExclusao">
                        <span class="material-symbols-outlined text-[18px]">delete</span>
                        Confirmar Remoção
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';
        const modalVeiculo = new bootstrap.Modal(document.getElementById('modalVeiculo'));
        
        $(document).ready(function() {
            carregarVeiculos();
            carregarEstatisticas();
            
            $('#filtroStatus').change(function() {
                carregarVeiculos($(this).val());
            });
        });
        
        function carregarEstatisticas() {
            $.get(baseUrl + '/api/frota/estatisticas.php', function(data) {
                if (data.status === 'ok') {
                    $('#statTotal').text(data.estatisticas.total);
                    $('#statDisponiveis').text(data.estatisticas.disponiveis);
                    $('#statEmUso').text(data.estatisticas.em_uso);
                    $('#statManutencao').text(data.estatisticas.manutencao);
                }
            });
        }
        
        function carregarVeiculos(status = '') {
            $.ajax({
                url: baseUrl + '/api/frota/listar_veiculos.php',
                data: { status: status },
                success: function(data) {
                    if (data.status === 'ok') {
                        renderizarTabela(data.veiculos);
                    }
                }
            });
        }
        
        function renderizarTabela(veiculos) {
            const tbody = $('#tabelaVeiculos');
            
            if (veiculos.length === 0) {
                tbody.html(`<tr><td colspan="7" class="text-center py-12 text-slate-400">
                    <div class="flex flex-col items-center gap-2">
                        <span class="material-symbols-outlined text-slate-300 text-5xl">local_shipping</span>
                        <span class="text-sm font-medium">Nenhum veículo encontrado</span>
                    </div>
                </td></tr>`);
                return;
            }
            
            let html = '';
            veiculos.forEach(v => {
                const isInativo = v.ativo === 0;

                const statusMap = {
                    'disponivel': { text: 'Disponível', cls: 'bg-emerald-50 text-emerald-700 border border-emerald-200' },
                    'em_uso':     { text: 'Em Uso',     cls: 'bg-amber-50 text-amber-700 border border-amber-200' },
                    'manutencao': { text: 'Manutenção', cls: 'bg-red-50 text-red-700 border border-red-200' },
                    'inativo':    { text: 'Inativo',    cls: 'bg-slate-100 text-slate-500 border border-slate-200' }
                };

                let statusInfo;
                if (isInativo) {
                    statusInfo = { text: 'Desativado', cls: 'bg-slate-100 text-slate-500 border border-slate-200' };
                } else {
                    statusInfo = statusMap[v.status] || { text: v.status, cls: 'bg-slate-100 text-slate-500 border border-slate-200' };
                }

                const rowClass = isInativo ? 'border-b border-slate-100 bg-slate-50/50 opacity-60' : 'border-b border-slate-100 hover:bg-slate-50 transition-colors';
                
                let botoesAcao = '';
                if (isInativo) {
                    botoesAcao = `
                        <button class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-emerald-600 hover:bg-emerald-50 transition-colors" onclick="reativarVeiculo(${v.id})" title="Reativar">
                            <span class="material-symbols-outlined text-[18px]">refresh</span>
                        </button>
                    `;
                } else {
                    botoesAcao = `
                        <button class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-primary hover:bg-primary-fixed/30 transition-colors" onclick="editarVeiculo(${v.id})" title="Editar">
                            <span class="material-symbols-outlined text-[18px]">edit</span>
                        </button>
                        <button class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-red-500 hover:bg-red-50 transition-colors" onclick="excluirVeiculo(${v.id})" title="Remover">
                            <span class="material-symbols-outlined text-[18px]">delete</span>
                        </button>
                    `;
                }
                
                html += `
                    <tr class="${rowClass}">
                        <td class="px-6 py-3.5"><span class="bg-slate-100 border border-slate-200 px-2 py-0.5 rounded font-mono text-xs font-bold text-slate-700">${v.placa}</span></td>
                        <td class="px-6 py-3.5 font-medium text-slate-800">${v.modelo}${isInativo ? ' <span class="text-slate-400 text-xs font-normal">(desativado)</span>' : ''}</td>
                        <td class="px-6 py-3.5 text-slate-600">${v.marca}</td>
                        <td class="px-6 py-3.5 text-slate-600">${v.ano || '-'}</td>
                        <td class="px-6 py-3.5 text-slate-600 font-medium">${v.km_atual.toLocaleString()} km</td>
                        <td class="px-6 py-3.5"><span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold ${statusInfo.cls}">${statusInfo.text}</span></td>
                        <td class="px-6 py-3.5 text-center"><div class="flex items-center justify-center gap-1">${botoesAcao}</div></td>
                    </tr>
                `;
            });
            
            tbody.html(html);
        }
        
        function novoVeiculo() {
            $('#veiculoId').val('');
            $('#formVeiculo')[0].reset();
            $('#foto_veiculo_base64').val('');
            resetFotoContainer();
            $('#modalVeiculoTitulo').html('<span class="material-symbols-outlined">local_shipping</span> Novo Veículo');
        }
        
        function editarVeiculo(id) {
            $.get(baseUrl + '/api/frota/buscar_veiculo.php', { id: id }, function(data) {
                if (data.status === 'ok' && data.veiculo) {
                    const v = data.veiculo;
                    $('#veiculoId').val(v.id);
                    $('#placa').val(v.placa);
                    $('#modelo').val(v.modelo);
                    $('#marca').val(v.marca);
                    $('#ano').val(v.ano);
                    $('#cor').val(v.cor);
                    $('#km_atual').val(v.km_atual);
                    $('#status').val(v.status);
                    $('#observacoes').val(v.observacoes);
                    $('#foto_veiculo_base64').val('');
                    if (v.foto_veiculo) {
                        mostrarFotoExistente(baseUrl + '/uploads/frota/' + v.foto_veiculo);
                    } else {
                        resetFotoContainer();
                    }
                    $('#modalVeiculoTitulo').html('<span class="material-symbols-outlined">edit</span> Editar Veículo');
                    modalVeiculo.show();
                }
            });
        }
        
        let veiculoParaExcluir = null;
        const modalConfirmar = new bootstrap.Modal(document.getElementById('modalConfirmar'));
        
        function excluirVeiculo(id) {
            veiculoParaExcluir = id;
            modalConfirmar.show();
        }
        
        function reativarVeiculo(id) {
            if (!confirm('Deseja reativar este veículo?')) return;
            
            $.ajax({
                url: baseUrl + '/api/frota/reativar_veiculo.php',
                method: 'POST',
                data: { id: id },
                success: function(data) {
                    if (data.status === 'ok') {
                        exibirToast(data.mensagem || 'Veículo reativado com sucesso!', 'success');
                        carregarVeiculos($('#filtroStatus').val());
                        carregarEstatisticas();
                    } else {
                        exibirToast('Erro: ' + (data.mensagem || 'Erro desconhecido'), 'danger');
                    }
                }
            });
        }
        
        $('#btnConfirmarExclusao').click(function() {
            if (!veiculoParaExcluir) return;
            
            $.ajax({
                url: baseUrl + '/api/frota/excluir_veiculo.php',
                method: 'POST',
                data: { id: veiculoParaExcluir },
                success: function(data) {
                    modalConfirmar.hide();
                    if (data.status === 'ok') {
                        exibirToast(data.mensagem || 'Veículo excluído com sucesso!', 'success');
                        carregarVeiculos($('#filtroStatus').val());
                        carregarEstatisticas();
                    } else {
                        exibirToast('Erro: ' + (data.mensagem || 'Erro desconhecido'), 'danger');
                    }
                    veiculoParaExcluir = null;
                }
            });
        });
        
        $('#formVeiculo').submit(function(e) {
            e.preventDefault();
            
            const btn = $('#btnSalvar');
            btn.prop('disabled', true).html('<div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div> Salvando...');
            
            $.ajax({
                url: baseUrl + '/api/frota/salvar_veiculo.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function(data) {
                    if (data.status === 'ok') {
                        modalVeiculo.hide();
                        exibirToast(data.mensagem || 'Veículo salvo com sucesso!', 'success');
                        carregarVeiculos($('#filtroStatus').val());
                        carregarEstatisticas();
                    } else {
                        exibirToast('Erro: ' + (data.mensagem || 'Erro desconhecido'), 'danger');
                    }
                    btn.prop('disabled', false).html('<span class="material-symbols-outlined text-[18px]">check</span> Salvar');
                },
                error: function() {
                    exibirToast('Erro ao salvar veículo', 'danger');
                    btn.prop('disabled', false).html('<span class="material-symbols-outlined text-[18px]">check</span> Salvar');
                }
            });
        });

        function previewFoto(input) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            if (file.size > 5 * 1024 * 1024) {
                exibirToast('A foto deve ter no máximo 5MB', 'warning');
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    const maxSize = 1024;
                    let w = img.width, h = img.height;
                    if (w > maxSize || h > maxSize) {
                        if (w > h) { h = Math.round(h * maxSize / w); w = maxSize; }
                        else { w = Math.round(w * maxSize / h); h = maxSize; }
                    }
                    canvas.width = w;
                    canvas.height = h;
                    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                    const dataUrl = canvas.toDataURL('image/jpeg', 0.7);
                    $('#foto_veiculo_base64').val(dataUrl);
                    renderFotoPreview(dataUrl);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        function renderFotoPreview(src) {
            const container = document.getElementById('fotoContainer');
            container.innerHTML = `
                <img src="${src}" style="max-width: 100%; max-height: 200px; border-radius: 0.5rem; object-fit: cover;">
                <button type="button" onclick="removerFoto(event)" style="position: absolute; top: 8px; right: 8px; background: #ef4444; color: white; border: none; border-radius: 0.375rem; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">✕</button>
            `;
            container.style.borderStyle = 'solid';
            container.style.borderColor = '#00685c';
            container.style.background = '#f0fdf4';
        }

        function mostrarFotoExistente(src) {
            renderFotoPreview(src);
            $('#foto_veiculo_base64').val('manter');
        }

        function removerFoto(e) {
            e.stopPropagation();
            resetFotoContainer();
            $('#foto_veiculo_base64').val('remover');
            $('#inputFotoVeiculo').val('');
        }

        function resetFotoContainer() {
            const container = document.getElementById('fotoContainer');
            container.innerHTML = `
                <span class="material-symbols-outlined" style="font-size: 2.5rem; color: #94a3b8;">photo_camera</span>
                <p style="color: #94a3b8; margin: 0.5rem 0 0; font-size: 0.875rem;">Clique para selecionar uma foto</p>
            `;
            container.style.borderStyle = 'dashed';
            container.style.borderColor = '#cbd5e1';
            container.style.background = '#f8fafc';
        }
    </script>
</body>
</html>
