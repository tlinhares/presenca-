<?php
session_start();
require_once __DIR__ . '/../auth/verifica_sessao.php';
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('gerenciar_preferencias_notificacoes');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Preferências de Notificações - Gerenciamento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        .header-page {
            background: var(--primary-gradient);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .card-main {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .badge-ativo { background: #38a169; }
        .badge-inativo { background: #e53e3e; }
        .badge-configurado { background: #3182ce; }
        .badge-nao-configurado { background: #a0aec0; }
        
        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
        
        .empty-state {
            padding: 3rem;
            text-align: center;
            color: #718096;
        }
        
        .empty-state i {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .stats-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }
        
        .stats-card p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        
        .preferencia-check {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 4px;
            text-align: center;
            line-height: 20px;
            font-size: 0.75rem;
        }
        
        .preferencia-check.ativo {
            background: #38a169;
            color: white;
        }
        
        .preferencia-check.inativo {
            background: #e2e8f0;
            color: #718096;
        }
        
        .filtros {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        @media (max-width: 768px) {
            .hide-mobile {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <div class="header-page">
        <div class="container-fluid px-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div>
                        <h5 class="mb-0"><i class="bi bi-bell me-2"></i>Preferências de Notificações</h5>
                        <small class="opacity-75">Gerenciar preferências de notificação dos usuários</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card-main">
                    <!-- Estatísticas -->
                    <div class="row g-3 p-3">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <h3 id="total-usuarios">0</h3>
                                <p><i class="bi bi-people me-1"></i>Total de Usuários</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card" style="background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);">
                                <h3 id="total-configurados">0</h3>
                                <p><i class="bi bi-check-circle me-1"></i>Configurados</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card" style="background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%);">
                                <h3 id="total-ativos">0</h3>
                                <p><i class="bi bi-person-check me-1"></i>Usuários Ativos</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card" style="background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);">
                                <h3 id="total-inativos">0</h3>
                                <p><i class="bi bi-person-x me-1"></i>Usuários Inativos</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filtros -->
                    <div class="filtros">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Buscar usuário</label>
                                <input type="text" class="form-control" id="filtro-busca" placeholder="Nome ou email...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="filtro-ativo">
                                    <option value="">Todos</option>
                                    <option value="1">Ativos</option>
                                    <option value="0">Inativos</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button class="btn btn-primary w-100" onclick="carregarPreferencias()">
                                    <i class="bi bi-search me-1"></i>Buscar
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabela -->
                    <div id="tabela-container">
                        <div class="text-center p-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Preferências -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-bell me-2"></i>Editar Preferências</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="form-editar">
                        <input type="hidden" id="editar_usuario_id">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Usuário</label>
                            <p class="mb-0" id="editar_usuario_nome"></p>
                            <small class="text-muted" id="editar_usuario_email"></small>
                        </div>
                        
                        <hr>
                        
                        <h6 class="mb-3">Tipos de Notificação</h6>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editar_reserva_propria" name="notificar_reserva_propria">
                                <label class="form-check-label" for="editar_reserva_propria">
                                    <strong>Reserva Própria</strong>
                                    <br><small class="text-muted">Notificar quando o usuário fizer sua própria reserva</small>
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editar_reserva_adicional" name="notificar_reserva_adicional">
                                <label class="form-check-label" for="editar_reserva_adicional">
                                    <strong>Reserva Adicional</strong>
                                    <br><small class="text-muted">Notificar quando alguém fizer reserva adicional para o usuário</small>
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editar_reserva_multipla" name="notificar_reserva_multipla">
                                <label class="form-check-label" for="editar_reserva_multipla">
                                    <strong>Reserva Múltipla</strong>
                                    <br><small class="text-muted">Notificar quando houver reserva múltipla</small>
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editar_reserva_cancelada" name="notificar_reserva_cancelada">
                                <label class="form-check-label" for="editar_reserva_cancelada">
                                    <strong>Reserva Cancelada</strong>
                                    <br><small class="text-muted">Notificar quando uma reserva for cancelada</small>
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editar_lembrete_diario" name="notificar_lembrete_diario" checked>
                                <label class="form-check-label" for="editar_lembrete_diario">
                                    <strong>Lembrete Diário</strong>
                                    <br><small class="text-muted">Receber lembretes diários de reservas</small>
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarPreferencias()">
                        <i class="bi bi-check-lg me-1"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';
        let usuarios = [];
        let modalEditar = null;
        
        $(document).ready(function() {
            modalEditar = new bootstrap.Modal(document.getElementById('modalEditar'));
            carregarPreferencias();
            
            // Buscar ao pressionar Enter
            $('#filtro-busca').on('keypress', function(e) {
                if (e.which === 13) {
                    carregarPreferencias();
                }
            });
        });
        
        function carregarPreferencias() {
            const busca = $('#filtro-busca').val();
            const ativo = $('#filtro-ativo').val();
            
            const params = new URLSearchParams();
            if (busca) params.append('busca', busca);
            if (ativo !== '') params.append('ativo', ativo);
            
            $('#tabela-container').html(`
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            `);
            
            $.ajax({
                url: baseUrl + '/api/notificacao/listar_preferencias_admin.php?' + params.toString(),
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'sucesso') {
                        usuarios = data.dados;
                        renderizarTabela();
                        atualizarEstatisticas();
                    } else {
                        exibirToast('Erro ao carregar preferências: ' + (data.mensagem || 'Erro desconhecido'), 'danger');
                        $('#tabela-container').html('<div class="text-center p-5 text-danger">Erro ao carregar dados</div>');
                    }
                },
                error: function(xhr, status, error) {
                    exibirToast('Erro ao carregar preferências', 'danger');
                    $('#tabela-container').html('<div class="text-center p-5 text-danger">Erro ao carregar dados</div>');
                }
            });
        }
        
        function renderizarTabela() {
            const container = $('#tabela-container');
            
            if (usuarios.length === 0) {
                container.html(`
                    <div class="empty-state">
                        <i class="bi bi-bell-slash"></i>
                        <h5>Nenhum usuário encontrado</h5>
                        <p>Tente ajustar os filtros de busca</p>
                    </div>
                `);
                return;
            }
            
            let html = `
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 40px;">Status</th>
                                <th>Usuário</th>
                                <th class="text-center">Reserva Própria</th>
                                <th class="text-center">Reserva Adicional</th>
                                <th class="text-center">Reserva Múltipla</th>
                                <th class="text-center">Reserva Cancelada</th>
                                <th class="text-center">Lembrete Diário</th>
                                <th class="text-center hide-mobile">Configurado</th>
                                <th style="width: 100px;" class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            usuarios.forEach(usuario => {
                const pref = usuario.preferencias;
                html += `
                    <tr class="${!usuario.ativo ? 'table-secondary' : ''}">
                        <td>
                            <span class="badge ${usuario.ativo ? 'badge-ativo' : 'badge-inativo'}">
                                ${usuario.ativo ? 'Ativo' : 'Inativo'}
                            </span>
                        </td>
                        <td>
                            <div>
                                <strong>${escapeHtml(usuario.nome)}</strong>
                                <br><small class="text-muted">${escapeHtml(usuario.email)}</small>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="preferencia-check ${pref.notificar_reserva_propria ? 'ativo' : 'inativo'}">
                                ${pref.notificar_reserva_propria ? '<i class="bi bi-check"></i>' : '<i class="bi bi-x"></i>'}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="preferencia-check ${pref.notificar_reserva_adicional ? 'ativo' : 'inativo'}">
                                ${pref.notificar_reserva_adicional ? '<i class="bi bi-check"></i>' : '<i class="bi bi-x"></i>'}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="preferencia-check ${pref.notificar_reserva_multipla ? 'ativo' : 'inativo'}">
                                ${pref.notificar_reserva_multipla ? '<i class="bi bi-check"></i>' : '<i class="bi bi-x"></i>'}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="preferencia-check ${pref.notificar_reserva_cancelada ? 'ativo' : 'inativo'}">
                                ${pref.notificar_reserva_cancelada ? '<i class="bi bi-check"></i>' : '<i class="bi bi-x"></i>'}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="preferencia-check ${pref.notificar_lembrete_diario ? 'ativo' : 'inativo'}">
                                ${pref.notificar_lembrete_diario ? '<i class="bi bi-check"></i>' : '<i class="bi bi-x"></i>'}
                            </span>
                        </td>
                        <td class="text-center hide-mobile">
                            <span class="badge ${usuario.configurado ? 'badge-configurado' : 'badge-nao-configurado'}">
                                ${usuario.configurado ? 'Sim' : 'Não'}
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-primary btn-action" onclick="editarPreferencias(${usuario.id})" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            container.html(html);
        }
        
        function atualizarEstatisticas() {
            const total = usuarios.length;
            const configurados = usuarios.filter(u => u.configurado).length;
            const ativos = usuarios.filter(u => u.ativo).length;
            const inativos = usuarios.filter(u => !u.ativo).length;
            
            $('#total-usuarios').text(total);
            $('#total-configurados').text(configurados);
            $('#total-ativos').text(ativos);
            $('#total-inativos').text(inativos);
        }
        
        function editarPreferencias(usuarioId) {
            const usuario = usuarios.find(u => u.id === usuarioId);
            if (!usuario) {
                exibirToast('Usuário não encontrado', 'danger');
                return;
            }
            
            $('#editar_usuario_id').val(usuario.id);
            $('#editar_usuario_nome').text(usuario.nome);
            $('#editar_usuario_email').text(usuario.email);
            
            const pref = usuario.preferencias;
            $('#editar_reserva_propria').prop('checked', pref.notificar_reserva_propria);
            $('#editar_reserva_adicional').prop('checked', pref.notificar_reserva_adicional);
            $('#editar_reserva_multipla').prop('checked', pref.notificar_reserva_multipla);
            $('#editar_reserva_cancelada').prop('checked', pref.notificar_reserva_cancelada);
            $('#editar_lembrete_diario').prop('checked', pref.notificar_lembrete_diario);
            
            modalEditar.show();
        }
        
        function salvarPreferencias() {
            const usuarioId = $('#editar_usuario_id').val();
            const dados = {
                usuario_id: usuarioId,
                notificar_reserva_propria: $('#editar_reserva_propria').is(':checked') ? 1 : 0,
                notificar_reserva_adicional: $('#editar_reserva_adicional').is(':checked') ? 1 : 0,
                notificar_reserva_multipla: $('#editar_reserva_multipla').is(':checked') ? 1 : 0,
                notificar_reserva_cancelada: $('#editar_reserva_cancelada').is(':checked') ? 1 : 0,
                notificar_lembrete_diario: $('#editar_lembrete_diario').is(':checked') ? 1 : 0
            };
            
            $.ajax({
                url: baseUrl + '/api/notificacao/atualizar_preferencias_admin.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(dados),
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'sucesso') {
                        exibirToast('Preferências atualizadas com sucesso!', 'success');
                        modalEditar.hide();
                        carregarPreferencias();
                    } else {
                        exibirToast('Erro ao salvar: ' + (data.mensagem || 'Erro desconhecido'), 'danger');
                    }
                },
                error: function(xhr) {
                    let mensagem = 'Erro ao salvar preferências';
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.mensagem) mensagem = data.mensagem;
                    } catch(e) {}
                    exibirToast(mensagem, 'danger');
                }
            });
        }
        
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
        }
    </script>
</body>
</html>
