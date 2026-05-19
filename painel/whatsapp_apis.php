<?php
session_start();
require_once __DIR__ . '/../auth/verifica_sessao.php';
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('gerenciar_whatsapp_apis');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>APIs WhatsApp - Gerenciamento</title>
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
        
        .nav-tabs {
            border-bottom: 2px solid #e2e8f0;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #718096;
            font-weight: 500;
            padding: 1rem 1.5rem;
        }
        
        .nav-tabs .nav-link.active {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            background: transparent;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .badge-ativo { background: #38a169; }
        .badge-inativo { background: #e53e3e; }
        .badge-status-ativa { background: #38a169; }
        .badge-status-inativa { background: #e53e3e; }
        .badge-status-conectando { background: #ed8936; }
        .badge-status-erro { background: #c53030; }
        .badge-status-desconhecido { background: #718096; }
        
        .status-checking {
            opacity: 0.6;
            pointer-events: none;
        }
        
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
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .stats-card .number {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .stats-card .label {
            font-size: 0.85rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="header-page">
        <div class="container-fluid px-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div>
                        <h5 class="mb-0"><i class="bi bi-whatsapp me-2"></i>APIs WhatsApp</h5>
                        <small class="opacity-75">Gerenciamento de APIs e configurações</small>
                    </div>
                </div>
                <a href="whatsapp_sessoes.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-broadcast me-1"></i>Sessões
                </a>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <div class="card-main">
            <!-- Tabs -->
            <ul class="nav nav-tabs" id="mainTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-apis" data-bs-toggle="tab" data-bs-target="#panel-apis" type="button" role="tab">
                        <i class="bi bi-gear me-2"></i>Cadastro de APIs
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-config" data-bs-toggle="tab" data-bs-target="#panel-config" type="button" role="tab">
                        <i class="bi bi-sliders me-2"></i>Configuração por Tipo
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content p-4" id="mainTabContent">
                <!-- Aba 1: Cadastro de APIs -->
                <div class="tab-pane fade show active" id="panel-apis" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">APIs Cadastradas</h5>
                        <button class="btn btn-primary btn-sm" onclick="abrirModalNovaAPI()">
                            <i class="bi bi-plus-lg me-1"></i>Nova API
                        </button>
                    </div>
                    
                    <div id="tabela-apis">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status"></div>
                        </div>
                    </div>
                </div>

                <!-- Aba 2: Configuração por Tipo -->
                <div class="tab-pane fade" id="panel-config" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Configuração por Tipo de Notificação</h5>
                        <button class="btn btn-success btn-sm" onclick="salvarConfiguracoes()">
                            <i class="bi bi-check-lg me-1"></i>Salvar Todas
                        </button>
                    </div>
                    
                    <div id="tabela-configs">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nova/Editar API -->
    <div class="modal fade" id="modalAPI" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                    <h5 class="modal-title" id="modalAPITitulo">
                        <i class="bi bi-plus-circle me-2"></i>Nova API
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formAPI">
                        <input type="hidden" id="api_id" name="id">

                        <div class="alert alert-info py-2 small mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Para uma sessão wppconnect nova, preencha apenas <b>Nome</b>, <b>Base URL</b>, <b>Session Name</b> e <b>Secret Key</b>. O sistema gera o token e monta as URLs automaticamente. Para conectar, salve e clique em <b>Sessões</b> no topo.
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="api_nome" name="nome" required maxlength="100" placeholder="Ex: atendimento, suporte">
                            <small class="text-muted">Rótulo interno para identificar a API no sistema</small>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-7">
                                <label class="form-label">Base URL <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" id="api_base_url" name="base_url" required placeholder="http://10.144.128.34:21465">
                                <small class="text-muted">Endereço do wppconnect-server (sem /api/...)</small>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Session Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="api_session_name" name="session_name" required maxlength="100" placeholder="atendimento">
                                <small class="text-muted">Nome da sessão no wppconnect</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Secret Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="api_secret_key" name="secret_key" required placeholder="wppconnect…">
                            <small class="text-muted">Chave global do wppconnect (campo <code>secretKey</code> do config.js do servidor)</small>
                        </div>

                        <div class="accordion mb-3" id="accAvancado">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colAvancado">
                                        <i class="bi bi-sliders me-2"></i>Avançado — Token e URLs manuais (opcional)
                                    </button>
                                </h2>
                                <div id="colAvancado" class="accordion-collapse collapse" data-bs-parent="#accAvancado">
                                    <div class="accordion-body">
                                        <div class="mb-3">
                                            <label class="form-label">Token / API Key</label>
                                            <input type="text" class="form-control" id="api_token" name="token" placeholder="Deixe vazio para gerar automaticamente">
                                            <small class="text-muted">Se vazio, o sistema chama <code>/{secret_key}/generate-token</code> na primeira utilização</small>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">URL API Mensagens</label>
                                                <input type="url" class="form-control" id="api_url_mensagem" name="url_mensagem" placeholder="auto: {base}/api/{session}/send-message">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">URL API Arquivos</label>
                                                <input type="url" class="form-control" id="api_url_arquivo" name="url_arquivo" placeholder="auto: {base}/api/{session}/send-file">
                                            </div>
                                        </div>
                                        <small class="text-muted">Deixe vazio para o sistema derivar de Base URL + Session Name</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Número WhatsApp</label>
                                <input type="text" class="form-control" id="api_numero" name="numero_whatsapp" maxlength="20" placeholder="Opcional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Prioridade</label>
                                <input type="number" class="form-control" id="api_prioridade" name="prioridade" value="0" min="0">
                                <small class="text-muted">Menor número = maior prioridade</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" id="api_observacoes" name="observacoes" rows="2"></textarea>
                        </div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="api_ativo" name="ativo" value="1" checked>
                            <label class="form-check-label" for="api_ativo">API ativa</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarAPI()">
                        <i class="bi bi-check-lg me-1"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Confirmação Exclusão -->
    <div class="modal fade" id="modalExcluir" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title"><i class="bi bi-trash me-2"></i>Excluir API</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <p>Deseja realmente excluir a API <strong id="excluir-nome"></strong>?</p>
                    <small class="text-danger">Esta ação não pode ser desfeita.</small>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmarExclusao()">
                        <i class="bi bi-trash me-1"></i>Excluir
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
        let apis = [];
        let configs = [];
        let apisDisponiveis = [];
        let excluirId = null;
        
        $(document).ready(function() {
            carregarAPIs();
            carregarConfiguracoes();
        });
        
        function carregarAPIs() {
            $.ajax({
                url: baseUrl + '/api/whatsapp_apis/listar.php',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        apis = data.apis;
                        // Carregar status automaticamente após carregar as APIs
                        carregarStatusSessoes();
                    } else {
                        exibirToast('Erro ao carregar APIs: ' + data.mensagem, 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro ao carregar APIs', 'danger');
                }
            });
        }
        
        function carregarStatusSessoes() {
            // Renderizar tabela primeiro (com status "verificando")
            renderizarTabelaAPIs();
            
            // Verificar status de todas as APIs
            $.ajax({
                url: baseUrl + '/api/whatsapp_apis/verificar_status_todos.php',
                dataType: 'json',
                timeout: 30000, // 30 segundos para verificar todas
                success: function(data) {
                    if (data.status === 'ok' && data.status_apis) {
                        // Atualizar status nas APIs
                        apis.forEach(function(api) {
                            const statusInfo = data.status_apis[api.id];
                            if (statusInfo) {
                                api.status_sessao = statusInfo.status_sessao;
                                api.status_mensagem = statusInfo.mensagem;
                                
                                // Debug: log quando status for desconhecido
                                if (statusInfo.status_sessao === 'desconhecido') {
                                    console.log('Status desconhecido para API ' + api.id + ':', statusInfo);
                                }
                            } else {
                                api.status_sessao = 'nao_verificado';
                                api.status_mensagem = 'Status não retornado';
                            }
                        });
                        // Re-renderizar tabela com os status atualizados
                        renderizarTabelaAPIs();
                    } else {
                        console.error('Erro ao verificar status:', data);
                        apis.forEach(function(api) {
                            api.status_sessao = 'erro';
                            api.status_mensagem = data.mensagem || 'Erro ao verificar status';
                        });
                        renderizarTabelaAPIs();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro na requisição de status:', xhr, status, error);
                    // Em caso de erro, marcar todas como erro
                    apis.forEach(function(api) {
                        api.status_sessao = 'erro';
                        api.status_mensagem = 'Erro ao verificar status: ' + (error || 'Erro desconhecido');
                    });
                    renderizarTabelaAPIs();
                }
            });
        }
        
        function renderizarTabelaAPIs() {
            const tbody = $('#tabela-apis');
            
            if (apis.length === 0) {
                tbody.html(`
                    <div class="empty-state">
                        <i class="bi bi-whatsapp"></i>
                        <h5>Nenhuma API cadastrada</h5>
                        <p>Clique em "Nova API" para cadastrar</p>
                    </div>
                `);
                return;
            }
            
            let html = `
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Status</th>
                                <th>Nome</th>
                                <th class="hide-mobile">Número</th>
                                <th class="text-center">Sessão WhatsApp</th>
                                <th class="text-center">Prioridade</th>
                                <th class="text-center hide-mobile">Estatísticas</th>
                                <th style="width: 150px;" class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            apis.forEach(api => {
                const taxaSucesso = api.total_envios + api.total_falhas > 0 
                    ? Math.round((api.total_envios / (api.total_envios + api.total_falhas)) * 100) 
                    : 0;
                
                // Status da sessão (será atualizado após verificação)
                const statusSessao = api.status_sessao || 'verificando';
                const statusSessaoClasses = {
                    'ativa': 'badge-status-ativa',
                    'inativa': 'badge-status-inativa',
                    'conectando': 'badge-status-conectando',
                    'erro': 'badge-status-erro',
                    'desconhecido': 'badge-status-desconhecido',
                    'verificando': 'badge bg-info',
                    'nao_verificado': 'badge bg-secondary'
                };
                const statusSessaoTextos = {
                    'ativa': 'Ativa',
                    'inativa': 'Inativa',
                    'conectando': 'Conectando',
                    'erro': 'Erro',
                    'desconhecido': 'Desconhecido',
                    'verificando': 'Verificando...',
                    'nao_verificado': 'Não verificado'
                };
                
                html += `
                    <tr class="${!api.ativo ? 'table-secondary' : ''}" id="row-api-${api.id}">
                        <td>
                            <span class="badge ${api.ativo ? 'badge-ativo' : 'badge-inativo'}">
                                ${api.ativo ? 'Ativa' : 'Inativa'}
                            </span>
                        </td>
                        <td>
                            <strong>${api.nome}</strong>
                            ${api.observacoes ? `<br><small class="text-muted">${api.observacoes}</small>` : ''}
                        </td>
                        <td class="hide-mobile">
                            <span class="badge bg-secondary">${api.numero_whatsapp || '-'}</span>
                        </td>
                        <td class="text-center">
                            <div id="status-sessao-${api.id}">
                                <span class="badge ${statusSessaoClasses[statusSessao] || statusSessaoClasses['nao_verificado']}">
                                    ${statusSessaoTextos[statusSessao] || statusSessaoTextos['nao_verificado']}
                                </span>
                                ${api.status_mensagem && statusSessao === 'erro' ? `<br><small class="text-danger">${api.status_mensagem}</small>` : ''}
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary">${api.prioridade}</span>
                        </td>
                        <td class="text-center hide-mobile">
                            <small>
                                <strong>${api.total_envios}</strong> envios<br>
                                <strong>${api.total_falhas}</strong> falhas<br>
                                <strong>${taxaSucesso}%</strong> sucesso
                            </small>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-outline-primary btn-action me-1" onclick="editarAPI(${parseInt(api.id)})" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-action" onclick="excluirAPI(${parseInt(api.id)}, '${api.nome.replace(/'/g, "\\'")}')" title="Excluir">
                                <i class="bi bi-trash"></i>
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
            
            tbody.html(html);
        }
        
        function abrirModalNovaAPI() {
            // Fechar modal existente se estiver aberto
            const modalExistente = bootstrap.Modal.getInstance('#modalAPI');
            if (modalExistente) {
                modalExistente.hide();
            }
            
            $('#formAPI')[0].reset();
            $('#api_id').val('');
            $('#api_ativo').prop('checked', true);
            $('#api_prioridade').val(0);
            $('#api_token').val('');
            $('#api_base_url').val('');
            $('#api_session_name').val('');
            $('#api_secret_key').val('');
            $('#colAvancado').removeClass('show'); // accordion fechado

            $('#modalAPITitulo').html('<i class="bi bi-plus-circle me-2"></i>Nova API');
            
            // Criar nova instância do modal e mostrar
            const modal = new bootstrap.Modal(document.getElementById('modalAPI'));
            modal.show();
        }
        
        function editarAPI(id) {
            // Converter ID para número
            const idNum = parseInt(id);
            
            // Fechar modal existente se estiver aberto
            const modalExistente = bootstrap.Modal.getInstance('#modalAPI');
            if (modalExistente) {
                modalExistente.hide();
            }
            
            // Buscar API completa (incluindo token) do servidor
            $.ajax({
                url: baseUrl + '/api/whatsapp_apis/buscar.php',
                method: 'GET',
                data: { id: idNum },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok' && data.api) {
                        const api = data.api;
                        
                        $('#api_id').val(api.id);
                        $('#api_nome').val(api.nome);
                        $('#api_url_mensagem').val(api.url_mensagem || '');
                        $('#api_url_arquivo').val(api.url_arquivo || '');
                        $('#api_token').val(api.token || '');
                        $('#api_base_url').val(api.base_url || '');
                        $('#api_session_name').val(api.session_name || '');
                        $('#api_secret_key').val(api.secret_key || '');
                        $('#api_numero').val(api.numero_whatsapp || '');
                        $('#api_prioridade').val(api.prioridade || 0);
                        $('#api_observacoes').val(api.observacoes || '');
                        $('#api_ativo').prop('checked', api.ativo == 1 || api.ativo === true);

                        $('#modalAPITitulo').html('<i class="bi bi-pencil me-2"></i>Editar API');
                        
                        // Criar nova instância do modal e mostrar
                        const modal = new bootstrap.Modal(document.getElementById('modalAPI'));
                        modal.show();
                    } else {
                        exibirToast('Erro ao buscar API: ' + (data.mensagem || 'Erro desconhecido'), 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao buscar API:', error);
                    exibirToast('Erro ao buscar API. Tente novamente.', 'danger');
                }
            });
        }
        
        function salvarAPI() {
            const id           = $('#api_id').val();
            const nome         = $('#api_nome').val().trim();
            const base_url     = $('#api_base_url').val().trim();
            const session_name = $('#api_session_name').val().trim();
            const secret_key   = $('#api_secret_key').val().trim();
            const url_mensagem = $('#api_url_mensagem').val().trim();
            const url_arquivo  = $('#api_url_arquivo').val().trim();
            const token        = $('#api_token').val().trim();

            // Validação mínima: precisa Nome + (Base URL + Session Name + Secret Key) ou (URLs explícitas + token)
            if (!nome) {
                exibirToast('Informe o Nome', 'warning');
                return;
            }
            const temWppConnectInfo = base_url && session_name && secret_key;
            const temUrlsExplicitas = url_mensagem && url_arquivo && token;
            if (!temWppConnectInfo && !temUrlsExplicitas) {
                exibirToast('Preencha Base URL + Session Name + Secret Key (recomendado), ou as URLs + Token manualmente', 'warning');
                return;
            }

            const dados = {
                id: id,
                nome: nome,
                base_url: base_url,
                session_name: session_name,
                secret_key: secret_key,
                url_mensagem: url_mensagem,
                url_arquivo: url_arquivo,
                token: token,
                numero_whatsapp: $('#api_numero').val().trim(),
                prioridade: parseInt($('#api_prioridade').val()) || 0,
                observacoes: $('#api_observacoes').val().trim(),
                ativo: $('#api_ativo').is(':checked') ? 1 : 0
            };
            
            $.ajax({
                url: baseUrl + '/api/whatsapp_apis/salvar.php',
                method: 'POST',
                data: dados,
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        bootstrap.Modal.getInstance('#modalAPI').hide();
                        // Recarregar APIs e status automaticamente
                        carregarAPIs();
                        exibirToast(data.mensagem, 'success');
                    } else {
                        exibirToast(data.mensagem, 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro ao salvar API', 'danger');
                }
            });
        }
        
        function excluirAPI(id, nome) {
            excluirId = id;
            $('#excluir-nome').text(nome);
            new bootstrap.Modal('#modalExcluir').show();
        }
        
        function confirmarExclusao() {
            if (!excluirId) return;
            
            $.ajax({
                url: baseUrl + '/api/whatsapp_apis/excluir.php',
                method: 'POST',
                data: { id: excluirId },
                dataType: 'json',
                success: function(data) {
                    bootstrap.Modal.getInstance('#modalExcluir').hide();
                    if (data.status === 'ok') {
                        carregarAPIs();
                        carregarConfiguracoes(); // Recarregar configs também
                        exibirToast(data.mensagem, 'success');
                    } else {
                        exibirToast(data.mensagem, 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro ao excluir API', 'danger');
                }
            });
        }
        
        
        function carregarConfiguracoes() {
            $.ajax({
                url: baseUrl + '/api/whatsapp_apis/config/listar.php',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        configs = data.configs;
                        apisDisponiveis = data.apis;
                        renderizarTabelaConfigs();
                    } else {
                        exibirToast('Erro ao carregar configurações: ' + data.mensagem, 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro ao carregar configurações', 'danger');
                }
            });
        }
        
        function renderizarTabelaConfigs() {
            const tbody = $('#tabela-configs');
            
            const tiposNomes = {
                'propria': 'Reserva Própria',
                'adicional': 'Reserva Adicional',
                'multipla': 'Reserva Múltipla',
                'cancelada': 'Cancelamento',
                'lembrete_reserva': 'Lembrete Diário',
                'justificativa_culto': 'Justificativa Culto',
                'cadastro_usuario': 'Cadastro Usuário',
                'relatorio_diario': 'Relatório Diário'
            };
            
            let html = `
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Tipo de Notificação</th>
                                <th>Modo de Seleção</th>
                                <th>API(s)</th>
                                <th>Tentativas</th>
                                <th>Desabilitar WhatsApp</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            configs.forEach(config => {
                const nomeTipo = tiposNomes[config.tipo_notificacao] || config.tipo_notificacao;
                const modoSelecao = config.modo_selecao || 'sorteio';
                const idsSorteio = config.ids_apis_sorteio || [];
                
                // Garantir que desabilitar_whatsapp seja tratado corretamente
                const desabilitarChecked = (config.desabilitar_whatsapp === 1 || config.desabilitar_whatsapp === true || config.desabilitar_whatsapp === '1');
                
                html += `
                    <tr>
                        <td><strong>${nomeTipo}</strong></td>
                        <td>
                            <select class="form-select form-select-sm" onchange="alterarModo('${config.tipo_notificacao}', this.value)">
                                <option value="sorteio" ${modoSelecao === 'sorteio' ? 'selected' : ''}>Sorteio</option>
                                <option value="especifica" ${modoSelecao === 'especifica' ? 'selected' : ''}>Específica</option>
                                <option value="desabilitado" ${modoSelecao === 'desabilitado' ? 'selected' : ''}>Desabilitado</option>
                            </select>
                        </td>
                        <td id="td-apis-${config.tipo_notificacao}">
                            ${renderizarCampoAPIs(config)}
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm" 
                                   id="tentativas-${config.tipo_notificacao}" 
                                   value="${config.tentativas_maximas || 3}" 
                                   min="1" max="10" style="width: 80px;">
                        </td>
                        <td>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       id="desabilitar-${config.tipo_notificacao}" 
                                       ${desabilitarChecked ? 'checked' : ''}>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            tbody.html(html);
        }
        
        function renderizarCampoAPIs(config) {
            const modoSelecao = config.modo_selecao || 'sorteio';
            const idsSorteio = config.ids_apis_sorteio || [];
            
            if (modoSelecao === 'desabilitado') {
                return '<span class="text-muted">-</span>';
            }
            
            if (modoSelecao === 'especifica') {
                let html = '<select class="form-select form-select-sm" id="api-especifica-' + config.tipo_notificacao + '">';
                html += '<option value="">Selecione...</option>';
                apisDisponiveis.forEach(api => {
                    html += `<option value="${api.id}" ${config.id_api_especifica == api.id ? 'selected' : ''}>${api.nome}</option>`;
                });
                html += '</select>';
                return html;
            }
            
            // Modo sorteio - multi-select
            let html = '<select class="form-select form-select-sm" multiple size="3" id="apis-sorteio-' + config.tipo_notificacao + '">';
            apisDisponiveis.forEach(api => {
                html += `<option value="${api.id}" ${idsSorteio.includes(api.id) ? 'selected' : ''}>${api.nome}</option>`;
            });
            html += '</select>';
            html += '<small class="text-muted d-block mt-1">Selecione uma ou mais APIs</small>';
            return html;
        }
        
        function alterarModo(tipoNotificacao, modo) {
            const config = configs.find(c => c.tipo_notificacao === tipoNotificacao);
            if (config) {
                config.modo_selecao = modo;
                // Limpar seleções anteriores ao mudar modo
                if (modo === 'especifica') {
                    config.id_api_especifica = null;
                    config.ids_apis_sorteio = [];
                } else if (modo === 'sorteio') {
                    config.id_api_especifica = null;
                    if (!config.ids_apis_sorteio || config.ids_apis_sorteio.length === 0) {
                        // Se não há APIs selecionadas, selecionar todas ativas por padrão
                        config.ids_apis_sorteio = apisDisponiveis.map(a => a.id);
                    }
                }
                $('#td-apis-' + tipoNotificacao).html(renderizarCampoAPIs(config));
            }
        }
        
        function salvarConfiguracoes() {
            const configsParaSalvar = [];
            
            configs.forEach(config => {
                const tipo = config.tipo_notificacao;
                const modoSelecao = $('#td-apis-' + tipo).closest('tr').find('select').first().val() || config.modo_selecao;
                const tentativasMaximas = parseInt($('#tentativas-' + tipo).val()) || 3;
                
                // Ler o estado atual do checkbox corretamente
                const checkbox = document.getElementById('desabilitar-' + tipo);
                const desabilitarWhatsapp = checkbox && checkbox.checked ? 1 : 0;
                
                let idApiEspecifica = null;
                let idsApisSorteio = null;
                
                if (modoSelecao === 'especifica') {
                    idApiEspecifica = parseInt($('#api-especifica-' + tipo).val()) || null;
                } else if (modoSelecao === 'sorteio') {
                    const selecionados = $('#apis-sorteio-' + tipo).val() || [];
                    if (selecionados.length > 0) {
                        idsApisSorteio = selecionados.map(id => parseInt(id));
                    }
                }
                
                configsParaSalvar.push({
                    tipo_notificacao: tipo,
                    modo_selecao: modoSelecao,
                    id_api_especifica: idApiEspecifica,
                    ids_apis_sorteio: idsApisSorteio,
                    tentativas_maximas: tentativasMaximas,
                    desabilitar_whatsapp: desabilitarWhatsapp
                });
            });
            
            $.ajax({
                url: baseUrl + '/api/whatsapp_apis/config/salvar.php',
                method: 'POST',
                data: { configs: configsParaSalvar },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        exibirToast(data.mensagem, 'success');
                        carregarConfiguracoes();
                    } else {
                        exibirToast(data.mensagem, 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro ao salvar configurações', 'danger');
                }
            });
        }
    </script>
</body>
</html>
