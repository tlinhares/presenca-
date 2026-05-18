<?php
// Ativar reportamento de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Função para verificar se o dispositivo está online
function verificarDispositivo($ip, $timeout = 1) {
    $socket = @fsockopen($ip, 80, $errno, $errstr, $timeout);
    if ($socket) {
        fclose($socket);
        return true;
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Usuários - SS3542</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
        }
        .card {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">Cadastro de Usuários - SS3542</h1>
        
        <?php
        // Configurações do dispositivo
        $deviceIp = '10.144.129.69';
        $deviceDisponivel = verificarDispositivo($deviceIp);
        
        if (!$deviceDisponivel) {
            echo '<div class="alert alert-warning mb-4">
                <strong>Aviso:</strong> O dispositivo não está respondendo. Verifique se o IP está correto e se o dispositivo está ligado e conectado à rede.
                <br>IP atual: ' . $deviceIp . '
            </div>';
        }
        ?>
        
        <div class="card">
            <div class="card-header">
                Formulário de Cadastro
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="userid" class="form-label">ID do Usuário</label>
                            <input type="text" class="form-control" id="userid" name="userid" required>
                        </div>
                        <div class="col-md-6">
                            <label for="username" class="form-label">Nome do Usuário</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Senha (opcional)</label>
                            <input type="password" class="form-control" id="password" name="password">
                        </div>
                        <div class="col-md-6">
                            <label for="cardno" class="form-label">Número do Cartão (Hexadecimal) (opcional)</label>
                            <input type="text" class="form-control" id="cardno" name="cardno">
                            <small class="text-muted">Deixe em branco para gerar automaticamente</small>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="validfrom" class="form-label">Válido De</label>
                            <input type="datetime-local" class="form-control" id="validfrom" name="validfrom" required>
                        </div>
                        <div class="col-md-6">
                            <label for="validto" class="form-label">Válido Até</label>
                            <input type="datetime-local" class="form-control" id="validto" name="validto" required>
                        </div>
                    </div>
                    
                    <input type="hidden" name="usertype" value="0">
                    <input type="hidden" name="authority" value="1">
                    <input type="hidden" name="timesection" value="255">
                    
                    <button type="submit" class="btn btn-primary" name="submit">Cadastrar Usuário</button>
                </form>
            </div>
        </div>
        
        <?php
        if (isset($_POST['submit'])) {
            try {
                // Verificar primeiro se o dispositivo está online
                if (!$deviceDisponivel) {
                    throw new Exception("Dispositivo não está respondendo. Verifique se o IP está correto e se o dispositivo está acessível na rede.");
                }
                
                // Obter dados do formulário
                $userId = isset($_POST['userid']) ? $_POST['userid'] : '';
                $userName = isset($_POST['username']) ? $_POST['username'] : '';
                $password = !empty($_POST['password']) ? $_POST['password'] : '123456'; // Senha padrão se não for fornecida
                $cardNo = !empty($_POST['cardno']) ? $_POST['cardno'] : strtoupper(dechex(mt_rand(1000000, 9999999))); // Gera um número hexadecimal aleatório se não for fornecido
                
                // Validar datas
                if (empty($_POST['validfrom']) || empty($_POST['validto'])) {
                    throw new Exception("As datas de validade são obrigatórias.");
                }
                
                $validFrom = date('Ymd His', strtotime($_POST['validfrom']));
                $validTo = date('Ymd His', strtotime($_POST['validto']));
                $timeSection = isset($_POST['timesection']) ? $_POST['timesection'] : '255'; // Zona de tempo padrão
                
                // Configurações do dispositivo
                $deviceUser = 'admin';
                $devicePass = 'Arcs2901';
                
                // URL para cadastro de usuário (método GET)
                $url = "http://$deviceIp/cgi-bin/recordUpdater.cgi?action=insert&name=AccessControlCard" .
                       "&CardNo=$cardNo&CardStatus=0&CardName=" . urlencode($userName) .
                       "&UserID=$userId&Password=$password" .
                       "&TimeSection=$timeSection" .
                       "&ValidDateStart=$validFrom&ValidDateEnd=$validTo";
                
                // Verificar se a extensão cURL está disponível
                if (!function_exists('curl_init')) {
                    throw new Exception("A extensão cURL não está disponível no servidor.");
                }
                
                // Inicializa cURL
                $ch = curl_init();
                
                // Configurações do cURL
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                curl_setopt($ch, CURLOPT_USERPWD, "$deviceUser:$devicePass");
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Tempo limite para conexão (em segundos)
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);       // Tempo limite para a operação completa (em segundos)
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Desativar verificação de certificado SSL
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Desativar verificação de host SSL
                curl_setopt($ch, CURLOPT_FAILONERROR, false);    // Não falhar em erros HTTP
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0); // Usar HTTP 1.0 que é mais compatível
                
                // Executa a solicitação
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                
                // Verificar erros
                $error = '';
                if (curl_errno($ch)) {
                    $error = 'Erro cURL: ' . curl_error($ch);
                    
                    // Tratamentos específicos para erros comuns
                    if (strpos($error, 'Empty reply from server') !== false) {
                        $error .= ' - O dispositivo não respondeu à solicitação. Isso geralmente indica que o dispositivo aceitou o comando, mas não retornou uma resposta. Verifique no dispositivo se o cadastro foi realizado.';
                        $httpCode = 0; // Consideramos como potencial sucesso
                    }
                }
                
                // Fecha a sessão cURL
                curl_close($ch);
                
                // Tentar criar diretório de logs se não existir
                try {
                    $logDir = '../logs';
                    if (!is_dir($logDir) && !@mkdir($logDir, 0777, true)) {
                        $logDir = './logs';
                        if (!is_dir($logDir)) {
                            @mkdir($logDir, 0777, true);
                        }
                    }
                    
                    // Verificar permissões de escrita
                    $logFile = $logDir . '/cadastro_usuarios.log';
                    $logMessage = date('Y-m-d H:i:s') . " - ID: $userId - Nome: $userName - Status: $httpCode - Response: $response";
                    if ($error) {
                        $logMessage .= " - Erro: $error";
                    }
                    
                    if (is_writable($logDir) || (!file_exists($logFile) && is_writable(dirname($logDir)))) {
                        @file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
                    }
                } catch (Exception $e) {
                    // Ignorar erros de log
                }
                
                // Exibe o resultado
                // No caso de "Empty reply from server", consideramos como provável sucesso
                if ($httpCode == 200 || ($httpCode == 0 && strpos($error, 'Empty reply from server') !== false)) {
                    $message = "Usuário cadastrado com sucesso!";
                    if ($httpCode == 0) {
                        $message .= " (O dispositivo não retornou resposta, mas provavelmente aceitou o comando)";
                    }
                    
                    echo '<div class="result success">' . $message . '</div>';
                } else {
                    echo '<div class="result error">Erro ao cadastrar usuário. Código: ' . $httpCode . ' Resposta: ' . htmlspecialchars($response) . ' ' . htmlspecialchars($error) . '</div>';
                }
                
                // Exibe os dados enviados para depuração
                echo '<div class="card mt-4">
                    <div class="card-header">Dados Enviados</div>
                    <div class="card-body">
                        <pre>' . htmlspecialchars($url) . '</pre>
                    </div>
                </div>';
                
            } catch (Exception $e) {
                echo '<div class="result error">Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 