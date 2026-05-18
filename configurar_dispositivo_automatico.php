<?php
/**
 * Script para configurar automaticamente o dispositivo facial
 * Este script pode ser executado via CRON ou manualmente
 */

require_once 'api/conexao.php';
require_once 'config/timezone.php';
require_once 'config/dominio.php';

// Função para log
function logConfiguracao($mensagem) {
    $logFile = __DIR__ . '/logs/configuracao_dispositivo_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $mensagem\n", FILE_APPEND);
}

// Função para configurar dispositivo via API
function configurarDispositivo($ip, $porta, $usuario, $senha, $configuracoes) {
    $url = "http://$ip:$porta/api/config";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($configuracoes));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode("$usuario:$senha")
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$usuario:$senha");
    
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

// Função para sincronizar usuários
function sincronizarUsuarios($ip, $porta, $usuario, $senha) {
    $url = "http://$ip:$porta/api/sync/users";
    
    // Buscar usuários ativos
    global $conn;
    $stmt = $conn->prepare("SELECT id, nome, foto FROM usuarios WHERE ativo = 1");
    $stmt->execute();
    $usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $dados = [
        'usuarios' => $usuarios,
        'timestamp' => time()
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode("$usuario:$senha")
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$usuario:$senha";
    
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);
    
    return [
        'sucesso' => $httpCode === 200,
        'codigo' => $httpCode,
        'resposta' => $resposta,
        'erro' => $erro,
        'usuarios_enviados' => count($usuarios)
    ];
}

// Processar configuração
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'configurar') {
        $id_dispositivo = $_POST['id_dispositivo'];
        
        // Buscar dados do dispositivo
        $stmt = $conn->prepare("SELECT * FROM dispositivos_faciais WHERE id = ? AND tipo_dispositivo = 'culto'");
        $stmt->bind_param("i", $id_dispositivo);
        $stmt->execute();
        $dispositivo = $stmt->get_result()->fetch_assoc();
        
        if (!$dispositivo) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Dispositivo não encontrado']);
            exit;
        }
        
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
        
        // Configurações para enviar ao dispositivo
        $config_dispositivo = [
            'api_reading' => [
                'url' => $URL_API_LEITURA_FACIAL,
                'method' => 'POST',
                'content_type' => 'application/json',
                'timeout' => 30,
                'retries' => 3
            ],
            'api_sync' => [
                'url' => $URL_API_SINCRONIZACAO,
                'method' => 'POST',
                'content_type' => 'application/json',
                'schedule' => '07:30',
                'interval' => 'daily'
            ],
            'horarios' => [
                'inicio_culto' => $horario_inicio,
                'fim_culto' => $horario_fim,
                'tolerancia_atraso' => $tolerancia
            ],
            'reconhecimento' => [
                'precisao' => 'alta',
                'velocidade' => 'rapida',
                'iluminacao' => 'automatica'
            ]
        ];
        
        // Configurar dispositivo
        $resultado_config = configurarDispositivo(
            $dispositivo['ip'],
            $dispositivo['porta'],
            $dispositivo['usuario'],
            $dispositivo['senha'],
            $config_dispositivo
        );
        
        if ($resultado_config['sucesso']) {
            // Sincronizar usuários
            $resultado_sync = sincronizarUsuarios(
                $dispositivo['ip'],
                $dispositivo['porta'],
                $dispositivo['usuario'],
                $dispositivo['senha']
            );
            
            if ($resultado_sync['sucesso']) {
                // Atualizar status do dispositivo
                $stmt = $conn->prepare("UPDATE dispositivos_faciais SET status_conexao = 'online', ultima_sincronizacao = NOW() WHERE id = ?");
                $stmt->bind_param("i", $id_dispositivo);
                $stmt->execute();
                
                logConfiguracao("Dispositivo {$dispositivo['nome']} configurado com sucesso. Usuários sincronizados: {$resultado_sync['usuarios_enviados']}");
                
                echo json_encode([
                    'status' => 'sucesso',
                    'mensagem' => 'Dispositivo configurado com sucesso!',
                    'usuarios_sincronizados' => $resultado_sync['usuarios_enviados']
                ]);
            } else {
                logConfiguracao("Erro ao sincronizar usuários no dispositivo {$dispositivo['nome']}: {$resultado_sync['erro']}");
                
                echo json_encode([
                    'status' => 'erro',
                    'mensagem' => 'Erro ao sincronizar usuários: ' . $resultado_sync['erro']
                ]);
            }
        } else {
            logConfiguracao("Erro ao configurar dispositivo {$dispositivo['nome']}: {$resultado_config['erro']}");
            
            echo json_encode([
                'status' => 'erro',
                'mensagem' => 'Erro ao configurar dispositivo: ' . $resultado_config['erro']
            ]);
        }
    }
    exit;
}

// Buscar dispositivos de culto
$stmt = $conn->prepare("SELECT * FROM dispositivos_faciais WHERE tipo_dispositivo = 'culto' ORDER BY nome");
$stmt->execute();
$dispositivos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração Automática - Dispositivo Facial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-gear-fill me-2 text-primary"></i>Configuração Automática - Dispositivo Facial
                </h2>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-hdd-stack me-2"></i>Dispositivos de Culto
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($dispositivos)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Nenhum dispositivo de culto encontrado.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>IP</th>
                                            <th>Porta</th>
                                            <th>Status</th>
                                            <th>Última Sincronização</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dispositivos as $dispositivo): ?>
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
                                                    <?php if ($dispositivo['status_conexao'] === 'online'): ?>
                                                        <span class="badge bg-success">Online</span>
                                                    <?php elseif ($dispositivo['status_conexao'] === 'offline'): ?>
                                                        <span class="badge bg-danger">Offline</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Erro</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($dispositivo['ultima_sincronizacao']): ?>
                                                        <?= date('d/m/Y H:i', strtotime($dispositivo['ultima_sincronizacao'])) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Nunca</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-primary btn-sm" onclick="configurarDispositivo(<?= $dispositivo['id'] ?>)">
                                                        <i class="bi bi-gear me-1"></i>Configurar
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

        <!-- Resultado da Configuração -->
        <div class="row mt-4">
            <div class="col-12">
                <div id="resultado-configuracao"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function configurarDispositivo(id) {
            const resultado = document.getElementById('resultado-configuracao');
            resultado.innerHTML = '<div class="alert alert-info"><i class="bi bi-hourglass-split me-2"></i>Configurando dispositivo...</div>';
            
            const formData = new FormData();
            formData.append('acao', 'configurar');
            formData.append('id_dispositivo', id);
            
            fetch('configurar_dispositivo_automatico.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'sucesso') {
                    resultado.innerHTML = `
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            ${data.mensagem}
                            <br>
                            <small>Usuários sincronizados: ${data.usuarios_sincronizados}</small>
                        </div>
                    `;
                } else {
                    resultado.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-x-circle me-2"></i>
                            ${data.mensagem}
                        </div>
                    `;
                }
            })
            .catch(error => {
                resultado.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-x-circle me-2"></i>
                        Erro: ${error.message}
                    </div>
                `;
            });
        }
    </script>
</body>
</html>
