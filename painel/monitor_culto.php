<?php
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');
include_once(__DIR__ . '/../auth/verifica_permissao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: monitor_culto                                          ║
// ║  Acesso: Grupo "Líder de Culto" ou Admin                      ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('monitor_culto');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Culto - Sistema de Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/feedback-system.css" rel="stylesheet">
    <style>
        .card-hover:hover {
            box-shadow: 0 0 0 4px #0d6efd33, 0 4px 24px rgba(0,0,0,0.10);
            border-color: #0d6efd !important;
            transform: translateY(-2px) scale(1.03);
            transition: all 0.2s;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .refresh-btn {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stats-card .card-body {
            padding: 1.5rem;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
        }
        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Cabeçalho -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">
                            <i class="bi bi-people-fill text-success me-2"></i>
                            Monitor de Sincronização - Culto
                        </h2>
                        <p class="text-muted mb-0">Acompanhe a sincronização facial dos usuários do culto</p>
                    </div>
                    <div>
                        <button class="btn btn-outline-primary me-2" onclick="carregarEstatisticas()">
                            <i class="bi bi-arrow-clockwise" id="refreshIcon"></i> Atualizar
                        </button>
                        <button class="btn btn-success" onclick="executarSincronizacao()">
                            <i class="bi bi-sync"></i> Sincronizar Agora
                        </button>
                        <button class="btn btn-info ms-2" onclick="verificarDispositivos()">
                            <i class="bi bi-hdd-stack"></i> Verificar Dispositivos
                        </button>
                        <button class="btn btn-warning ms-2" onclick="mostrarDetalhesFalhas()">
                            <i class="bi bi-exclamation-triangle"></i> Ver Falhas
                        </button>
                        <button class="btn btn-outline-danger ms-2" onclick="limparUsuariosNaoCulto()">
                            <i class="bi bi-trash"></i> Limpar Não-Culto
                        </button>
                        <a href="../culto/dashboard.php" class="btn btn-outline-secondary ms-2">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cards de Estatísticas -->
        <div class="row mb-4" id="statsCards">
            <!-- Será preenchido via JavaScript -->
        </div>

        <!-- Abas de Conteúdo -->
        <div class="row">
            <div class="col-12">
                <ul class="nav nav-tabs" id="monitorTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios" type="button" role="tab">
                            <i class="bi bi-people me-1"></i>Lista de Usuários
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                            <i class="bi bi-graph-up me-1"></i>Estatísticas
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="monitorTabsContent">
                    <!-- Aba: Usuários (Principal) -->
                    <div class="tab-pane fade show active" id="usuarios" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-people me-2"></i>Lista de Usuários do Culto
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="tabelaUsuarios">
                                        <thead>
                                            <tr>
                                                <th>Foto</th>
                                                <th>Nome</th>
                                                <th>Email</th>
                                                <th>Status Geral</th>
                                                <th>Sincronizados</th>
                                                <th>Falhas</th>
                                                <th>Pendentes</th>
                                                <th>Última Sincronização</th>
                                                <th>Dispositivos</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Será preenchido via JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Aba: Estatísticas -->
                    <div class="tab-pane fade" id="overview" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-graph-up me-2"></i>Estatísticas Gerais
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row" id="overviewStats">
                                    <!-- Será preenchido via JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação -->
    <div class="modal fade" id="modalConfirmacao" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle me-2"></i>Confirmar Ação
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="modalTexto">Tem certeza que deseja realizar esta ação?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmar">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/feedback-system.js"></script>
    <script>
        let estatisticas = null;

        // Carregar estatísticas ao inicializar
        document.addEventListener('DOMContentLoaded', function() {
            carregarEstatisticas();
            
            // Auto-refresh a cada 30 segundos
            setInterval(carregarEstatisticas, 30000);
        });

        function carregarEstatisticas() {
            const refreshIcon = document.getElementById('refreshIcon');
            refreshIcon.classList.add('refresh-btn');
            
            fetch('../api/culto/estatisticas_sincronizacao.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'sucesso') {
                        estatisticas = data;
                        atualizarInterface();
                    } else {
                        exibirToast('Erro ao carregar estatísticas: ' + data.mensagem, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    exibirToast('Erro ao carregar estatísticas', 'error');
                })
                .finally(() => {
                    refreshIcon.classList.remove('refresh-btn');
                });
        }

        function atualizarInterface() {
            if (!estatisticas) return;

            // Atualizar cards de estatísticas
            atualizarCardsStats();
            
            // Atualizar visão geral
            atualizarOverview();
            
            // Atualizar tabela de usuários
            atualizarTabelaUsuarios();
        }

        function atualizarCardsStats() {
            const stats = estatisticas.estatisticas_gerais;
            const status = estatisticas.status_sincronizacao;
            
            const cardsHtml = `
                <div class="col-md-3 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-number">${stats.total_usuarios_culto || 0}</div>
                            <div class="stats-label">Usuários do Culto</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-number">${stats.usuarios_com_foto || 0}</div>
                            <div class="stats-label">Com Foto</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-number">${status.sincronizado || 0}</div>
                            <div class="stats-label">Sincronizados</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-number">${status.falha || 0}</div>
                            <div class="stats-label">Falhas</div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('statsCards').innerHTML = cardsHtml;
        }

        function atualizarOverview() {
            const status = estatisticas.status_sincronizacao;
            const total = Object.values(status).reduce((a, b) => a + b, 0);
            
            const overviewHtml = `
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">Status de Sincronização</h6>
                            <div class="mb-2">
                                <span class="badge bg-success me-2">${status.sincronizado || 0}</span>
                                <span>Sincronizados</span>
                            </div>
                            <div class="mb-2">
                                <span class="badge bg-warning me-2">${status.pendente || 0}</span>
                                <span>Pendentes</span>
                            </div>
                            <div class="mb-2">
                                <span class="badge bg-danger me-2">${status.falha || 0}</span>
                                <span>Falhas</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">Taxa de Sucesso</h6>
                            <div class="progress mb-2">
                                <div class="progress-bar bg-success" style="width: ${total > 0 ? ((status.sincronizado || 0) / total * 100) : 0}%"></div>
                            </div>
                            <small class="text-muted">${total > 0 ? Math.round((status.sincronizado || 0) / total * 100) : 0}% de sucesso</small>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('overviewStats').innerHTML = overviewHtml;
        }



        function atualizarTabelaUsuarios() {
            const usuarios = estatisticas.usuarios_detalhado || [];
            console.log('Atualizando tabela com', usuarios.length, 'usuários');
            let tbody = '';
            
            usuarios.forEach(usuario => {
                // Determinar status geral
                let statusGeral = '';
                let statusClass = '';
                
                if (usuario.total_sincronizacoes === 0) {
                    statusGeral = 'Não Sincronizado';
                    statusClass = 'bg-secondary';
                } else if (usuario.falhas > 0 && usuario.sincronizados === 0) {
                    statusGeral = 'Falha Total';
                    statusClass = 'bg-danger';
                } else if (usuario.sincronizados > 0 && usuario.falhas === 0) {
                    statusGeral = 'Sincronizado';
                    statusClass = 'bg-success';
                } else if (usuario.sincronizados > 0 && usuario.falhas > 0) {
                    statusGeral = 'Parcial';
                    statusClass = 'bg-warning';
                } else if (usuario.pendentes > 0) {
                    statusGeral = 'Pendente';
                    statusClass = 'bg-info';
                }
                
                // Foto do usuário
                const fotoHtml = usuario.foto_base64 ? 
                    `<img src="data:image/jpeg;base64,${usuario.foto_base64}" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;" alt="Foto">` :
                    `<div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-person text-muted"></i>
                     </div>`;
                
                tbody += `
                    <tr>
                        <td>${fotoHtml}</td>
                        <td><strong>${usuario.nome}</strong></td>
                        <td>${usuario.email}</td>
                        <td><span class="badge ${statusClass}">${statusGeral}</span></td>
                        <td><span class="badge bg-success">${usuario.sincronizados || 0}</span></td>
                        <td><span class="badge bg-danger">${usuario.falhas || 0}</span></td>
                        <td><span class="badge bg-warning">${usuario.pendentes || 0}</span></td>
                        <td><small>${usuario.ultima_sincronizacao || 'Nunca'}</small></td>
                        <td><small class="text-muted">${usuario.dispositivos || 'Nenhum'}</small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="sincronizarUsuario(${usuario.id})" title="Re-sincronizar usuário">
                                <i class="bi bi-arrow-repeat me-1"></i>Re-sync
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            const tbodyElement = document.querySelector('#tabelaUsuarios tbody');
            console.log('Elemento tbody encontrado:', tbodyElement);
            if (tbodyElement) {
                tbodyElement.innerHTML = tbody;
                console.log('Tabela atualizada com', tbody.split('</tr>').length - 1, 'linhas');
            } else {
                console.error('Elemento #tabelaUsuarios tbody não encontrado');
            }
        }

        function executarSincronizacao() {
            mostrarConfirmacao(
                'Deseja executar a sincronização inteligente de todos os usuários do culto?',
                () => {
                    // Mostrar modal de progresso
                    mostrarModalProgresso();
                    
                    fetch('../api/culto/sincronizacao_lote.php', {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'sucesso') {
                            // Mostrar resultado final
                            mostrarResultadoFinal(data.resultados);
                        } else {
                            exibirToast('Erro na sincronização: ' + data.mensagem, 'error');
                            fecharModalProgresso();
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        exibirToast('Erro ao executar sincronização', 'error');
                        fecharModalProgresso();
                    });
                }
            );
        }

        function sincronizarUsuario(usuarioId) {
            mostrarConfirmacao(
                'Deseja re-sincronizar este usuário? (Remove do dispositivo e adiciona novamente)',
                () => {
                    const formData = new FormData();
                    formData.append('usuario_id', usuarioId);
                    
                    fetch('../api/culto/ressincronizar_usuario.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'sucesso') {
                            // Mostrar detalhes da re-sincronização
                            let mensagem = `Usuário "${data.usuario}" re-sincronizado!\n\n`;
                            mensagem += `• Dispositivos processados: ${data.total_processados}\n`;
                            mensagem += `• Sucessos: ${data.total_sucessos}\n`;
                            mensagem += `• Falhas: ${data.total_falhas}\n`;
                            
                            if (data.resultados && data.resultados.length > 0) {
                                mensagem += `\nDetalhes por dispositivo:\n`;
                                data.resultados.forEach(resultado => {
                                    const status = resultado.status === 'sucesso' ? '✓' : '✗';
                                    mensagem += `${status} ${resultado.dispositivo}: ${resultado.mensagem}\n`;
                                });
                            }
                            
                            exibirToast(mensagem, 'success');
                            carregarEstatisticas();
                        } else {
                            exibirToast('Erro na re-sincronização: ' + data.mensagem, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        exibirToast('Erro ao re-sincronizar usuário', 'error');
                    });
                }
            );
        }

        function verificarDispositivos() {
            fetch('../api/culto/verificar_dispositivos.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'sucesso') {
                        let mensagem = `Verificação de Dispositivos:\n\n`;
                        mensagem += `• Total de dispositivos do tipo "culto": ${data.resumo.total_dispositivos_culto}\n`;
                        mensagem += `• Dispositivos ativos: ${data.resumo.dispositivos_ativos}\n`;
                        mensagem += `• Total de usuários do culto: ${data.resumo.total_usuarios_culto}\n`;
                        mensagem += `• Usuários com foto: ${data.resumo.usuarios_com_foto}\n`;
                        mensagem += `• Registros de sincronização hoje: ${data.resumo.registros_sync_hoje}\n\n`;
                        
                        if (data.dispositivos.length > 0) {
                            mensagem += `Dispositivos encontrados:\n`;
                            data.dispositivos.forEach(disp => {
                                const status = disp.ativo == 1 ? 'Ativo' : 'Inativo';
                                mensagem += `• ${disp.nome} (${disp.ip}:${disp.porta}) - ${status}\n`;
                            });
                        } else {
                            mensagem += `⚠️ NENHUM DISPOSITIVO DO TIPO "CULTO" ENCONTRADO!\n`;
                            mensagem += `Configure dispositivos faciais do tipo "culto" para que a sincronização funcione.`;
                        }
                        
                        exibirToast(mensagem, data.resumo.dispositivos_ativos > 0 ? 'success' : 'warning');
                    } else {
                        exibirToast('Erro ao verificar dispositivos: ' + data.mensagem, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    exibirToast('Erro ao verificar dispositivos', 'error');
                });
        }

        function mostrarDetalhesFalhas() {
            fetch('../api/culto/detalhes_falhas.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'sucesso') {
                        if (data.falhas.length === 0) {
                            exibirToast('Nenhuma falha encontrada para hoje!', 'success');
                            return;
                        }
                        
                        // Criar modal de falhas se não existir
                        if (!document.getElementById('modalFalhas')) {
                            const modalHtml = `
                                <div class="modal fade" id="modalFalhas" tabindex="-1">
                                    <div class="modal-dialog modal-xl">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title">
                                                    <i class="bi bi-exclamation-triangle me-2"></i>Detalhes das Falhas
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div id="conteudoFalhas">
                                                    <!-- Será preenchido via JavaScript -->
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                            document.body.insertAdjacentHTML('beforeend', modalHtml);
                        }
                        
                        // Preencher conteúdo das falhas
                        let conteudo = `
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="card bg-danger text-white">
                                        <div class="card-body text-center">
                                            <h3>${data.estatisticas.total_falhas}</h3>
                                            <p class="mb-0">Total de Falhas</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-warning text-white">
                                        <div class="card-body text-center">
                                            <h3>${data.estatisticas.usuarios_com_falha}</h3>
                                            <p class="mb-0">Usuários com Falha</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-info text-white">
                                        <div class="card-body text-center">
                                            <h3>${data.estatisticas.dispositivos_com_falha}</h3>
                                            <p class="mb-0">Dispositivos com Falha</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Usuário</th>
                                            <th>Email</th>
                                            <th>Dispositivo</th>
                                            <th>Tentativas</th>
                                            <th>Última Tentativa</th>
                                            <th>Detalhes do Erro</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        
                        data.falhas.forEach(falha => {
                            const ultimaTentativa = falha.ultima_tentativa ? 
                                new Date(falha.ultima_tentativa).toLocaleString('pt-BR') : 'N/A';
                            
                            conteudo += `
                                <tr>
                                    <td><strong>${falha.nome}</strong></td>
                                    <td>${falha.email}</td>
                                    <td>
                                        <small>
                                            ${falha.dispositivo_nome}<br>
                                            <code>${falha.dispositivo_ip}</code>
                                        </small>
                                    </td>
                                    <td><span class="badge bg-warning">${falha.tentativas}</span></td>
                                    <td><small>${ultimaTentativa}</small></td>
                                    <td><small class="text-danger">${falha.detalhes || 'N/A'}</small></td>
                                </tr>
                            `;
                        });
                        
                        conteudo += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                        
                        document.getElementById('conteudoFalhas').innerHTML = conteudo;
                        new bootstrap.Modal(document.getElementById('modalFalhas')).show();
                        
                    } else {
                        exibirToast('Erro ao buscar falhas: ' + data.mensagem, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    exibirToast('Erro ao buscar falhas', 'error');
                });
        }

        function limparUsuariosNaoCulto() {
            mostrarConfirmacao(
                'Deseja remover todos os registros de sincronização de usuários que não são mais do culto?',
                () => {
                    fetch('../api/culto/limpar_usuarios_nao_culto.php', {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'sucesso') {
                            let mensagem = `Limpeza concluída!\n\n`;
                            mensagem += `• Usuários removidos: ${data.usuarios_removidos}\n`;
                            mensagem += `• Registros removidos: ${data.registros_removidos}\n\n`;
                            
                            if (data.usuarios && data.usuarios.length > 0) {
                                mensagem += `Usuários removidos:\n`;
                                data.usuarios.forEach(usuario => {
                                    mensagem += `• ${usuario.nome} (${usuario.email})\n`;
                                });
                            }
                            
                            exibirToast(mensagem, 'success');
                            carregarEstatisticas();
                        } else {
                            exibirToast('Erro na limpeza: ' + data.mensagem, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        exibirToast('Erro ao limpar usuários não-culto', 'error');
                    });
                }
            );
        }

        function mostrarModalProgresso() {
            // Criar modal de progresso se não existir
            if (!document.getElementById('modalProgresso')) {
                const modalHtml = `
                    <div class="modal fade" id="modalProgresso" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title">
                                        <i class="bi bi-hourglass-split me-2"></i>Sincronizando Usuários
                                    </h5>
                                </div>
                                <div class="modal-body text-center">
                                    <div class="mb-3">
                                        <i class="bi bi-arrow-repeat text-primary" style="font-size: 3rem; animation: spin 1s linear infinite;"></i>
                                    </div>
                                    <h6>Processando sincronização inteligente...</h6>
                                    <p class="text-muted">Verificando usuários e dispositivos, aguarde...</p>
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHtml);
            }
            
            new bootstrap.Modal(document.getElementById('modalProgresso')).show();
        }
        
        function fecharModalProgresso() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalProgresso'));
            if (modal) {
                modal.hide();
            }
        }
        
        function mostrarResultadoFinal(resultados) {
            fecharModalProgresso();
            
            // Criar modal de resultado se não existir
            if (!document.getElementById('modalResultado')) {
                const modalHtml = `
                    <div class="modal fade" id="modalResultado" tabindex="-1">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title">
                                        <i class="bi bi-check-circle me-2"></i>Resultado da Sincronização
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div id="resultadoConteudo">
                                        <!-- Será preenchido via JavaScript -->
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="carregarEstatisticas()">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Atualizar Dados
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHtml);
            }
            
            // Preencher conteúdo do resultado
            let conteudo = `
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3>${resultados.total_usuarios}</h3>
                                <p class="mb-0">Total de Usuários</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3>${resultados.usuarios_sincronizados}</h3>
                                <p class="mb-0">Sincronizados</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h3>${resultados.usuarios_ja_sincronizados}</h3>
                                <p class="mb-0">Já Sincronizados</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h3>${resultados.usuarios_falhas}</h3>
                                <p class="mb-0">Com Falhas</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Status</th>
                                <th>Dispositivos</th>
                                <th>Detalhes</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            resultados.detalhes_por_usuario.forEach(usuario => {
                let statusBadge = '';
                if (usuario.dispositivos_sincronizados > 0) {
                    statusBadge = '<span class="badge bg-success">Sincronizado</span>';
                } else if (usuario.dispositivos_ja_sincronizados > 0) {
                    statusBadge = '<span class="badge bg-info">Já Sincronizado</span>';
                } else if (usuario.dispositivos_falhas > 0) {
                    statusBadge = '<span class="badge bg-danger">Falha</span>';
                } else {
                    statusBadge = '<span class="badge bg-secondary">Não Processado</span>';
                }
                
                let detalhes = '';
                usuario.detalhes_dispositivos.forEach(det => {
                    const icon = det.status === 'sincronizado' ? '✓' : 
                                det.status === 'ja_sincronizado' ? '○' : '✗';
                    detalhes += `${icon} ${det.dispositivo}: ${det.mensagem}<br>`;
                });
                
                conteudo += `
                    <tr>
                        <td><strong>${usuario.usuario_nome}</strong></td>
                        <td>${statusBadge}</td>
                        <td>
                            <small>
                                Sincronizados: ${usuario.dispositivos_sincronizados || 0}<br>
                                Já sincronizados: ${usuario.dispositivos_ja_sincronizados || 0}<br>
                                Falhas: ${usuario.dispositivos_falhas || 0}
                            </small>
                        </td>
                        <td><small>${detalhes}</small></td>
                    </tr>
                `;
            });
            
            conteudo += `
                        </tbody>
                    </table>
                </div>
            `;
            
            document.getElementById('resultadoConteudo').innerHTML = conteudo;
            new bootstrap.Modal(document.getElementById('modalResultado')).show();
        }

        function mostrarConfirmacao(mensagem, callback) {
            document.getElementById('modalTexto').textContent = mensagem;
            document.getElementById('btnConfirmar').onclick = () => {
                bootstrap.Modal.getInstance(document.getElementById('modalConfirmacao')).hide();
                if (callback) callback();
            };
            new bootstrap.Modal(document.getElementById('modalConfirmacao')).show();
        }
    </script>
</body>
</html>

