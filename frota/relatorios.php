<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('frota_relatorios');

require_once __DIR__ . '/../api/conexao.php';

// Buscar veículos para o filtro
$veiculos = [];
$sql_veiculos = "SELECT id, placa, modelo FROM frota_veiculos WHERE ativo = 1 ORDER BY modelo";
$result_veiculos = $conn->query($sql_veiculos);
while ($row = $result_veiculos->fetch_assoc()) {
    $veiculos[] = $row;
}

// Buscar usuários que já usaram veículos
$usuarios = [];
$sql_usuarios = "SELECT DISTINCT u.id, u.nome 
                 FROM usuarios u 
                 INNER JOIN frota_utilizacoes fu ON u.id = fu.id_usuario 
                 ORDER BY u.nome";
$result_usuarios = $conn->query($sql_usuarios);
while ($row = $result_usuarios->fetch_assoc()) {
    $usuarios[] = $row;
}

$mesAtual = date('m');
$anoAtual = date('Y');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Frota</title>
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
        .card-relatorio.selected { border-color: #7c3aed; background-color: #f5f3ff; }
    </style>
</head>
<body class="bg-background text-on-surface min-h-screen">

    <!-- Header Sticky -->
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-slate-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-primary flex items-center justify-center rounded-lg shrink-0">
                    <span class="material-symbols-outlined text-white text-lg" style="font-variation-settings: 'FILL' 1;">local_shipping</span>
                </div>
                <span class="text-lg font-extrabold text-[#00685c] font-headline">Frota AOM</span>
            </div>
            <nav class="flex items-center gap-1">
                <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-900 rounded-lg hover:bg-slate-100 transition-colors">Dashboard</a>
                <a href="#" class="px-4 py-2 text-sm font-semibold text-violet-600 border-b-2 border-violet-600 rounded-t-lg">Relatórios</a>
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Module Header -->
        <div class="mb-8">
            <div class="flex items-center gap-2 mb-2">
                <span class="material-symbols-outlined text-violet-600 text-lg">analytics</span>
                <span class="text-xs font-bold uppercase tracking-widest text-violet-600 font-label">Módulo de Frota</span>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <h1 class="text-4xl font-extrabold text-slate-900 font-headline leading-tight">Relatórios da Frota</h1>
                    <p class="text-slate-600 mt-1">Gere relatórios detalhados e exporte em PDF ou Excel</p>
                </div>
                <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors shadow-sm shrink-0">
                    <span class="material-symbols-outlined text-base">arrow_back</span>
                    Voltar
                </a>
            </div>
        </div>

        <!-- Tipos de Relatório -->
        <div class="mb-6">
            <h5 class="text-sm font-semibold text-slate-700 mb-3 font-headline">Selecione o Tipo de Relatório</h5>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="card-relatorio selected bg-white rounded-xl border-2 border-transparent p-5 text-center cursor-pointer transition-all hover:shadow-md hover:border-slate-200" data-tipo="geral" onclick="selecionarTipo('geral', this)">
                    <span class="material-symbols-outlined text-4xl text-violet-600 mb-2">bar_chart</span>
                    <h6 class="font-bold text-slate-900 text-sm">Geral</h6>
                    <p class="text-slate-500 text-xs mt-1">Todas as utilizações</p>
                </div>
                <div class="card-relatorio bg-white rounded-xl border-2 border-transparent p-5 text-center cursor-pointer transition-all hover:shadow-md hover:border-slate-200" data-tipo="veiculo" onclick="selecionarTipo('veiculo', this)">
                    <span class="material-symbols-outlined text-4xl text-cyan-600 mb-2">local_shipping</span>
                    <h6 class="font-bold text-slate-900 text-sm">Por Veículo</h6>
                    <p class="text-slate-500 text-xs mt-1">Histórico do veículo</p>
                </div>
                <div class="card-relatorio bg-white rounded-xl border-2 border-transparent p-5 text-center cursor-pointer transition-all hover:shadow-md hover:border-slate-200" data-tipo="usuario" onclick="selecionarTipo('usuario', this)">
                    <span class="material-symbols-outlined text-4xl text-emerald-600 mb-2">person</span>
                    <h6 class="font-bold text-slate-900 text-sm">Por Usuário</h6>
                    <p class="text-slate-500 text-xs mt-1">Utilizações do usuário</p>
                </div>
                <div class="card-relatorio bg-white rounded-xl border-2 border-transparent p-5 text-center cursor-pointer transition-all hover:shadow-md hover:border-slate-200" data-tipo="destino" onclick="selecionarTipo('destino', this)">
                    <span class="material-symbols-outlined text-4xl text-red-500 mb-2">location_on</span>
                    <h6 class="font-bold text-slate-900 text-sm">Por Destino</h6>
                    <p class="text-slate-500 text-xs mt-1">Agrupado por destino</p>
                </div>
                <div class="card-relatorio bg-white rounded-xl border-2 border-transparent p-5 text-center cursor-pointer transition-all hover:shadow-md hover:border-slate-200" data-tipo="km" onclick="selecionarTipo('km', this)">
                    <span class="material-symbols-outlined text-4xl text-amber-600 mb-2">speed</span>
                    <h6 class="font-bold text-slate-900 text-sm">Quilometragem</h6>
                    <p class="text-slate-500 text-xs mt-1">KM por veículo</p>
                </div>
                <div class="card-relatorio bg-white rounded-xl border-2 border-transparent p-5 text-center cursor-pointer transition-all hover:shadow-md hover:border-slate-200" data-tipo="estatisticas" onclick="selecionarTipo('estatisticas', this)">
                    <span class="material-symbols-outlined text-4xl text-violet-600 mb-2">pie_chart</span>
                    <h6 class="font-bold text-slate-900 text-sm">Estatísticas</h6>
                    <p class="text-slate-500 text-xs mt-1">Resumo geral</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Filtros -->
            <div class="lg:col-span-4">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h5 class="text-base font-bold text-slate-900 mb-4 font-headline flex items-center gap-2">
                        <span class="material-symbols-outlined text-violet-600 text-xl">filter_alt</span>
                        Filtros
                    </h5>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Período</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" id="dataInicio" value="<?= date('Y-m-01') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                            <input type="date" id="dataFim" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                        </div>
                    </div>

                    <div class="mb-4" id="filtroVeiculo">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Veículo</label>
                        <select id="veiculoId" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                            <option value="">Todos os veículos</option>
                            <?php foreach ($veiculos as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= $v['placa'] ?> - <?= $v['modelo'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4" id="filtroUsuario" style="display: none;">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Usuário</label>
                        <select id="usuarioId" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                            <option value="">Todos os usuários</option>
                            <?php foreach ($usuarios as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= $u['nome'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Status</label>
                        <select id="status" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                            <option value="">Todos</option>
                            <option value="finalizado">Finalizados</option>
                            <option value="em_andamento">Em Andamento</option>
                        </select>
                    </div>

                    <div class="border-t border-slate-200 pt-5 flex flex-col gap-2.5">
                        <button type="button" onclick="carregarPreview()" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-violet-600 hover:bg-violet-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm">
                            <span class="material-symbols-outlined text-lg">visibility</span>
                            Visualizar Prévia
                        </button>
                        <button type="button" onclick="exportarPDF()" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm">
                            <span class="material-symbols-outlined text-lg">picture_as_pdf</span>
                            Exportar PDF
                        </button>
                        <button type="button" onclick="exportarExcel()" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm">
                            <span class="material-symbols-outlined text-lg">table_view</span>
                            Exportar Excel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Preview -->
            <div class="lg:col-span-8">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                        <h5 class="text-base font-bold text-slate-900 font-headline flex items-center gap-2">
                            <span class="material-symbols-outlined text-violet-600 text-xl">description</span>
                            Prévia do Relatório
                        </h5>
                        <span id="totalRegistros" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-200 text-slate-600">0 registros</span>
                    </div>
                    <div class="p-6 max-h-[500px] overflow-y-auto" id="previewContent">
                        <div class="text-center py-12 text-slate-400">
                            <span class="material-symbols-outlined text-6xl mb-3 block">description</span>
                            <p class="text-sm">Clique em "Visualizar Prévia" para ver os dados</p>
                        </div>
                    </div>
                </div>

                <!-- Estatísticas Rápidas -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4" id="estatisticasRapidas" style="display: none;">
                    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center shadow-sm">
                        <div class="text-2xl font-extrabold text-violet-600 font-headline" id="statViagens">0</div>
                        <div class="text-xs text-slate-500 font-semibold mt-1">Viagens</div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center shadow-sm">
                        <div class="text-2xl font-extrabold text-emerald-600 font-headline" id="statKm">0</div>
                        <div class="text-xs text-slate-500 font-semibold mt-1">KM Total</div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center shadow-sm">
                        <div class="text-2xl font-extrabold text-cyan-600 font-headline" id="statVeiculos">0</div>
                        <div class="text-xs text-slate-500 font-semibold mt-1">Veículos</div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center shadow-sm">
                        <div class="text-2xl font-extrabold text-amber-600 font-headline" id="statUsuarios">0</div>
                        <div class="text-xs text-slate-500 font-semibold mt-1">Usuários</div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';
        let tipoSelecionado = 'geral';
        
        function selecionarTipo(tipo, elemento) {
            tipoSelecionado = tipo;
            
            $('.card-relatorio').removeClass('selected');
            $(elemento).addClass('selected');
            
            if (tipo === 'veiculo' || tipo === 'km') {
                $('#filtroVeiculo').show();
                $('#filtroUsuario').hide();
            } else if (tipo === 'usuario') {
                $('#filtroVeiculo').hide();
                $('#filtroUsuario').show();
            } else {
                $('#filtroVeiculo').show();
                $('#filtroUsuario').show();
            }
        }
        
        function getParams() {
            return {
                tipo: tipoSelecionado,
                data_inicio: $('#dataInicio').val(),
                data_fim: $('#dataFim').val(),
                veiculo_id: $('#veiculoId').val(),
                usuario_id: $('#usuarioId').val(),
                status: $('#status').val()
            };
        }
        
        function carregarPreview() {
            const params = getParams();
            
            $('#previewContent').html(`
                <div class="text-center py-12">
                    <div class="inline-block w-8 h-8 border-4 border-violet-200 border-t-violet-600 rounded-full animate-spin mb-3"></div>
                    <p class="text-slate-400 text-sm">Carregando dados...</p>
                </div>
            `);
            
            $.ajax({
                url: baseUrl + '/api/frota/relatorio_preview.php',
                method: 'GET',
                data: params,
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        renderizarPreview(data);
                        $('#estatisticasRapidas').css('display', 'grid');
                        $('#statViagens').text(data.estatisticas.total_viagens);
                        $('#statKm').text(data.estatisticas.km_total.toLocaleString());
                        $('#statVeiculos').text(data.estatisticas.veiculos_unicos);
                        $('#statUsuarios').text(data.estatisticas.usuarios_unicos);
                        $('#totalRegistros').text(data.dados.length + ' registros');
                    } else {
                        $('#previewContent').html(`
                            <div class="text-center py-12 text-red-500">
                                <span class="material-symbols-outlined text-6xl mb-3 block">error</span>
                                <p class="text-sm">${data.mensagem || 'Erro ao carregar dados'}</p>
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#previewContent').html(`
                        <div class="text-center py-12 text-red-500">
                            <span class="material-symbols-outlined text-6xl mb-3 block">error</span>
                            <p class="text-sm">Erro ao carregar dados</p>
                        </div>
                    `);
                }
            });
        }
        
        function renderizarPreview(data) {
            let html = '<div class="overflow-x-auto"><table class="w-full text-sm">';
            
            if (tipoSelecionado === 'estatisticas') {
                html = renderizarEstatisticas(data);
            } else if (tipoSelecionado === 'destino') {
                html += `
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider">
                            <th class="px-4 py-3 text-left font-semibold">Destino</th>
                            <th class="px-4 py-3 text-left font-semibold">Viagens</th>
                            <th class="px-4 py-3 text-left font-semibold">KM Total</th>
                            <th class="px-4 py-3 text-left font-semibold">Tempo Total</th>
                        </tr>
                    </thead>
                    <tbody>
                `;
                data.dados.forEach(d => {
                    html += `
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3 border-b border-slate-100 font-semibold text-slate-900">${d.destino || 'Não informado'}</td>
                            <td class="px-4 py-3 border-b border-slate-100 text-slate-600">${d.total_viagens}</td>
                            <td class="px-4 py-3 border-b border-slate-100 text-slate-600">${parseInt(d.km_total).toLocaleString()} km</td>
                            <td class="px-4 py-3 border-b border-slate-100 text-slate-600">${d.tempo_formatado}</td>
                        </tr>
                    `;
                });
            } else if (tipoSelecionado === 'km') {
                html += `
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider">
                            <th class="px-4 py-3 text-left font-semibold">Veículo</th>
                            <th class="px-4 py-3 text-left font-semibold">KM Inicial</th>
                            <th class="px-4 py-3 text-left font-semibold">KM Final</th>
                            <th class="px-4 py-3 text-left font-semibold">KM Percorrido</th>
                            <th class="px-4 py-3 text-left font-semibold">Viagens</th>
                        </tr>
                    </thead>
                    <tbody>
                `;
                data.dados.forEach(d => {
                    html += `
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3 border-b border-slate-100 font-semibold text-slate-900">${d.placa} <span class="text-slate-500 font-normal">- ${d.modelo}</span></td>
                            <td class="px-4 py-3 border-b border-slate-100 text-slate-600">${parseInt(d.km_inicial).toLocaleString()}</td>
                            <td class="px-4 py-3 border-b border-slate-100 text-slate-600">${parseInt(d.km_final).toLocaleString()}</td>
                            <td class="px-4 py-3 border-b border-slate-100 text-emerald-600 font-bold">${parseInt(d.km_percorrido).toLocaleString()} km</td>
                            <td class="px-4 py-3 border-b border-slate-100 text-slate-600">${d.total_viagens}</td>
                        </tr>
                    `;
                });
            } else {
                html += `
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider">
                            <th class="px-4 py-3 text-left font-semibold">Data</th>
                            <th class="px-4 py-3 text-left font-semibold">Veículo</th>
                            <th class="px-4 py-3 text-left font-semibold">Usuário</th>
                            <th class="px-4 py-3 text-left font-semibold">Destino</th>
                            <th class="px-4 py-3 text-left font-semibold">KM</th>
                            <th class="px-4 py-3 text-left font-semibold">Tempo</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-center font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                `;
                data.dados.forEach(d => {
                    const statusBadge = d.status === 'finalizado' 
                        ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Finalizado</span>'
                        : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Em Andamento</span>';
                    html += `
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3 border-b border-slate-100 text-slate-600">${d.data_saida_formatada}</td>
                            <td class="px-4 py-3 border-b border-slate-100 font-semibold text-slate-900">${d.placa}</td>
                            <td class="px-4 py-3 border-b border-slate-100 text-slate-600">${d.usuario_nome}</td>
                            <td class="px-4 py-3 border-b border-slate-100 text-slate-600">${d.destino || '-'}</td>
                            <td class="px-4 py-3 border-b border-slate-100 text-slate-600">${d.km_percorrido ? d.km_percorrido.toLocaleString() + ' km' : '-'}</td>
                            <td class="px-4 py-3 border-b border-slate-100 text-slate-600">${d.tempo_formatado || '-'}</td>
                            <td class="px-4 py-3 border-b border-slate-100">${statusBadge}</td>
                            <td class="px-4 py-3 border-b border-slate-100 text-center">
                                <a href="${baseUrl}/frota/detalhes.php?id=${d.id}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-violet-600 hover:bg-violet-50 transition-colors" title="Ver Detalhes">
                                    <span class="material-symbols-outlined text-lg">visibility</span>
                                </a>
                            </td>
                        </tr>
                    `;
                });
            }
            
            html += '</tbody></table></div>';
            
            if (data.dados.length === 0) {
                html = `
                    <div class="text-center py-12 text-slate-400">
                        <span class="material-symbols-outlined text-6xl mb-3 block">inbox</span>
                        <p class="text-sm">Nenhum registro encontrado para os filtros selecionados</p>
                    </div>
                `;
            }
            
            $('#previewContent').html(html);
        }
        
        function renderizarEstatisticas(data) {
            const stats = data.estatisticas;
            return `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                        <div class="bg-violet-600 text-white px-4 py-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-lg">bar_chart</span>
                            <span class="font-bold text-sm">Resumo Geral</span>
                        </div>
                        <div class="p-4">
                            <table class="w-full text-sm">
                                <tr><td class="py-1.5 text-slate-600">Total de Viagens</td><td class="py-1.5 text-right font-bold text-slate-900">${stats.total_viagens}</td></tr>
                                <tr><td class="py-1.5 text-slate-600">KM Total Percorrido</td><td class="py-1.5 text-right font-bold text-slate-900">${stats.km_total.toLocaleString()} km</td></tr>
                                <tr><td class="py-1.5 text-slate-600">Média KM por Viagem</td><td class="py-1.5 text-right font-bold text-slate-900">${stats.media_km.toLocaleString()} km</td></tr>
                                <tr><td class="py-1.5 text-slate-600">Tempo Total de Uso</td><td class="py-1.5 text-right font-bold text-slate-900">${stats.tempo_total_formatado}</td></tr>
                                <tr><td class="py-1.5 text-slate-600">Veículos Utilizados</td><td class="py-1.5 text-right font-bold text-slate-900">${stats.veiculos_unicos}</td></tr>
                                <tr><td class="py-1.5 text-slate-600">Usuários Ativos</td><td class="py-1.5 text-right font-bold text-slate-900">${stats.usuarios_unicos}</td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                        <div class="bg-emerald-600 text-white px-4 py-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-lg">emoji_events</span>
                            <span class="font-bold text-sm">Top 5 Veículos (por KM)</span>
                        </div>
                        <div class="p-4">
                            <table class="w-full text-sm">
                                ${data.top_veiculos.map((v, i) => `
                                    <tr>
                                        <td class="py-1.5"><span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-200 text-slate-600 text-xs font-bold mr-2">${i+1}</span><span class="text-slate-700">${v.placa} - ${v.modelo}</span></td>
                                        <td class="py-1.5 text-right font-bold text-slate-900">${parseInt(v.km_total).toLocaleString()} km</td>
                                    </tr>
                                `).join('')}
                            </table>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                        <div class="bg-cyan-600 text-white px-4 py-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-lg">person</span>
                            <span class="font-bold text-sm">Top 5 Usuários (por Viagens)</span>
                        </div>
                        <div class="p-4">
                            <table class="w-full text-sm">
                                ${data.top_usuarios.map((u, i) => `
                                    <tr>
                                        <td class="py-1.5"><span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-200 text-slate-600 text-xs font-bold mr-2">${i+1}</span><span class="text-slate-700">${u.nome}</span></td>
                                        <td class="py-1.5 text-right font-bold text-slate-900">${u.total_viagens} viagens</td>
                                    </tr>
                                `).join('')}
                            </table>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                        <div class="bg-amber-500 text-white px-4 py-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-lg">location_on</span>
                            <span class="font-bold text-sm">Top 5 Destinos</span>
                        </div>
                        <div class="p-4">
                            <table class="w-full text-sm">
                                ${data.top_destinos.map((d, i) => `
                                    <tr>
                                        <td class="py-1.5"><span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-200 text-slate-600 text-xs font-bold mr-2">${i+1}</span><span class="text-slate-700">${d.destino || 'Não informado'}</span></td>
                                        <td class="py-1.5 text-right font-bold text-slate-900">${d.total} viagens</td>
                                    </tr>
                                `).join('')}
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function exportarPDF() {
            const params = getParams();
            const queryString = $.param(params);
            window.open(baseUrl + '/api/frota/exportar_pdf.php?' + queryString, '_blank');
        }
        
        function exportarExcel() {
            const params = getParams();
            const queryString = $.param(params);
            window.location.href = baseUrl + '/api/frota/exportar_excel.php?' + queryString;
        }
    </script>
</body>
</html>
