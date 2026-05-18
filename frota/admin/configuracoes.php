<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../../auth/verifica_sessao.php');

require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('frota_configuracoes');

require_once __DIR__ . '/../../api/conexao.php';

$valor_km = '0.00';
$sql = "SELECT valor FROM frota_configuracoes WHERE chave = 'valor_km' LIMIT 1";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $valor_km = $row['valor'];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Frota</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#00685c",
                        "primary-container": "#198374",
                        "on-primary": "#ffffff",
                        "background": "#f1f5f9",
                        "on-surface": "#0f172a",
                        "surface-container-low": "#f8fafc",
                        "surface-container": "#e2e8f0",
                        "surface-container-high": "#e2e8f0",
                        "surface-container-highest": "#cbd5e1",
                        "outline": "#6d7a77",
                        "outline-variant": "#bcc9c5",
                        "error": "#ba1a1a",
                        "error-container": "#ffdad6",
                        "on-surface-variant": "#3d4946",
                        "secondary": "#795900",
                        "secondary-container": "#fec330"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Inter", "sans-serif"],
                        "label": ["Inter", "sans-serif"]
                    },
                    borderRadius: { "DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem" }
                }
            }
        }
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-headline { font-family: 'Manrope', sans-serif; }
    </style>
</head>
<body class="bg-background text-on-surface min-h-screen">

    <!-- Header -->
    <header class="flex justify-between items-center w-full px-6 py-3 sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div class="flex items-center gap-8">
            <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="text-xl font-bold tracking-tight text-primary font-headline">Frota AOM</a>
            <nav class="hidden md:flex items-center gap-6">
                <a class="text-slate-600 font-medium hover:text-primary transition-colors duration-200 font-label text-sm" href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>">Dashboard</a>
                <a class="text-amber-600 font-bold border-b-2 border-amber-600 pb-1 font-label text-sm" href="#">Configurações</a>
            </nav>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="flex items-center gap-2 px-4 py-2 bg-white text-slate-700 font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all shadow-sm text-sm">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                <span>Voltar</span>
            </a>
        </div>
    </header>

    <main class="max-w-[1440px] mx-auto p-6 lg:p-10">

        <!-- Module Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-6">
            <div>
                <div class="flex items-center gap-2 text-amber-600 mb-2">
                    <span class="material-symbols-outlined">settings</span>
                    <span class="font-label font-semibold text-sm tracking-wider uppercase">Administração de Frota</span>
                </div>
                <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight mb-1 font-headline">Configurações</h1>
                <p class="text-slate-600 font-body">Gerencie os parâmetros do módulo de frota.</p>
            </div>
        </div>

        <!-- Valor do KM -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center gap-3">
                    <span class="material-symbols-outlined text-amber-600">paid</span>
                    <h2 class="text-lg font-bold text-slate-900 font-headline">Valor do Quilômetro</h2>
                </div>
                <div class="p-6">
                    <p class="text-slate-600 text-sm mb-6">
                        Defina o valor cobrado por quilômetro rodado. Este valor será utilizado para calcular o custo de locação em todo o módulo de frota (histórico, detalhes, comprovantes e relatórios).
                    </p>

                    <div class="flex items-center gap-4 mb-6">
                        <div class="flex-1">
                            <label for="valorKm" class="block text-sm font-semibold text-slate-700 mb-2">Valor por KM (R$)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-semibold">R$</span>
                                <input type="number" 
                                       id="valorKm" 
                                       step="0.01" 
                                       min="0" 
                                       value="<?= htmlspecialchars($valor_km) ?>"
                                       class="w-full pl-12 pr-4 py-3 border border-slate-300 rounded-xl text-lg font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                            </div>
                        </div>
                    </div>

                    <button onclick="salvarValorKm()" id="btnSalvar" class="flex items-center gap-2 px-6 py-3 bg-amber-600 text-white font-semibold rounded-xl shadow-lg shadow-amber-600/20 hover:bg-amber-700 transition-all">
                        <span class="material-symbols-outlined text-[20px]">save</span>
                        <span>Salvar Configuração</span>
                    </button>

                    <div id="mensagem" class="mt-4 hidden"></div>
                </div>
            </div>

            <!-- Preview de cálculo -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary">calculate</span>
                    <h2 class="text-lg font-bold text-slate-900 font-headline">Simulação de Cálculo</h2>
                </div>
                <div class="p-6">
                    <p class="text-slate-600 text-sm mb-6">
                        Veja como o cálculo será aplicado nas utilizações de veículos.
                    </p>

                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <label for="kmSimulado" class="text-sm font-semibold text-slate-700 whitespace-nowrap">KM Rodado:</label>
                            <input type="number" 
                                   id="kmSimulado" 
                                   value="100" 
                                   min="0"
                                   oninput="calcularSimulacao()"
                                   class="w-32 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition">
                        </div>

                        <div class="bg-slate-50 rounded-xl p-5 border border-slate-200">
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase mb-1">KM Rodado</p>
                                    <p class="text-2xl font-extrabold text-slate-900 font-headline" id="simKm">100</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase mb-1">Valor/KM</p>
                                    <p class="text-2xl font-extrabold text-amber-600 font-headline" id="simValorKm">R$ 6,00</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase mb-1">Valor Total</p>
                                    <p class="text-2xl font-extrabold text-primary font-headline" id="simTotal">R$ 600,00</p>
                                </div>
                            </div>
                            <div class="mt-4 pt-4 border-t border-slate-200 text-center">
                                <p class="text-xs text-slate-500">
                                    <span id="simKm2">100</span> km × R$ <span id="simVkm2">6,00</span> = <strong class="text-primary" id="simTotal2">R$ 600,00</strong>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';

        function calcularSimulacao() {
            const km = parseFloat($('#kmSimulado').val()) || 0;
            const valorKm = parseFloat($('#valorKm').val()) || 0;
            const total = km * valorKm;

            $('#simKm').text(km.toLocaleString('pt-BR'));
            $('#simValorKm').text('R$ ' + valorKm.toFixed(2).replace('.', ','));
            $('#simTotal').text('R$ ' + total.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
            $('#simKm2').text(km.toLocaleString('pt-BR'));
            $('#simVkm2').text(valorKm.toFixed(2).replace('.', ','));
            $('#simTotal2').text('R$ ' + total.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
        }

        $('#valorKm').on('input', calcularSimulacao);

        function salvarValorKm() {
            const valor = $('#valorKm').val();
            if (!valor || parseFloat(valor) < 0) {
                exibirToast('Informe um valor válido', 'danger');
                return;
            }

            const btn = $('#btnSalvar');
            btn.prop('disabled', true).html('<svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Salvando...');

            $.ajax({
                url: baseUrl + '/api/frota/configuracoes.php',
                method: 'POST',
                data: { chave: 'valor_km', valor: valor },
                success: function(data) {
                    if (data.status === 'ok') {
                        exibirToast('Valor do KM atualizado com sucesso!', 'success');
                    } else {
                        exibirToast('Erro: ' + (data.mensagem || 'Erro desconhecido'), 'danger');
                    }
                    btn.prop('disabled', false).html('<span class="material-symbols-outlined text-[20px]">save</span> <span>Salvar Configuração</span>');
                },
                error: function() {
                    exibirToast('Erro ao salvar configuração', 'danger');
                    btn.prop('disabled', false).html('<span class="material-symbols-outlined text-[20px]">save</span> <span>Salvar Configuração</span>');
                }
            });
        }

        calcularSimulacao();
    </script>
</body>
</html>
