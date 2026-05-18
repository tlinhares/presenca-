<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../../auth/verifica_sessao.php');

require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('frota_departamentos');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departamentos - Frota</title>
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
                        "secondary-container": "#fec330",
                        "primary-fixed": "#97f3e2",
                        "on-primary-fixed": "#00201b"
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
                <a class="text-teal-600 font-bold border-b-2 border-teal-600 pb-1 font-label text-sm" href="#">Departamentos</a>
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
                <div class="flex items-center gap-2 text-teal-600 mb-2">
                    <span class="material-symbols-outlined">apartment</span>
                    <span class="font-label font-semibold text-sm tracking-wider uppercase">Administração de Frota</span>
                </div>
                <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight mb-1 font-headline">Departamentos</h1>
                <p class="text-slate-600 font-body">Gerencie os departamentos disponíveis para as retiradas de veículos.</p>
            </div>
            <div>
                <button onclick="abrirModal()" class="flex items-center gap-2 px-5 py-3 bg-primary text-on-primary font-semibold rounded-xl shadow-lg shadow-primary/20 hover:brightness-110 transition-all">
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    <span>Novo Departamento</span>
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-5 rounded-xl border border-slate-200 border-l-4 border-l-primary shadow-sm">
                <p class="text-slate-500 font-label text-sm font-semibold mb-1">Total</p>
                <h3 class="text-3xl font-extrabold text-slate-900 font-headline" id="statTotal">0</h3>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 border-l-4 border-l-primary-fixed shadow-sm">
                <p class="text-slate-500 font-label text-sm font-semibold mb-1">Ativos</p>
                <h3 class="text-3xl font-extrabold text-slate-900 font-headline" id="statAtivos">0</h3>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 border-l-4 border-l-error shadow-sm">
                <p class="text-slate-500 font-label text-sm font-semibold mb-1">Inativos</p>
                <h3 class="text-3xl font-extrabold text-slate-900 font-headline" id="statInativos">0</h3>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-teal-600">list</span>
                    <h2 class="text-lg font-bold text-slate-900 font-headline">Lista de Departamentos</h2>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/50">
                            <th class="text-left px-6 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider">Nome</th>
                            <th class="text-left px-6 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider">Descrição</th>
                            <th class="text-center px-6 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider">Status</th>
                            <th class="text-center px-6 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider">Criado em</th>
                            <th class="text-center px-6 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaDepartamentos">
                        <tr>
                            <td colspan="5" class="text-center py-12 text-slate-400">
                                <span class="material-symbols-outlined text-4xl mb-2 block">hourglass_empty</span>
                                Carregando...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal -->
    <div id="modalDepartamento" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="fecharModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg relative">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                    <h3 class="text-lg font-bold text-slate-900 font-headline" id="modalTitulo">Novo Departamento</h3>
                    <button onclick="fecharModal()" class="p-1 hover:bg-slate-100 rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-slate-400">close</span>
                    </button>
                </div>
                <form id="formDepartamento" class="p-6 space-y-5">
                    <input type="hidden" id="depId" value="0">
                    <div>
                        <label for="depNome" class="block text-sm font-semibold text-slate-700 mb-2">Nome <span class="text-red-500">*</span></label>
                        <input type="text" id="depNome" required maxlength="150"
                               class="w-full px-4 py-3 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition"
                               placeholder="Ex: Logística, Administrativo...">
                    </div>
                    <div>
                        <label for="depDescricao" class="block text-sm font-semibold text-slate-700 mb-2">Descrição</label>
                        <textarea id="depDescricao" rows="3" maxlength="255"
                                  class="w-full px-4 py-3 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition resize-none"
                                  placeholder="Descrição breve do departamento..."></textarea>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" id="depAtivo" checked
                               class="w-4 h-4 text-primary border-slate-300 rounded focus:ring-primary">
                        <label for="depAtivo" class="text-sm font-medium text-slate-700">Ativo</label>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="fecharModal()"
                                class="flex-1 px-4 py-3 bg-slate-100 text-slate-700 font-semibold rounded-xl hover:bg-slate-200 transition-all">
                            Cancelar
                        </button>
                        <button type="submit" id="btnSalvar"
                                class="flex-1 px-4 py-3 bg-primary text-on-primary font-semibold rounded-xl shadow-lg shadow-primary/20 hover:brightness-110 transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">save</span>
                            <span>Salvar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';

        $(document).ready(function() {
            carregarDepartamentos();
        });

        function carregarDepartamentos() {
            $.ajax({
                url: baseUrl + '/api/frota/departamentos.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        renderizarTabela(data.departamentos);
                        atualizarStats(data.departamentos);
                    }
                },
                error: function() {
                    $('#tabelaDepartamentos').html('<tr><td colspan="5" class="text-center py-8 text-red-500">Erro ao carregar departamentos</td></tr>');
                }
            });
        }

        function atualizarStats(deps) {
            const total = deps.length;
            const ativos = deps.filter(d => d.ativo === 1).length;
            const inativos = total - ativos;
            $('#statTotal').text(total);
            $('#statAtivos').text(ativos);
            $('#statInativos').text(inativos);
        }

        function renderizarTabela(deps) {
            if (!deps.length) {
                $('#tabelaDepartamentos').html(`
                    <tr>
                        <td colspan="5" class="text-center py-12 text-slate-400">
                            <span class="material-symbols-outlined text-4xl mb-2 block">folder_off</span>
                            Nenhum departamento cadastrado
                        </td>
                    </tr>
                `);
                return;
            }

            let html = '';
            deps.forEach(function(d) {
                const statusBadge = d.ativo
                    ? '<span class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-full"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>Ativo</span>'
                    : '<span class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-50 text-red-600 text-xs font-bold rounded-full"><span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span>Inativo</span>';

                const nome = $('<div>').text(d.nome).html();
                const descricao = d.descricao ? $('<div>').text(d.descricao).html() : '<span class="text-slate-400">-</span>';

                html += `
                    <tr class="border-b border-slate-100 hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 font-semibold text-slate-900">${nome}</td>
                        <td class="px-6 py-4 text-slate-600">${descricao}</td>
                        <td class="px-6 py-4 text-center">${statusBadge}</td>
                        <td class="px-6 py-4 text-center text-slate-500 text-xs">${d.criado_em_fmt || '-'}</td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="editarDepartamento(${d.id}, '${nome.replace(/'/g, "\\'")}', '${(d.descricao || '').replace(/'/g, "\\'")}', ${d.ativo})"
                                        class="p-2 hover:bg-slate-100 rounded-lg transition-colors" title="Editar">
                                    <span class="material-symbols-outlined text-slate-600 text-[20px]">edit</span>
                                </button>
                                <button onclick="excluirDepartamento(${d.id}, '${nome.replace(/'/g, "\\'")}')"
                                        class="p-2 hover:bg-red-50 rounded-lg transition-colors" title="Excluir">
                                    <span class="material-symbols-outlined text-red-500 text-[20px]">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            $('#tabelaDepartamentos').html(html);
        }

        function abrirModal(titulo) {
            $('#modalTitulo').text(titulo || 'Novo Departamento');
            $('#depId').val(0);
            $('#depNome').val('');
            $('#depDescricao').val('');
            $('#depAtivo').prop('checked', true);
            $('#modalDepartamento').removeClass('hidden');
        }

        function fecharModal() {
            $('#modalDepartamento').addClass('hidden');
        }

        function editarDepartamento(id, nome, descricao, ativo) {
            $('#modalTitulo').text('Editar Departamento');
            $('#depId').val(id);
            $('#depNome').val(nome);
            $('#depDescricao').val(descricao);
            $('#depAtivo').prop('checked', ativo === 1);
            $('#modalDepartamento').removeClass('hidden');
        }

        function excluirDepartamento(id, nome) {
            if (!confirm('Deseja realmente excluir o departamento "' + nome + '"?')) return;

            $.ajax({
                url: baseUrl + '/api/frota/departamentos.php?id=' + id,
                method: 'DELETE',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        exibirToast(data.mensagem, 'success');
                        carregarDepartamentos();
                    } else {
                        exibirToast('Erro: ' + (data.mensagem || 'Erro desconhecido'), 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro ao excluir departamento', 'danger');
                }
            });
        }

        $('#formDepartamento').submit(function(e) {
            e.preventDefault();

            const btn = $('#btnSalvar');
            btn.prop('disabled', true).html('<svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Salvando...');

            $.ajax({
                url: baseUrl + '/api/frota/departamentos.php',
                method: 'POST',
                data: {
                    id: $('#depId').val(),
                    nome: $('#depNome').val(),
                    descricao: $('#depDescricao').val(),
                    ativo: $('#depAtivo').is(':checked') ? 1 : 0
                },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        exibirToast(data.mensagem, 'success');
                        fecharModal();
                        carregarDepartamentos();
                    } else {
                        exibirToast('Erro: ' + (data.mensagem || 'Erro desconhecido'), 'danger');
                    }
                    btn.prop('disabled', false).html('<span class="material-symbols-outlined text-[20px]">save</span> <span>Salvar</span>');
                },
                error: function() {
                    exibirToast('Erro ao salvar departamento', 'danger');
                    btn.prop('disabled', false).html('<span class="material-symbols-outlined text-[20px]">save</span> <span>Salvar</span>');
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') fecharModal();
        });
    </script>
</body>
</html>
