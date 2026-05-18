<?php
/**
 * Monitor público de leitura facial (sem necessidade de login)
 * Para demonstração e verificação rápida
 */

require_once 'api/conexao.php';

// Verificar se a conexão foi estabelecida
if (!$conn) {
    die("Erro de conexão com o banco de dados");
}

// Buscar dispositivos de culto
$stmt = $conn->prepare("SELECT * FROM dispositivos_faciais WHERE tipo_dispositivo = 'culto' AND ativo = 1");
if (!$stmt) {
    die("Erro ao preparar consulta de dispositivos: " . $conn->error);
}
$stmt->execute();
$dispositivos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Buscar últimas presenças
$stmt = $conn->prepare("
    SELECT pc.*, u.nome as nome_usuario
    FROM presencas_culto pc 
    JOIN usuarios u ON pc.id_usuario = u.id 
    ORDER BY pc.data DESC, pc.horario_confirmacao DESC 
    LIMIT 10
");
if (!$stmt) {
    die("Erro ao preparar consulta de presenças: " . $conn->error);
}
$stmt->execute();
$ultimas_presencas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Público - Leitura Facial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
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
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-online {
            background-color: #28a745;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-house-door me-2"></i>Sistema de Presença
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text">
                    <i class="bi bi-eye me-1"></i>Monitor Público
                </span>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Monitor Público:</strong> Esta página mostra o status atual do sistema de reconhecimento facial para culto.
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-activity me-2 text-primary"></i>Monitor de Leitura Facial
                </h2>
            </div>
        </div>

        <!-- Status dos Dispositivos -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-hdd-stack me-2"></i>Status dos Dispositivos
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($dispositivos)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Nenhum dispositivo de culto ativo encontrado.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($dispositivos as $dispositivo): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card border-0 bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <span class="status-indicator status-online"></span>
                                                    <?= htmlspecialchars($dispositivo['nome']) ?>
                                                </h6>
                                                <p class="card-text mb-1">
                                                    <strong>IP:</strong> <code><?= htmlspecialchars($dispositivo['ip']) ?></code>
                                                </p>
                                                <p class="card-text mb-1">
                                                    <strong>Porta:</strong> <?= $dispositivo['porta'] ?>
                                                </p>
                                                <p class="card-text mb-0">
                                                    <strong>Status:</strong> 
                                                    <span class="badge bg-success">Ativo</span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logs Recentes -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-file-text me-2"></i>Logs Recentes
                        </h5>
                        <button class="btn btn-outline-light btn-sm" onclick="atualizarLogs()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="logs-container" style="max-height: 300px; overflow-y: auto;">
                            <div class="text-center text-muted">
                                <i class="bi bi-hourglass-split me-2"></i>Carregando logs...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Últimas Presenças -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="bi bi-people me-2"></i>Últimas Presenças Registradas
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($ultimas_presencas)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox me-2"></i>Nenhuma presença registrada ainda.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Usuário</th>
                                            <th>Data</th>
                                            <th>Horário</th>
                                            <th>Status</th>
                                            <th>Tipo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimas_presencas as $presenca): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($presenca['nome_usuario']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($presenca['data'])) ?></td>
                                                <td><?= $presenca['horario_confirmacao'] ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    $status_icon = '';
                                                    switch ($presenca['status']) {
                                                        case 'presente':
                                                            $status_class = 'bg-success';
                                                            $status_icon = 'bi-check-circle';
                                                            break;
                                                        case 'atrasado':
                                                            $status_class = 'bg-warning';
                                                            $status_icon = 'bi-clock';
                                                            break;
                                                        case 'falta':
                                                            $status_class = 'bg-danger';
                                                            $status_icon = 'bi-x-circle';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?= $status_class ?>">
                                                        <i class="bi <?= $status_icon ?> me-1"></i>
                                                        <?= ucfirst($presenca['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?= ucfirst($presenca['tipo_confirmacao']) ?>
                                                    </span>
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

        <!-- Informações do Sistema -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-info-circle me-2"></i>Informações do Sistema
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h6 class="text-muted">Dispositivos Ativos</h6>
                                    <h4 class="text-primary"><?= count($dispositivos) ?></h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h6 class="text-muted">Presenças Hoje</h6>
                                    <h4 class="text-success"><?= count($ultimas_presencas) ?></h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h6 class="text-muted">Status</h6>
                                    <h4 class="text-success">Online</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h6 class="text-muted">Última Atualização</h6>
                                    <h6 class="text-info"><?= date('H:i:s') ?></h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function atualizarLogs() {
            const container = document.getElementById('logs-container');
            container.innerHTML = '<div class="text-center text-muted"><i class="bi bi-hourglass-split me-2"></i>Carregando logs...</div>';
            
            // Simular carregamento de logs (versão simplificada)
            setTimeout(() => {
                const logs = [
                    '<?= date('Y-m-d H:i:s') ?> Recebida leitura facial: Tiago Linhares Tavares do dispositivo 10.144.129.78',
                    '<?= date('Y-m-d H:i:s') ?> Presença registrada para Tiago Linhares Tavares: atrasado',
                    '<?= date('Y-m-d H:i:s') ?> Sistema funcionando normalmente'
                ];
                
                let html = '';
                logs.forEach(log => {
                    let logClass = 'log-entry';
                    if (log.includes('ERRO:')) {
                        logClass += ' log-error';
                    } else if (log.includes('Presença') || log.includes('registrada')) {
                        logClass += ' log-success';
                    } else if (log.includes('Recebida')) {
                        logClass += ' log-warning';
                    }
                    html += `<div class="${logClass}">${log}</div>`;
                });
                container.innerHTML = html;
            }, 1000);
        }
        
        // Carregar logs inicialmente
        atualizarLogs();
        
        // Atualizar logs a cada 10 segundos
        setInterval(atualizarLogs, 10000);
    </script>
</body>
</html>
