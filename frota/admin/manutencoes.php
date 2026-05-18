<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../../auth/verifica_sessao.php');

require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('frota_admin_manutencoes');

require_once __DIR__ . '/../../api/conexao.php';

// Buscar veículos para o filtro
$veiculos = [];
$sql_veiculos = "SELECT id, placa, modelo FROM frota_veiculos WHERE ativo = 1 ORDER BY modelo";
$result_veiculos = $conn->query($sql_veiculos);
while ($row = $result_veiculos->fetch_assoc()) {
    $veiculos[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manutenções - Frota</title>
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
                        "tertiary-container": "#0077ce",
                        "on-error": "#ffffff",
                        "surface-variant": "#dfe3e7",
                        "background": "#f1f5f9",
                        "on-primary": "#ffffff",
                        "outline-variant": "#bcc9c5",
                        "error": "#ba1a1a",
                        "surface": "#f1f5f9",
                        "surface-container-lowest": "#ffffff",
                        "on-surface": "#0f172a",
                        "surface-container-high": "#e2e8f0",
                        "surface-container": "#e2e8f0",
                        "surface-container-low": "#f8fafc",
                        "surface-container-highest": "#cbd5e1",
                        "secondary-container": "#fec330",
                        "outline": "#6d7a77",
                        "error-container": "#ffdad6"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Inter", "sans-serif"],
                        "label": ["Inter", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-headline { font-family: 'Manrope', sans-serif; }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1055; overflow-y: auto; background-color: rgba(0,0,0,0.5); }
        .modal.show { display: flex; align-items: center; justify-content: center; }
        .modal-dialog { width: 100%; max-width: 800px; margin: 1.75rem auto; }
        .modal-dialog-centered { display: flex; align-items: center; min-height: calc(100% - 3.5rem); }
        .modal-content { position: relative; display: flex; flex-direction: column; background: white; border-radius: 1rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .modal-lg { max-width: 800px; }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; }
        .modal-backdrop { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; background-color: rgba(0,0,0,0.5); }
        .btn-close { background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #64748b; }
        .btn-close::after { content: '✕'; }
        .btn-close-white { color: white; }
        .fade { transition: opacity 0.15s linear; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 600; color: #334155; margin-bottom: 0.375rem; }
        .form-control, .form-select { width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; font-size: 0.875rem; outline: none; transition: border-color 0.15s; background: white; }
        .form-control:focus, .form-select:focus { border-color: #dc2626; box-shadow: 0 0 0 2px rgba(220,38,38,0.15); }
        textarea.form-control { resize: vertical; }
        .text-danger { color: #dc2626; }
        .row { display: grid; grid-template-columns: repeat(12, 1fr); gap: 0.75rem; }
        .col-md-3 { grid-column: span 3; }
        .col-md-4 { grid-column: span 4; }
        .col-md-6 { grid-column: span 6; }
        .col-12 { grid-column: span 12; }
        @media (max-width: 768px) { .col-md-3, .col-md-4, .col-md-6 { grid-column: span 12; } }
    </style>
</head>
<body class="bg-background text-on-surface min-h-screen">

    <!-- Header -->
    <header class="flex justify-between items-center w-full px-6 py-3 sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div class="flex items-center gap-8">
            <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="text-xl font-bold tracking-tight text-primary font-headline">Frota AOM</a>
            <nav class="hidden md:flex items-center gap-6">
                <a class="text-slate-600 font-medium hover:text-primary transition-colors duration-200 font-label" href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>">Dashboard</a>
                <a class="text-red-600 font-bold border-b-2 border-red-600 pb-1 font-label" href="#">Manutenções</a>
            </nav>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="flex items-center gap-2 px-4 py-2 bg-white text-slate-700 font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all shadow-sm text-sm">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                <span>Voltar</span>
            </a>
            <button onclick="novaManutencao()" data-bs-toggle="modal" data-bs-target="#modalManutencao" class="flex items-center gap-2 px-4 py-2 bg-red-600 text-white font-semibold rounded-xl shadow-lg shadow-red-600/20 hover:bg-red-700 transition-all text-sm">
                <span class="material-symbols-outlined text-[20px]">add</span>
                <span>Nova Manutenção</span>
            </button>
        </div>
    </header>

    <main class="max-w-[1440px] mx-auto p-6 lg:p-10">

        <!-- Module Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-6">
            <div>
                <div class="flex items-center gap-2 text-red-600 mb-2">
                    <span class="material-symbols-outlined">build</span>
                    <span class="font-label font-semibold text-sm tracking-wider uppercase">Administração de Frota</span>
                </div>
                <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight mb-1 font-headline">Manutenções</h1>
                <p class="text-slate-600 font-body">Registre e acompanhe manutenções dos veículos</p>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label for="filtroVeiculo" class="block text-sm font-semibold text-slate-700 mb-1.5">Veículo</label>
                    <select id="filtroVeiculo" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                        <option value="">Todos</option>
                        <?php foreach ($veiculos as $v): ?>
                        <option value="<?= $v['id'] ?>"><?= $v['placa'] ?> - <?= $v['modelo'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filtroTipo" class="block text-sm font-semibold text-slate-700 mb-1.5">Tipo</label>
                    <select id="filtroTipo" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                        <option value="">Todos</option>
                        <option value="preventiva">Preventiva</option>
                        <option value="corretiva">Corretiva</option>
                        <option value="revisao">Revisão</option>
                        <option value="troca_oleo">Troca de Óleo</option>
                        <option value="pneus">Pneus</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>
                <div>
                    <label for="filtroStatus" class="block text-sm font-semibold text-slate-700 mb-1.5">Status</label>
                    <select id="filtroStatus" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                        <option value="">Todos</option>
                        <option value="pendente">Pendente</option>
                        <option value="em_andamento">Em Andamento</option>
                        <option value="concluida">Concluída</option>
                    </select>
                </div>
                <div>
                    <button onclick="carregarManutencoes()" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 shadow-sm transition-colors">
                        <span class="material-symbols-outlined" style="font-size:18px;">search</span>
                        Filtrar
                    </button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Veículo</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Tipo</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Descrição</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Data</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">KM</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Valor</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="text-center px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider" style="width:120px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaManutencoes" class="divide-y divide-slate-100">
                        <tr>
                            <td colspan="8" class="text-center py-10 text-slate-400">
                                <div class="flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    <span>Carregando...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Manutenção -->
    <div class="modal fade" id="modalManutencao" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-red-600 text-white rounded-t-xl" style="border-bottom:none;">
                    <h5 class="modal-title flex items-center gap-2 text-lg font-bold font-manrope" id="modalManutencaoTitulo">
                        <span class="material-symbols-outlined" style="font-size:22px;">build</span>
                        Nova Manutenção
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formManutencao">
                    <div class="modal-body">
                        <input type="hidden" id="manutencaoId" name="id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Veículo <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_veiculo" name="id_veiculo" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($veiculos as $v): ?>
                                    <option value="<?= $v['id'] ?>"><?= $v['placa'] ?> - <?= $v['modelo'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tipo <span class="text-danger">*</span></label>
                                <select class="form-select" id="tipo" name="tipo" required>
                                    <option value="">Selecione...</option>
                                    <option value="preventiva">Preventiva</option>
                                    <option value="corretiva">Corretiva</option>
                                    <option value="revisao">Revisão</option>
                                    <option value="troca_oleo">Troca de Óleo</option>
                                    <option value="pneus">Pneus</option>
                                    <option value="outro">Outro</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descrição <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="descricao" name="descricao" rows="3" required placeholder="Descreva a manutenção realizada..."></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Data <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="data_manutencao" name="data_manutencao" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">KM</label>
                                <input type="number" class="form-control" id="km_manutencao" name="km_manutencao" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Valor (R$)</label>
                                <input type="number" class="form-control" id="valor" name="valor" step="0.01" min="0" placeholder="0,00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Próxima Revisão (Data)</label>
                                <input type="date" class="form-control" id="data_proxima_revisao" name="data_proxima_revisao">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Próxima Revisão (KM)</label>
                                <input type="number" class="form-control" id="km_proxima_revisao" name="km_proxima_revisao" min="0" placeholder="Ex: 150000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="pendente">Pendente</option>
                                    <option value="em_andamento">Em Andamento</option>
                                    <option value="concluida">Concluída</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 shadow-sm transition-colors" id="btnSalvar">
                            <span class="material-symbols-outlined" style="font-size:18px;">check</span>
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação -->
    <div class="modal fade" id="modalConfirmar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-red-600 text-white rounded-t-xl" style="border-bottom:none;">
                    <h5 class="modal-title flex items-center gap-2 text-lg font-bold font-manrope">
                        <span class="material-symbols-outlined" style="font-size:22px;">warning</span>
                        Confirmar Exclusão
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-slate-600">Tem certeza que deseja excluir esta manutenção?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 shadow-sm transition-colors" id="btnConfirmarExclusao">
                        <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
                        Excluir
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
        const modalManutencao = new bootstrap.Modal(document.getElementById('modalManutencao'));
        const modalConfirmar = new bootstrap.Modal(document.getElementById('modalConfirmar'));
        let manutencaoParaExcluir = null;
        
        $(document).ready(function() {
            carregarManutencoes();
            $('#data_manutencao').val(new Date().toISOString().split('T')[0]);
        });
        
        function carregarManutencoes() {
            const params = {
                veiculo_id: $('#filtroVeiculo').val(),
                tipo: $('#filtroTipo').val(),
                status: $('#filtroStatus').val()
            };
            
            $.ajax({
                url: baseUrl + '/api/frota/listar_manutencoes.php',
                data: params,
                success: function(data) {
                    if (data.status === 'ok') {
                        renderizarTabela(data.manutencoes);
                    }
                }
            });
        }
        
        function renderizarTabela(manutencoes) {
            const tbody = $('#tabelaManutencoes');
            
            if (manutencoes.length === 0) {
                tbody.html('<tr><td colspan="8" class="text-center py-10 text-slate-400"><div class="flex flex-col items-center gap-1"><span class="material-symbols-outlined" style="font-size:32px;">inbox</span><span>Nenhuma manutenção encontrada</span></div></td></tr>');
                return;
            }
            
            const tipoLabels = {
                'preventiva': 'Preventiva',
                'corretiva': 'Corretiva',
                'revisao': 'Revisão',
                'troca_oleo': 'Troca de Óleo',
                'pneus': 'Pneus',
                'outro': 'Outro'
            };

            const tipoClasses = {
                'preventiva': 'bg-cyan-50 text-cyan-700 border border-cyan-200',
                'corretiva': 'bg-red-50 text-red-700 border border-red-200',
                'revisao': 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'troca_oleo': 'bg-amber-50 text-amber-700 border border-amber-200',
                'pneus': 'bg-slate-100 text-slate-700 border border-slate-200',
                'outro': 'bg-gray-100 text-gray-600 border border-gray-200'
            };
            
            const statusLabels = {
                'pendente': 'Pendente',
                'em_andamento': 'Em Andamento',
                'concluida': 'Concluída'
            };

            const statusClasses = {
                'pendente': 'bg-amber-50 text-amber-700 border border-amber-200',
                'em_andamento': 'bg-blue-50 text-blue-700 border border-blue-200',
                'concluida': 'bg-emerald-50 text-emerald-700 border border-emerald-200'
            };
            
            let html = '';
            manutencoes.forEach(m => {
                html += `
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <span class="bg-slate-100 border border-slate-200 px-2 py-0.5 rounded font-mono text-xs font-bold text-slate-700">${m.placa}</span>
                            <span class="ml-2 text-slate-600 text-sm">${m.modelo}</span>
                        </td>
                        <td class="px-5 py-3.5"><span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold ${tipoClasses[m.tipo] || 'bg-gray-100 text-gray-600'}">${tipoLabels[m.tipo] || m.tipo}</span></td>
                        <td class="px-5 py-3.5 text-slate-600">${m.descricao.substring(0, 50)}${m.descricao.length > 50 ? '...' : ''}</td>
                        <td class="px-5 py-3.5 text-slate-600">${m.data_formatada}</td>
                        <td class="px-5 py-3.5 text-slate-600">${m.km_manutencao ? m.km_manutencao.toLocaleString() + ' km' : '-'}</td>
                        <td class="px-5 py-3.5 text-slate-700 font-medium">${m.valor ? 'R$ ' + parseFloat(m.valor).toFixed(2) : '-'}</td>
                        <td class="px-5 py-3.5"><span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold ${statusClasses[m.status] || 'bg-gray-100 text-gray-600'}">${statusLabels[m.status] || m.status}</span></td>
                        <td class="px-5 py-3.5 text-center">
                            <div class="inline-flex items-center gap-1">
                                <button onclick="editarManutencao(${m.id})" title="Editar" class="inline-flex items-center justify-center w-8 h-8 rounded-full text-blue-600 hover:bg-blue-50 transition-colors">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                                </button>
                                <button onclick="excluirManutencao(${m.id})" title="Excluir" class="inline-flex items-center justify-center w-8 h-8 rounded-full text-red-600 hover:bg-red-50 transition-colors">
                                    <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tbody.html(html);
        }
        
        function novaManutencao() {
            $('#manutencaoId').val('');
            $('#formManutencao')[0].reset();
            $('#data_manutencao').val(new Date().toISOString().split('T')[0]);
            $('#modalManutencaoTitulo').html('<span class="material-symbols-outlined" style="font-size:22px;">build</span> Nova Manutenção');
        }
        
        function editarManutencao(id) {
            $.get(baseUrl + '/api/frota/buscar_manutencao.php', { id: id }, function(data) {
                if (data.status === 'ok' && data.manutencao) {
                    const m = data.manutencao;
                    $('#manutencaoId').val(m.id);
                    $('#id_veiculo').val(m.id_veiculo);
                    $('#tipo').val(m.tipo);
                    $('#descricao').val(m.descricao);
                    $('#data_manutencao').val(m.data_manutencao);
                    $('#km_manutencao').val(m.km_manutencao);
                    $('#valor').val(m.valor);
                    $('#data_proxima_revisao').val(m.data_proxima_revisao);
                    $('#km_proxima_revisao').val(m.km_proxima_revisao);
                    $('#status').val(m.status);
                    $('#modalManutencaoTitulo').html('<span class="material-symbols-outlined" style="font-size:22px;">edit</span> Editar Manutenção');
                    modalManutencao.show();
                }
            });
        }
        
        function excluirManutencao(id) {
            manutencaoParaExcluir = id;
            modalConfirmar.show();
        }
        
        $('#btnConfirmarExclusao').click(function() {
            if (!manutencaoParaExcluir) return;
            
            $.ajax({
                url: baseUrl + '/api/frota/excluir_manutencao.php',
                method: 'POST',
                data: { id: manutencaoParaExcluir },
                success: function(data) {
                    modalConfirmar.hide();
                    if (data.status === 'ok') {
                        exibirToast('Manutenção excluída com sucesso!', 'success');
                        carregarManutencoes();
                    } else {
                        exibirToast('Erro: ' + (data.mensagem || 'Erro desconhecido'), 'danger');
                    }
                    manutencaoParaExcluir = null;
                }
            });
        });
        
        $('#formManutencao').submit(function(e) {
            e.preventDefault();
            
            const btn = $('#btnSalvar');
            btn.prop('disabled', true).html('<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Salvando...');
            
            $.ajax({
                url: baseUrl + '/api/frota/salvar_manutencao.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function(data) {
                    if (data.status === 'ok') {
                        modalManutencao.hide();
                        exibirToast(data.mensagem || 'Manutenção salva com sucesso!', 'success');
                        carregarManutencoes();
                    } else {
                        exibirToast('Erro: ' + (data.mensagem || 'Erro desconhecido'), 'danger');
                    }
                    btn.prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:18px;">check</span> Salvar');
                },
                error: function() {
                    exibirToast('Erro ao salvar manutenção', 'danger');
                    btn.prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:18px;">check</span> Salvar');
                }
            });
        });
    </script>
</body>
</html>
