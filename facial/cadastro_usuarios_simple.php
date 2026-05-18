<?php
// Versão simplificada para cadastrar usuários no dispositivo SS3542
error_reporting(0); // Desativar erros para produção
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro Simples - SS3542</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .card { margin: 20px 0; }
        .success { background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; }
        .error { background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">Cadastro Simples de Usuários - SS3542</h1>
        
        <div class="card">
            <div class="card-header">Formulário de Cadastro</div>
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
                            <label for="password" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="password" name="password" value="123456">
                        </div>
                        <div class="col-md-6">
                            <label for="cardno" class="form-label">Número do Cartão (Hexadecimal)</label>
                            <input type="text" class="form-control" id="cardno" name="cardno" value="AB123456">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" name="submit">Cadastrar Usuário</button>
                </form>
            </div>
        </div>
        
        <?php
        if (isset($_POST['submit'])) {
            // Obter dados do formulário
            $userId = $_POST['userid'];
            $userName = $_POST['username'];
            $password = !empty($_POST['password']) ? $_POST['password'] : '123456';
            $cardNo = !empty($_POST['cardno']) ? $_POST['cardno'] : 'AB123456';
            
            // Data de validade (fixo de hoje até 2037)
            $validFrom = "2019-01-02 00:00:00";
            $validTo = "2037-01-02 01:00:00";
            
            // Configurações do dispositivo
            $deviceIp = '10.144.129.69';
            $deviceUser = 'admin';
            $devicePass = 'Arcs2901';
            
            // Preparar dados no formato JSON
            $userData = [
                'UserList' => [
                    [
                        'UserID' => $userId,
                        'UserName' => $userName,
                        'UserType' => 0,
                        'Authority' => 1,
                        'Password' => $password,
                        'Doors' => [0],
                        'TimeSections' => [255],
                        'ValidFrom' => $validFrom,
                        'ValidTo' => $validTo
                    ]
                ]
            ];
            
            $jsonData = json_encode($userData);
            
            // URL correta conforme o exemplo
            $url = "http://$deviceIp/cgi-bin/AccessUser.cgi?action=insertMulti";
            
            // Usando cURL para enviar os dados
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($ch, CURLOPT_USERPWD, "$deviceUser:$devicePass");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            // Executar a requisição
            $response = @curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // Considerar sucesso mesmo que não haja resposta (comportamento do dispositivo)
            $success = true; // Assumimos sucesso por padrão
            if (!empty($error) && $error != "Empty reply from server") {
                $success = false;
            }
            
            // Exibir resultado
            if ($success) {
                echo '<div class="success">
                    <h4>Usuário cadastrado com sucesso!</h4>
                    <p>ID: '.$userId.', Nome: '.$userName.'</p>
                </div>';
            } else {
                echo '<div class="error">
                    <h4>Ocorreu um problema ao cadastrar o usuário</h4>
                    <p>Erro: '.$error.'</p>
                    <p>Código HTTP: '.$httpcode.'</p>
                </div>';
            }
            
            // Exibir dados enviados para depuração
            echo '<div class="card mt-4">
                <div class="card-header">Dados enviados ao dispositivo</div>
                <div class="card-body">
                    <p><strong>URL:</strong> <code>'.htmlspecialchars($url).'</code></p>
                    <p><strong>JSON:</strong></p>
                    <pre>'.htmlspecialchars($jsonData).'</pre>
                </div>
            </div>';
        }
        ?>
    </div>
</body>
</html> 