<?php
/**
 * Página de administração para sincronização permanente de usuários com dispositivos faciais de culto
 */

require_once '../config/timezone.php';
require_once '../api/conexao.php';
require_once '../auth/verifica_sessao.php';

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: culto_sync_permanente                                  ║
// ║  Acesso: Grupo "Líder de Culto" ou Admin                      ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('culto_sync_permanente');

// Buscar dispositivos de culto
$stmt = $conn->prepare("SELECT * FROM dispositivos_faciais WHERE tipo_dispositivo = 'culto' AND ativo = 1");
$stmt->execute();
$dispositivos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Buscar estatísticas de sincronização
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_usuarios,
        SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as usuarios_ativos,
        SUM(CASE WHEN ativo = 0 THEN 1 ELSE 0 END) as usuarios_inativos
    FROM usuarios
");
$stmt->execute();
$stats_usuarios = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("
    SELECT 
        status,
        COUNT(*) as quantidade
    FROM facial_sync_culto 
    WHERE data = CURDATE()
    GROUP BY status
");
$stmt->execute();
$stats_sync = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Buscar últimas ações de sincronização
$stmt = $conn->prepare("
    SELECT 
        fsc.*,
        u.nome as nome_usuario,
        df.nome as nome_dispositivo
    FROM facial_sync_culto fsc
    JOIN usuarios u ON fsc.id_usuario = u.id
    JOIN dispositivos_faciais df ON fsc.id_dispositivo = df.id
    ORDER BY fsc.ultima_tentativa DESC
    LIMIT 20
");
$stmt->execute();
$ultimas_acoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sincronização Permanente - Culto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.8em;
        }
        .log-entry {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            border-left: 3px solid #007bff;
            padding-left: 10px;
            margin-bottom: 5px;
        }
        .log-success {
            border-left-color: #28a745;
            background-color: #f8fff9;
        }
        .log-error {
            border-left-color: #dc3545;
            background-color: #fff8f8;
        }
        .log-warning {
            border-left-color: #ffc107;
            background-color: #fffdf8;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../culto/dashboard.php">
                <i class="bi bi-house-door me-2"></i>Sistema de Presença
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../culto/dashboard.php">
                    <i class="bi bi-arrow-left me-1"></i>Voltar ao Painel
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-sync me-2 text-primary"></i>Sincronização Permanente - Culto
                </h2>
            </div>
        </div>

        <!-- Alertas -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Sistema de Sincronização Permanente:</strong> Todos os usuários ativos ficam permanentemente nos dispositivos faciais de culto. 
                    Usuários são adicionados automaticamente quando cadastrados e removidos quando inativados.
                </div>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-primary">
                            <i class="bi bi-people-fill"></i>
                        </h5>
                        <h3 class="text-primary"><?= $stats_usuarios['usuarios_ativos'] ?></h3>
                        <p class="card-text">Usuários Ativos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success">
                            <i class="bi bi-hdd-stack"></i>
                        </h5>
                        <h3 class="text-success"><?= count($dispositivos) ?></h3>
                        <p class="card-text">Dispositivos Ativos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-warning">
                            <i class="bi bi-check-circle"></i>
                        </h5>
                        <h3 class="text-warning">
                            <?= array_sum(array_column(array_filter($stats_sync, function($s) { return $s['status'] === 'sincronizado'; }), 'quantidade')) ?>
                        </h3>
                        <p class="card-text">Sincronizados Hoje</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                        </h5>
                        <h3 class="text-danger">
                            <?= array_sum(array_column(array_filter($stats_sync, function($s) { return $s['status'] === 'falha'; }), 'quantidade')) ?>
                        </h3>
                        <p class="card-text">Falhas Hoje</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ações -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-gear me-2"></i>Ações de Sincronização
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <button class="btn btn-success w-100" onclick="executarSincronizacao()">
                                    <i class="bi bi-arrow-repeat me-2"></i>Sincronizar Agora
                                </button>
                                <small class="text-muted d-block mt-1">Executa sincronização inteligente usando a API de lote</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <button class="btn btn-warning w-100" onclick="processarPendentes()">
                                    <i class="bi bi-play-circle me-2"></i>Processar Pendentes
                                </button>
                                <small class="text-muted d-block mt-1">Processa ações automáticas pendentes</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <button class="btn btn-info w-100" onclick="buscarDadosFacial()">
                                    <i class="bi bi-search me-2"></i>Buscar Dados Facial
                                </button>
                                <small class="text-muted d-block mt-1">Consulta dados do dispositivo facial</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dispositivos -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-hdd-stack me-2"></i>Dispositivos de Culto
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($dispositivos)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Nenhum dispositivo de culto ativo encontrado.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>IP</th>
                                            <th>Porta</th>
                                            <th>Usuário</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dispositivos as $dispositivo): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($dispositivo['nome']) ?></td>
                                                <td><code><?= htmlspecialchars($dispositivo['ip']) ?></code></td>
                                                <td><?= htmlspecialchars($dispositivo['porta']) ?></td>
                                                <td><?= htmlspecialchars($dispositivo['usuario']) ?></td>
                                                <td>
                                                    <span class="badge bg-success">Ativo</span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="testarDispositivo('<?= $dispositivo['ip'] ?>')">
                                                        <i class="bi bi-wifi"></i> Testar
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Últimas Ações -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>Últimas Ações de Sincronização
                        </h5>
                        <button class="btn btn-outline-light btn-sm" onclick="atualizarAcoes()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Usuário</th>
                                        <th>Dispositivo</th>
                                        <th>Status</th>
                                        <th>Origem</th>
                                        <th>Última Tentativa</th>
                                        <th>Detalhes</th>
                                    </tr>
                                </thead>
                                <tbody id="tabela-acoes">
                                    <?php foreach ($ultimas_acoes as $acao): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($acao['nome_usuario']) ?></td>
                                            <td><?= htmlspecialchars($acao['nome_dispositivo']) ?></td>
                                            <td>
                                                <?php
                                                $status_class = 'secondary';
                                                $status_icon = 'question-circle';
                                                
                                                switch ($acao['status']) {
                                                    case 'sincronizado':
                                                        $status_class = 'success';
                                                        $status_icon = 'check-circle';
                                                        break;
                                                    case 'falha':
                                                        $status_class = 'danger';
                                                        $status_icon = 'x-circle';
                                                        break;
                                                    case 'pendente':
                                                        $status_class = 'warning';
                                                        $status_icon = 'clock';
                                                        break;
                                                    case 'remover':
                                                        $status_class = 'info';
                                                        $status_icon = 'trash';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge bg-<?= $status_class ?> status-badge">
                                                    <i class="bi bi-<?= $status_icon ?> me-1"></i>
                                                    <?= ucfirst($acao['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($acao['origem']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= $acao['ultima_tentativa'] ? date('d/m/Y H:i', strtotime($acao['ultima_tentativa'])) : 'Nunca' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($acao['detalhes']) ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fluxo igual ao monitor_culto: executarSincronizacao
        function executarSincronizacao() {
            // Modal de confirmação simplificado
            if (!confirm('Deseja executar a sincronização inteligente de todos os usuários do culto?')) return;

            mostrarModalProgresso();

            fetch('../api/culto/sincronizacao_lote.php', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'sucesso') {
                        mostrarResultadoFinal(data.resultados);
                    } else {
                        showError('Erro na sincronização: ' + (data.mensagem || 'Falha desconhecida'));
                        fecharModalProgresso();
                    }
                })
                .catch(error => {
                    showError('Erro ao executar sincronização: ' + error.message);
                    fecharModalProgresso();
                });
        }

        // Utilidades de UI (baseadas no monitor_culto)
        function mostrarModalProgresso() {
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
                    </div>`;
                document.body.insertAdjacentHTML('beforeend', modalHtml);
            }
            new bootstrap.Modal(document.getElementById('modalProgresso')).show();
        }

        function fecharModalProgresso() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalProgresso'));
            if (modal) modal.hide();
        }

        function mostrarResultadoFinal(resultados) {
            fecharModalProgresso();

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
                                    <div id="resultadoConteudo"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="atualizarAcoes()">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Atualizar Dados
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>`;
                document.body.insertAdjacentHTML('beforeend', modalHtml);
            }

            let conteudo = `
                <div class="row mb-4">
                    <div class="col-md-2"><div class="card bg-primary text-white"><div class="card-body text-center"><h3>${resultados.total_usuarios}</h3><p class="mb-0">Total de Usuários</p></div></div></div>
                    <div class="col-md-2"><div class="card bg-success text-white"><div class="card-body text-center"><h3>${resultados.usuarios_sincronizados}</h3><p class="mb-0">Sincronizados</p></div></div></div>
                    <div class="col-md-2"><div class="card bg-warning text-white"><div class="card-body text-center"><h3>${resultados.usuarios_removidos || 0}</h3><p class="mb-0">Removidos</p></div></div></div>
                    <div class="col-md-2"><div class="card bg-info text-white"><div class="card-body text-center"><h3>${resultados.usuarios_ja_sincronizados || 0}</h3><p class="mb-0">Já Sincronizados</p></div></div></div>
                    <div class="col-md-2"><div class="card bg-danger text-white"><div class="card-body text-center"><h3>${resultados.usuarios_falhas}</h3><p class="mb-0">Com Falhas</p></div></div></div>
                    <div class="col-md-2"><div class="card bg-secondary text-white"><div class="card-body text-center"><h3>${resultados.total_dispositivos}</h3><p class="mb-0">Dispositivos</p></div></div></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Usuário</th><th>Status</th><th>Dispositivos</th><th>Detalhes</th></tr></thead>
                        <tbody>`;

            (resultados.detalhes_por_usuario || []).forEach(usuario => {
                let statusBadge = '';
                if (usuario.dispositivos_sincronizados > 0) statusBadge = '<span class="badge bg-success">Sincronizado</span>';
                else if (usuario.dispositivos_removidos > 0) statusBadge = '<span class="badge bg-warning">Removido</span>';
                else if (usuario.dispositivos_falhas > 0) statusBadge = '<span class="badge bg-danger">Falha</span>';
                else statusBadge = '<span class="badge bg-secondary">Não Processado</span>';

                let detalhes = '';
                (usuario.detalhes_dispositivos || []).forEach(det => {
                    let icon = '';
                    if (det.status === 'sincronizado') icon = '✓';
                    else if (det.status === 'removido') icon = '🗑️';
                    else if (det.status === 'ok') icon = '○';
                    else icon = '✗';
                    detalhes += `${icon} ${det.dispositivo}: ${det.mensagem}<br>`;
                });

                const cultoStatus = usuario.culto == 1 ? '<span class="badge bg-success">Culto=1</span>' : '<span class="badge bg-secondary">Culto=0</span>';
                const ativoStatus = usuario.ativo == 1 ? '<span class="badge bg-primary">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>';

                conteudo += `<tr>
                    <td><strong>${usuario.usuario_nome}</strong><br><small>${cultoStatus} ${ativoStatus}</small></td>
                    <td>${statusBadge}</td>
                    <td><small>Sincronizados: ${usuario.dispositivos_sincronizados || 0}<br>Removidos: ${usuario.dispositivos_removidos || 0}<br>Falhas: ${usuario.dispositivos_falhas || 0}</small></td>
                    <td><small>${detalhes}</small></td>
                </tr>`;
            });

            conteudo += `</tbody></table></div>`;
            document.getElementById('resultadoConteudo').innerHTML = conteudo;
            new bootstrap.Modal(document.getElementById('modalResultado')).show();
        }
        
        function processarPendentes() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Processando...';
            btn.disabled = true;
            
            fetch('../api/culto/processar_sincronizacao_automatica.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'sucesso') {
                    showSuccess(`Processamento concluído! Adicionados: ${data.data.adicionados}, Removidos: ${data.data.removidos}, Falhas: ${data.data.total_falhas}`);
                    atualizarAcoes();
                } else {
                    showError('Erro: ' + data.message);
                }
            })
            .catch(error => {
                showError('Erro na requisição: ' + error.message);
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        function buscarDadosFacial() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Buscando...';
            btn.disabled = true;
            
            fetch('../api/culto/buscar_dados_facial.php?data_inicio=' + new Date().toISOString().split('T')[0])
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const totalDispositivos = data.data ? data.data.total_dispositivos : 0;
                    showSuccess(`Dados buscados com sucesso! Dispositivos consultados: ${totalDispositivos}`);
                    console.log('Dados do facial:', data.data);
                } else {
                    const errorMsg = data.message || data.mensagem || 'Erro desconhecido';
                    showError('Erro: ' + errorMsg);
                }
            })
            .catch(error => {
                showError('Erro na requisição: ' + error.message);
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        function testarDispositivo(ip) {
            showInfo('Testando conectividade com ' + ip + '...');
            
            fetch('../api/culto/buscar_dados_facial.php?ip_dispositivo=' + ip)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'sucesso') {
                    showSuccess('Dispositivo ' + ip + ' está respondendo corretamente!');
                } else {
                    showError('Erro no dispositivo ' + ip + ': ' + data.message);
                }
            })
            .catch(error => {
                showError('Erro ao testar ' + ip + ': ' + error.message);
            });
        }
        
        function atualizarAcoes() {
            location.reload();
        }
        
        function showSuccess(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show';
            alert.innerHTML = `
                <i class="bi bi-check-circle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.container');
            const firstRow = container.querySelector('.row');
            
            if (firstRow) {
                container.insertBefore(alert, firstRow);
            } else {
                container.appendChild(alert);
            }
            
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }
        
        function showError(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show';
            alert.innerHTML = `
                <i class="bi bi-exclamation-triangle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.container');
            const firstRow = container.querySelector('.row');
            
            if (firstRow) {
                container.insertBefore(alert, firstRow);
            } else {
                container.appendChild(alert);
            }
            
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 8000);
        }
        
        function showInfo(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-info alert-dismissible fade show';
            alert.innerHTML = `
                <i class="bi bi-info-circle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.container');
            const firstRow = container.querySelector('.row');
            
            if (firstRow) {
                container.insertBefore(alert, firstRow);
            } else {
                container.appendChild(alert);
            }
            
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 3000);
        }
    </script>
</body>
</html>
