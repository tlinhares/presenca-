<?php
require_once 'api/conexao.php';

// Buscar IP do primeiro dispositivo de culto ativo
$stmt = $conn->prepare("SELECT ip FROM dispositivos_faciais WHERE tipo_dispositivo = 'culto' AND ativo = 1 LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$ip_dispositivo = $result->fetch_assoc()['ip'] ?? '10.144.198.50';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste - Leitura Facial Culto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Teste - API de Leitura Facial para Culto</h4>
                    </div>
                    <div class="card-body">
                        <form id="formTeste">
                            <div class="mb-3">
                                <label for="nome_usuario" class="form-label">Nome do Usuário</label>
                                <input type="text" class="form-control" id="nome_usuario" value="Neize Pereira da Silva Rodrigues" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="ip_dispositivo" class="form-label">IP do Dispositivo</label>
                                <input type="text" class="form-control" id="ip_dispositivo" value="<?= $ip_dispositivo ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="timestamp" class="form-label">Timestamp</label>
                                <input type="text" class="form-control" id="timestamp" value="<?= time() ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label for="foto_base64" class="form-label">Foto Base64 (opcional)</label>
                                <textarea class="form-control" id="foto_base64" rows="3" placeholder="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Enviar Leitura</button>
                        </form>
                        
                        <div id="resultado" class="mt-4" style="display: none;">
                            <h5>Resultado:</h5>
                            <pre id="resultadoJson" class="bg-light p-3 rounded"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('formTeste').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                nome_usuario: document.getElementById('nome_usuario').value,
                ip_dispositivo: document.getElementById('ip_dispositivo').value,
                timestamp: document.getElementById('timestamp').value,
                foto_base64: document.getElementById('foto_base64').value || null
            };
            
            fetch('api/culto/receber_leitura_facial.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('resultado').style.display = 'block';
                document.getElementById('resultadoJson').textContent = JSON.stringify(data, null, 2);
                
                if (data.status === 'success') {
                    document.getElementById('resultadoJson').className = 'bg-success text-white p-3 rounded';
                } else {
                    document.getElementById('resultadoJson').className = 'bg-danger text-white p-3 rounded';
                }
            })
            .catch(error => {
                document.getElementById('resultado').style.display = 'block';
                document.getElementById('resultadoJson').textContent = 'Erro: ' + error.message;
                document.getElementById('resultadoJson').className = 'bg-danger text-white p-3 rounded';
            });
        });
    </script>
</body>
</html>
