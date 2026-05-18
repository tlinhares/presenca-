<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Dispositivo de Culto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Configuração do Dispositivo Facial de Culto</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h5>📋 Instruções para Configuração</h5>
                            <p>Para configurar o dispositivo facial para culto, você precisa:</p>
                            <ol>
                                <li><strong>Configurar o dispositivo</strong> para enviar leituras para nossa API</li>
                                <li><strong>Definir o endpoint</strong> para: <code>http://SEU_SERVIDOR/presenca/api/culto/receber_leitura_facial.php</code></li>
                                <li><strong>Enviar dados</strong> no formato JSON com os campos obrigatórios</li>
                            </ol>
                        </div>

                        <h5>🔧 Configuração do Dispositivo</h5>
                        <div class="mb-3">
                            <label class="form-label">URL da API:</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="url_api" value="http://localhost/presenca/api/culto/receber_leitura_facial.php" readonly>
                                <button class="btn btn-outline-secondary" onclick="copiarUrl()">Copiar</button>
                            </div>
                        </div>

                        <h5>📤 Formato dos Dados</h5>
                        <div class="mb-3">
                            <label class="form-label">Método HTTP:</label>
                            <input type="text" class="form-control" value="POST" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Content-Type:</label>
                            <input type="text" class="form-control" value="application/json" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Exemplo de JSON:</label>
                            <textarea class="form-control" rows="8" readonly>{
  "nome_usuario": "Nome Completo do Usuário",
  "ip_dispositivo": "10.144.198.50",
  "timestamp": 1726574400,
  "foto_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ..."
}</textarea>
                        </div>

                        <h5>📋 Campos Obrigatórios</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Campo</th>
                                    <th>Tipo</th>
                                    <th>Descrição</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>nome_usuario</code></td>
                                    <td>string</td>
                                    <td>Nome completo do usuário reconhecido</td>
                                </tr>
                                <tr>
                                    <td><code>ip_dispositivo</code></td>
                                    <td>string</td>
                                    <td>IP do dispositivo que está enviando</td>
                                </tr>
                                <tr>
                                    <td><code>timestamp</code></td>
                                    <td>integer</td>
                                    <td>Timestamp Unix da leitura</td>
                                </tr>
                                <tr>
                                    <td><code>foto_base64</code></td>
                                    <td>string</td>
                                    <td>Foto em base64 (opcional)</td>
                                </tr>
                            </tbody>
                        </table>

                        <h5>⏰ Configurações de Horário</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h6>Início do Culto</h6>
                                        <h4 class="text-primary">07:30</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h6>Tolerância</h6>
                                        <h4 class="text-warning">15 min</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h6>Fim do Culto</h6>
                                        <h4 class="text-danger">08:30</h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h5>📊 Status de Presença</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="alert alert-success">
                                    <strong>Presente</strong><br>
                                    Chegou até 07:45
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-warning">
                                    <strong>Atrasado</strong><br>
                                    Chegou entre 07:45 e 08:30
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-danger">
                                    <strong>Falta</strong><br>
                                    Não chegou até 08:30
                                </div>
                            </div>
                        </div>

                        <h5>🧪 Testar Configuração</h5>
                        <div class="mb-3">
                            <a href="teste_leitura_facial_culto.php" class="btn btn-primary">
                                <i class="bi bi-play-circle"></i> Testar API
                            </a>
                        </div>

                        <h5>📝 Logs</h5>
                        <div class="mb-3">
                            <p>Os logs de leitura facial são salvos em:</p>
                            <code>/var/www/html/presenca/logs/leitura_facial_culto_YYYY-MM-DD.log</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copiarUrl() {
            const urlInput = document.getElementById('url_api');
            urlInput.select();
            urlInput.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            // Mostrar feedback
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = 'Copiado!';
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-secondary');
            
            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }
    </script>
</body>
</html>
