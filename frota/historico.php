<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('frota_historico');

$usuarioId = $_SESSION['usuario_id'] ?? 0;
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Histórico - Frota</title>
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
                        "surface-container-highest": "#cbd5e1",
                        "page-accent": "#2563eb",
                        "page-accent-dark": "#1d4ed8",
                        "page-accent-light": "#3b82f6",
                        "page-accent-bg": "#dbeafe",
                        "page-accent-bg-light": "#eff6ff"
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

    <!-- Top Navigation Bar -->
    <header class="flex justify-between items-center w-full px-6 py-3 sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div class="flex items-center gap-8">
            <span class="text-xl font-bold tracking-tight text-[#00685c]">Frota AOM</span>
            <nav class="hidden md:flex items-center gap-6">
                <a class="text-slate-600 font-medium hover:text-[#00897B] transition-colors duration-200 font-label text-sm" href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>">Dashboard</a>
                <a class="text-blue-600 font-bold border-b-2 border-blue-600 pb-1 font-label text-sm" href="#">Histórico</a>
                <?php if (MenuPermissaoService::podeAcessar('frota_admin_manutencoes')): ?>
                <a class="text-slate-600 font-medium hover:text-[#00897B] transition-colors duration-200 font-label text-sm" href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/manutencoes.php') ?>">Manutenções</a>
                <?php endif; ?>
                <?php if (MenuPermissaoService::podeAcessar('frota_relatorios')): ?>
                <a class="text-slate-600 font-medium hover:text-[#00897B] transition-colors duration-200 font-label text-sm" href="<?= MenuPermissaoService::ajustarUrl('/frota/relatorios.php') ?>">Relatórios</a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="flex items-center gap-4">
            <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="flex items-center gap-2 px-4 py-2 bg-white text-slate-700 font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all shadow-sm text-sm">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                <span>Voltar</span>
            </a>
            <div class="w-8 h-8 rounded-full overflow-hidden border border-slate-300 bg-primary flex items-center justify-center text-white text-xs font-bold">
                <?= mb_substr($nomeUsuario, 0, 1) ?>
            </div>
        </div>
    </header>

    <!-- Mobile Nav -->
    <div class="md:hidden px-4 py-3 flex gap-2 overflow-x-auto bg-white border-b border-slate-200">
        <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="flex-shrink-0 px-3 py-1.5 bg-white text-slate-700 font-medium rounded-xl text-xs border border-slate-200">Dashboard</a>
        <a href="#" class="flex-shrink-0 px-3 py-1.5 bg-blue-600 text-white font-medium rounded-xl text-xs">Histórico</a>
        <?php if (MenuPermissaoService::podeAcessar('frota_admin_manutencoes')): ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/frota/admin/manutencoes.php') ?>" class="flex-shrink-0 px-3 py-1.5 bg-white text-slate-700 font-medium rounded-xl text-xs border border-slate-200">Manutenções</a>
        <?php endif; ?>
        <?php if (MenuPermissaoService::podeAcessar('frota_relatorios')): ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/frota/relatorios.php') ?>" class="flex-shrink-0 px-3 py-1.5 bg-white text-slate-700 font-medium rounded-xl text-xs border border-slate-200">Relatórios</a>
        <?php endif; ?>
    </div>

    <main class="max-w-[1440px] mx-auto p-6 lg:p-10">

        <!-- Module Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-6">
            <div>
                <div class="flex items-center gap-2 text-blue-600 mb-2">
                    <span class="material-symbols-outlined">history</span>
                    <span class="font-label font-semibold text-sm tracking-wider uppercase">Módulo de Frota</span>
                </div>
                <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight mb-1 font-headline">Meu Histórico</h1>
                <p class="text-slate-600 font-body">Suas utilizações de veículos e viagens realizadas.</p>
            </div>
        </div>

        <!-- Stat Cards -->
        <section class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-4 lg:gap-6 mb-10">
            <div class="bg-white p-6 rounded-xl flex flex-col justify-between border border-slate-200 border-l-4 border-l-blue-600 shadow-sm hover:shadow-md transition-shadow">
                <div>
                    <p class="text-slate-500 font-label text-sm font-semibold mb-1">Total de Viagens</p>
                    <h3 class="text-4xl font-extrabold text-slate-900 font-headline" id="totalViagens">0</h3>
                </div>
                <div class="mt-4 flex items-center text-blue-600 text-xs font-bold">
                    <span class="material-symbols-outlined text-sm mr-1">directions_car</span>
                    Viagens realizadas
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl flex flex-col justify-between border border-slate-200 border-l-4 border-l-emerald-500 shadow-sm hover:shadow-md transition-shadow">
                <div>
                    <p class="text-slate-500 font-label text-sm font-semibold mb-1">KM Percorridos</p>
                    <h3 class="text-4xl font-extrabold text-slate-900 font-headline" id="kmTotal">0</h3>
                </div>
                <div class="mt-4 flex items-center text-emerald-600 text-xs font-bold">
                    <span class="material-symbols-outlined text-sm mr-1">speed</span>
                    Quilômetros totais
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl flex flex-col justify-between border border-slate-200 border-l-4 border-l-cyan-500 shadow-sm hover:shadow-md transition-shadow">
                <div>
                    <p class="text-slate-500 font-label text-sm font-semibold mb-1">Tempo Total</p>
                    <h3 class="text-4xl font-extrabold text-slate-900 font-headline" id="tempoTotal">0h</h3>
                </div>
                <div class="mt-4 flex items-center text-cyan-600 text-xs font-bold">
                    <span class="material-symbols-outlined text-sm mr-1">schedule</span>
                    Tempo em uso
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl flex flex-col justify-between border border-slate-200 border-l-4 border-l-amber-500 shadow-sm hover:shadow-md transition-shadow">
                <div>
                    <p class="text-slate-500 font-label text-sm font-semibold mb-1">Este Mês</p>
                    <h3 class="text-4xl font-extrabold text-slate-900 font-headline" id="mesAtual">0</h3>
                </div>
                <div class="mt-4 flex items-center text-amber-600 text-xs font-bold">
                    <span class="material-symbols-outlined text-sm mr-1">calendar_month</span>
                    Mês atual
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl flex flex-col justify-between border border-slate-200 border-l-4 border-l-green-600 shadow-sm hover:shadow-md transition-shadow col-span-2 sm:col-span-1">
                <div>
                    <p class="text-slate-500 font-label text-sm font-semibold mb-1">Valor Total Locação</p>
                    <h3 class="text-3xl font-extrabold text-slate-900 font-headline" id="valorTotalLocacao">R$ 0,00</h3>
                </div>
                <div class="mt-4 flex items-center text-green-600 text-xs font-bold">
                    <span class="material-symbols-outlined text-sm mr-1">paid</span>
                    <span id="valorKmInfo">R$ 0,00/km</span>
                </div>
            </div>
        </section>

        <!-- Filter Section -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-8">
            <div class="flex flex-wrap gap-6 items-end">
                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Período</label>
                    <div class="relative">
                        <select id="filtroPeriodo" class="appearance-none bg-white border border-slate-200 rounded-lg py-2 pl-4 pr-10 text-sm font-medium focus:ring-blue-500 focus:border-blue-500 focus:ring-1 cursor-pointer shadow-sm min-w-[180px]">
                            <option value="">Todos</option>
                            <option value="7" selected>Últimos 7 dias</option>
                            <option value="30">Últimos 30 dias</option>
                            <option value="90">Últimos 90 dias</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 text-[20px]">expand_more</span>
                    </div>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Status</label>
                    <div class="relative">
                        <select id="filtroStatus" class="appearance-none bg-white border border-slate-200 rounded-lg py-2 pl-4 pr-10 text-sm font-medium focus:ring-blue-500 focus:border-blue-500 focus:ring-1 cursor-pointer shadow-sm min-w-[180px]">
                            <option value="">Todos</option>
                            <option value="finalizado">Finalizados</option>
                            <option value="em_andamento">Em Andamento</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 text-[20px]">expand_more</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- List Container -->
        <div id="listaUtilizacoes">
            <div class="flex flex-col items-center justify-center py-16">
                <div class="w-10 h-10 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mb-4"></div>
                <p class="text-slate-500 text-sm font-medium">Carregando histórico...</p>
            </div>
        </div>

        <!-- No Results -->
        <div id="semResultados" class="flex flex-col items-center justify-center py-20 text-center" style="display: none;">
            <span class="material-symbols-outlined text-slate-300 text-7xl mb-4">inbox</span>
            <h5 class="text-lg font-bold text-slate-900 font-headline mb-1">Nenhuma utilização encontrada</h5>
            <p class="text-slate-500 text-sm">Você ainda não utilizou nenhum veículo.</p>
        </div>

    </main>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';
        
        $(document).ready(function() {
            carregarHistorico();
            carregarEstatisticas();
            
            $('#filtroPeriodo, #filtroStatus').change(carregarHistorico);
        });
        
        function carregarEstatisticas() {
            $.ajax({
                url: baseUrl + '/api/frota/meu_historico.php',
                data: { estatisticas: 1 },
                success: function(data) {
                    if (data.status === 'ok') {
                        $('#totalViagens').text(data.estatisticas.total_viagens);
                        $('#kmTotal').text(data.estatisticas.km_total.toLocaleString());
                        $('#tempoTotal').text(data.estatisticas.tempo_total);
                        $('#mesAtual').text(data.estatisticas.mes_atual);
                        if (data.estatisticas.valor_total_locacao !== undefined) {
                            $('#valorTotalLocacao').text('R$ ' + data.estatisticas.valor_total_locacao.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
                            $('#valorKmInfo').text('R$ ' + data.estatisticas.valor_km.toFixed(2).replace('.', ',') + '/km');
                        }
                    }
                }
            });
        }
        
        function carregarHistorico() {
            const periodo = $('#filtroPeriodo').val();
            const status = $('#filtroStatus').val();
            
            $.ajax({
                url: baseUrl + '/api/frota/meu_historico.php',
                data: { dias: periodo, status: status },
                success: function(data) {
                    if (data.status === 'ok') {
                        renderizarHistorico(data.utilizacoes);
                    }
                }
            });
        }
        
        function renderizarHistorico(utilizacoes) {
            const container = $('#listaUtilizacoes');
            
            if (utilizacoes.length === 0) {
                container.empty();
                $('#semResultados').show();
                return;
            }
            
            $('#semResultados').hide();
            let html = '';
            
            utilizacoes.forEach(u => {
                const statusColor = {
                    'finalizado': 'bg-emerald-100 text-emerald-800',
                    'em_andamento': 'bg-amber-100 text-amber-800',
                    'cancelado': 'bg-red-100 text-red-800'
                }[u.status] || 'bg-slate-100 text-slate-700';

                const statusText = {
                    'finalizado': 'Finalizado',
                    'em_andamento': 'Em Andamento',
                    'cancelado': 'Cancelado'
                }[u.status] || u.status;
                
                const kmPercorrido = u.km_percorrido !== null ? u.km_percorrido.toLocaleString() + ' km' : '-';
                const tempoUso = u.tempo_formatado || '-';
                const valorKmFmt = u.valor_km ? 'R$ ' + u.valor_km.toFixed(2).replace('.', ',') + '/km' : '-';
                const valorLocacaoFmt = u.valor_locacao !== null ? 'R$ ' + u.valor_locacao.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '-';
                
                html += `
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow mb-4 overflow-hidden">
                        <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex justify-between items-center flex-wrap gap-2">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="bg-slate-100 border border-slate-200 px-3 py-1 rounded-lg font-mono text-sm font-bold text-slate-700">${u.placa}</span>
                                <div>
                                    <span class="font-bold text-slate-900">${u.modelo}</span>
                                    <span class="text-slate-500 text-sm ml-1">${u.marca}</span>
                                    <span class="ml-2 text-[10px] text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full font-bold">${valorKmFmt}</span>
                                    ${u.departamento ? `<span class="ml-1 text-[10px] text-teal-700 bg-teal-50 border border-teal-200 px-2 py-0.5 rounded-full font-bold">${u.departamento}</span>` : ''}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="${statusColor} text-[10px] font-bold px-2 py-1 rounded-md uppercase tracking-widest">${statusText}</span>
                                <a href="${baseUrl}/frota/detalhes.php?id=${u.id}" class="px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition-colors no-underline">Ver Detalhes</a>
                            </div>
                        </div>
                        <div class="px-6 py-4 grid grid-cols-2 md:grid-cols-6 gap-4">
                            <div>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-0.5">Saída</p>
                                <p class="text-sm font-semibold text-slate-800">${u.data_saida_formatada}</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-0.5">Entrada</p>
                                <p class="text-sm font-semibold text-slate-800">${u.data_entrada_formatada || '-'}</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-0.5">KM Percorrido</p>
                                <p class="text-sm font-semibold text-emerald-700">${kmPercorrido}</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-0.5">Tempo</p>
                                <p class="text-sm font-semibold text-cyan-700">${tempoUso}</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-0.5">Destino</p>
                                <p class="text-sm font-semibold text-slate-800">${u.destino || '-'}</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-0.5">Valor Locação</p>
                                <p class="text-sm font-extrabold text-green-700">${valorLocacaoFmt}</p>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.html(html);
        }
    </script>
</body>
</html>
