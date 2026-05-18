<?php
/**
 * Script de teste para verificar configuração do dispositivo facial
 * Acesse via: http://SEU_SERVIDOR/presenca/teste_configuracao_facial.php
 */

require_once 'api/conexao.php';
require_once 'config/timezone.php';
require_once 'config/dominio.php';

// Função para testar conectividade
function testarConectividade($ip, $porta = 80) {
    $conexao = @fsockopen($ip, $porta, $errno, $errstr, 5);
    if ($conexao) {
        fclose($conexao);
        return true;
    }
    return false;
}

// Função para testar API
function testarAPI($url, $dados) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($dados))
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);
    
    return [
        'sucesso' => $httpCode === 200,
        'codigo' => $httpCode,
        'resposta' => $resposta,
        'erro' => $erro
    ];
}

// Buscar dispositivos de culto
$stmt = $conn->prepare("SELECT * FROM dispositivos_faciais WHERE tipo_dispositivo = 'culto' AND ativo = 1");
$stmt->execute();
$dispositivos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obter IP do primeiro dispositivo de culto ativo para exemplos
$ip_exemplo = !empty($dispositivos) ? $dispositivos[0]['ip'] : '10.144.198.50';

// Buscar configurações do culto
$stmt = $conn->prepare("SELECT chave, valor FROM configuracoes_culto");
$stmt->execute();
$result = $stmt->get_result();
$configuracoes = [];
while ($row = $result->fetch_assoc()) {
    $configuracoes[$row['chave']] = $row['valor'];
}

$horario_inicio = $configuracoes['horario_inicio'] ?? '07:30:00';
$horario_fim = $configuracoes['horario_fim'] ?? '08:30:00';
$tolerancia = $configuracoes['tolerancia_atraso'] ?? '15';

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Configuração - Dispositivo Facial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-gear-fill me-2 text-primary"></i>Teste de Configuração - Dispositivo Facial
                </h2>
            </div>
        </div>

        <!-- Informações do Sistema -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-info-circle me-2"></i>Informações do Sistema
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>URL da API de Leitura:</h6>
                                <code><?= $URL_API_LEITURA_FACIAL ?></code>
                            </div>
                            <div class="col-md-6">
                                <h6>URL da API de Sincronização:</h6>
                                <code><?= $URL_API_SINCRONIZACAO ?></code>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <h6>Horário de Início:</h6>
                                <span class="badge bg-success"><?= $horario_inicio ?></span>
                            </div>
                            <div class="col-md-4">
                                <h6>Tolerância:</h6>
                                <span class="badge bg-warning"><?= $tolerancia ?> min</span>
                            </div>
                            <div class="col-md-4">
                                <h6>Horário de Fim:</h6>
                                <span class="badge bg-danger"><?= $horario_fim ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Teste de Dispositivos -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="bi bi-hdd-stack me-2"></i>Teste de Dispositivos
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
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>IP</th>
                                            <th>Porta</th>
                                            <th>Conectividade</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dispositivos as $dispositivo): ?>
                                            <?php 
                                            $conectivo = testarConectividade($dispositivo['ip'], $dispositivo['porta']);
                                            ?>
                                            <tr>
                                                <td>
                                                    <i class="bi bi-hdd me-1"></i>
                                                    <?= htmlspecialchars($dispositivo['nome']) ?>
                                                </td>
                                                <td>
                                                    <code><?= htmlspecialchars($dispositivo['ip']) ?></code>
                                                </td>
                                                <td><?= $dispositivo['porta'] ?></td>
                                                <td>
                                                    <?php if ($conectivo): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle me-1"></i>Online
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-x-circle me-1"></i>Offline
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($dispositivo['ativo']): ?>
                                                        <span class="badge bg-success">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inativo</span>
                                                    <?php endif; ?>
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

        <!-- Teste da API -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-code-slash me-2"></i>Teste da API
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Teste de Leitura Facial:</h6>
                                <button class="btn btn-outline-info btn-sm" onclick="testarLeituraFacial()">
                                    <i class="bi bi-play-circle me-1"></i>Testar API
                                </button>
                                <div id="resultado-leitura" class="mt-2"></div>
                            </div>
                            <div class="col-md-6">
                                <h6>Teste de Sincronização:</h6>
                                <button class="btn btn-outline-info btn-sm" onclick="testarSincronizacao()">
                                    <i class="bi bi-play-circle me-1"></i>Testar Sync
                                </button>
                                <div id="resultado-sync" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comandos de Configuração -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-terminal me-2"></i>Comandos de Configuração
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Configuração via cURL:</h6>
                                <div class="bg-dark text-light p-3 rounded">
                                    <pre><code>curl -X POST <?= $URL_API_LEITURA_FACIAL ?> \
  -H "Content-Type: application/json" \
  -d '{
    "nome_usuario": "Tiago Linhares Tavares",
    "ip_dispositivo": "<?= $ip_exemplo ?>",
    "timestamp": <?= time() ?>
  }'</code></pre>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Configuração no Dispositivo:</h6>
                                <div class="bg-dark text-light p-3 rounded">
                                    <pre><code>URL: <?= $URL_API_LEITURA_FACIAL ?>
Método: POST
Content-Type: application/json
Timeout: 30s</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logs -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-file-text me-2"></i>Logs do Sistema
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Logs de Leitura Facial:</h6>
                                <a href="logs/" class="btn btn-outline-secondary btn-sm" target="_blank">
                                    <i class="bi bi-folder me-1"></i>Ver Logs
                                </a>
                            </div>
                            <div class="col-md-6">
                                <h6>Logs de Sincronização:</h6>
                                <a href="logs/" class="btn btn-outline-secondary btn-sm" target="_blank">
                                    <i class="bi bi-folder me-1"></i>Ver Logs
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function testarLeituraFacial() {
            const resultado = document.getElementById('resultado-leitura');
            resultado.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div>Testando...';
            
            const dados = {
                nome_usuario: 'Tiago Linhares Tavares',
                ip_dispositivo: '<?= $ip_exemplo ?>',
                timestamp: Math.floor(Date.now() / 1000)
            };
            
            fetch('api/culto/receber_leitura_facial.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(dados)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success' || data.status === 'sucesso') {
                    resultado.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>API funcionando corretamente!<br><small>Status: ' + (data.data?.status || 'OK') + '</small></div>';
                } else {
                    resultado.innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>' + (data.message || data.mensagem || 'Erro desconhecido') + '</div>';
                }
            })
            .catch(error => {
                resultado.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Erro: ' + error.message + '</div>';
            });
        }
        
        function testarSincronizacao() {
            const resultado = document.getElementById('resultado-sync');
            resultado.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div>Testando...';
            
            const dados = {
                data: new Date().toISOString().split('T')[0]
            };
            
            fetch('api/culto/executar_sync.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(dados)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'ok' || data.status === 'sucesso') {
                    resultado.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Sincronização funcionando!<br><small>Sincronizados: ' + (data.sincronizados || 0) + ', Falhas: ' + (data.falhas || 0) + '</small></div>';
                } else {
                    resultado.innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>' + (data.message || data.mensagem || 'Erro desconhecido') + '</div>';
                }
            })
            .catch(error => {
                resultado.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Erro: ' + error.message + '</div>';
            });
        }
    </script>
</body>
</html>
