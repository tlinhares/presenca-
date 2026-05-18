<?php
session_start();
require_once 'auth/verifica_sessao.php';
require_once 'api/conexao.php';

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: configurar_dominio                                     ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('configurar_dominio');

$mensagem = '';

// Processar atualização do domínio
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao']) && $_POST['acao'] === 'atualizar_dominio') {
        $novo_dominio = trim($_POST['dominio']);
        
        // Validar domínio
        if (empty($novo_dominio)) {
            $mensagem = '<div class="alert alert-danger">Domínio não pode estar vazio.</div>';
        } elseif (!filter_var('https://' . $novo_dominio, FILTER_VALIDATE_URL)) {
            $mensagem = '<div class="alert alert-danger">Domínio inválido.</div>';
        } else {
            // Atualizar arquivo de configuração
            $arquivo_config = __DIR__ . '/config/dominio.php';
            $conteudo = file_get_contents($arquivo_config);
            
            // Substituir o domínio
            $conteudo = preg_replace(
                '/\$DOMINIO_SISTEMA = \'[^\']*\';/',
                '$DOMINIO_SISTEMA = \'' . addslashes($novo_dominio) . '\';',
                $conteudo
            );
            
            if (file_put_contents($arquivo_config, $conteudo)) {
                $mensagem = '<div class="alert alert-success">Domínio atualizado com sucesso!</div>';
            } else {
                $mensagem = '<div class="alert alert-danger">Erro ao atualizar arquivo de configuração.</div>';
            }
        }
    }
}

// Buscar domínio atual
$arquivo_config = __DIR__ . '/config/dominio.php';
$conteudo = file_get_contents($arquivo_config);
preg_match('/\$DOMINIO_SISTEMA = \'([^\']*)\';/', $conteudo, $matches);
$dominio_atual = $matches[1] ?? 'presenca.aom.org.br';

// Buscar IP do primeiro dispositivo de culto ativo para exemplos
$stmt = $conn->prepare("SELECT ip FROM dispositivos_faciais WHERE tipo_dispositivo = 'culto' AND ativo = 1 LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$ip_exemplo = $result->fetch_assoc()['ip'] ?? '10.144.198.50';

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Domínio do Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-house-door me-2"></i>Sistema de Presença
            </a>
            <div class="navbar-nav ms-auto">
                <a href="painel/index.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Voltar ao Painel
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-globe me-2 text-primary"></i>Configurar Domínio do Sistema
                </h2>
            </div>
        </div>

        <?php echo $mensagem; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-gear me-2"></i>Configuração do Domínio
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="acao" value="atualizar_dominio">
                            
                            <div class="mb-3">
                                <label for="dominio" class="form-label">Domínio do Sistema</label>
                                <div class="input-group">
                                    <span class="input-group-text">https://</span>
                                    <input type="text" class="form-control" id="dominio" name="dominio" 
                                           value="<?= htmlspecialchars($dominio_atual) ?>" 
                                           placeholder="exemplo.com.br" required>
                                </div>
                                <div class="form-text">
                                    Digite apenas o domínio, sem http:// ou https://
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i>Salvar Domínio
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-info-circle me-2"></i>URLs das APIs
                        </h5>
                    </div>
                    <div class="card-body">
                        <h6>API de Leitura Facial:</h6>
                        <code class="d-block mb-3">https://<?= htmlspecialchars($dominio_atual) ?>/api/culto/receber_leitura_facial.php</code>
                        
                        <h6>API de Sincronização:</h6>
                        <code class="d-block mb-3">https://<?= htmlspecialchars($dominio_atual) ?>/api/culto/executar_sync.php</code>
                        
                        <div class="mt-3">
                            <a href="teste_configuracao_facial.php" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-play-circle me-1"></i>Testar Configuração
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exemplos de Configuração -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-terminal me-2"></i>Exemplos de Configuração
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Configuração via cURL:</h6>
                                <div class="bg-dark text-light p-3 rounded">
                                    <pre><code>curl -X POST https://<?= htmlspecialchars($dominio_atual) ?>/api/culto/receber_leitura_facial.php \
  -H "Content-Type: application/json" \
  -d '{
    "nome_usuario": "Tiago Linhares Tavares",
    "ip_dispositivo": "<?= $ip_exemplo ?>",
    "timestamp": 1726574400
  }'</code></pre>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Configuração no Dispositivo:</h6>
                                <div class="bg-dark text-light p-3 rounded">
                                    <pre><code>URL: https://<?= htmlspecialchars($dominio_atual) ?>/api/culto/receber_leitura_facial.php
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

        <!-- Informações Importantes -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>Informações Importantes
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-info-circle me-2"></i>Antes de alterar o domínio:</h6>
                            <ul class="mb-0">
                                <li>Certifique-se de que o novo domínio está funcionando</li>
                                <li>Verifique se o SSL está configurado corretamente</li>
                                <li>Teste as APIs após a alteração</li>
                                <li>Atualize as configurações dos dispositivos faciais</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
