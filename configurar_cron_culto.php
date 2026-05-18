<?php
/**
 * Script para configurar o cron de sincronização de culto
 * Este script deve ser executado uma vez para configurar o cron job
 */

header('Content-Type: text/html; charset=UTF-8');

$cron_job = "*/5 * * * * /usr/bin/php " . __DIR__ . "/cron/sincronizacao_culto_automatica.php";
$cron_file = "/tmp/cron_culto_" . date('Y-m-d_H-i-s');

echo "<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Configuração de Cron - Culto</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
<div class='container mt-5'>
    <div class='row justify-content-center'>
        <div class='col-md-8'>
            <div class='card'>
                <div class='card-header bg-success text-white'>
                    <h4 class='mb-0'>
                        <i class='bi bi-gear-fill me-2'></i>
                        Configuração de Cron - Sincronização de Culto
                    </h4>
                </div>
                <div class='card-body'>";

try {
    // Verificar se o comando crontab está disponível
    $crontab_available = shell_exec('which crontab');
    if (empty($crontab_available)) {
        throw new Exception("Comando crontab não encontrado. Certifique-se de que o cron está instalado.");
    }
    
    // Obter crontab atual
    $current_crontab = shell_exec('crontab -l 2>/dev/null');
    if ($current_crontab === null) {
        $current_crontab = "";
    }
    
    // Verificar se o cron job já existe
    if (strpos($current_crontab, 'sincronizacao_culto_automatica.php') !== false) {
        echo "<div class='alert alert-warning'>
                <i class='bi bi-exclamation-triangle me-2'></i>
                <strong>Atenção:</strong> O cron job para sincronização de culto já está configurado!
              </div>";
    } else {
        // Adicionar o novo cron job
        $new_crontab = $current_crontab . "\n" . $cron_job . "\n";
        
        // Salvar em arquivo temporário
        file_put_contents($cron_file, $new_crontab);
        
        // Aplicar o novo crontab
        $result = shell_exec("crontab $cron_file 2>&1");
        
        if ($result === null) {
            echo "<div class='alert alert-success'>
                    <i class='bi bi-check-circle me-2'></i>
                    <strong>Sucesso!</strong> Cron job configurado com sucesso!
                  </div>";
            
            echo "<div class='card mt-3'>
                    <div class='card-header'>
                        <h6 class='mb-0'>Configuração Aplicada</h6>
                    </div>
                    <div class='card-body'>
                        <code>$cron_job</code>
                        <p class='mt-2 text-muted'>
                            Este cron job será executado a cada 5 minutos para manter os usuários do culto sincronizados.
                        </p>
                    </div>
                  </div>";
        } else {
            throw new Exception("Erro ao configurar crontab: $result");
        }
        
        // Limpar arquivo temporário
        unlink($cron_file);
    }
    
    // Mostrar crontab atual
    echo "<div class='card mt-3'>
            <div class='card-header'>
                <h6 class='mb-0'>Crontab Atual</h6>
            </div>
            <div class='card-body'>
                <pre class='bg-light p-3'><code>" . htmlspecialchars($current_crontab) . "</code></pre>
            </div>
          </div>";
    
    // Instruções
    echo "<div class='card mt-3'>
            <div class='card-header'>
                <h6 class='mb-0'>Instruções</h6>
            </div>
            <div class='card-body'>
                <ul class='mb-0'>
                    <li>O cron job será executado automaticamente a cada 5 minutos</li>
                    <li>Logs são salvos em: <code>logs/cron_culto_YYYY-MM-DD.log</code></li>
                    <li>Para monitorar, acesse: <a href='painel/monitor_culto.php'>Monitor Culto</a></li>
                    <li>Para remover o cron: <code>crontab -e</code> e delete a linha correspondente</li>
                </ul>
            </div>
          </div>";
    
    // Testar execução
    echo "<div class='card mt-3'>
            <div class='card-header'>
                <h6 class='mb-0'>Teste de Execução</h6>
            </div>
            <div class='card-body'>
                <p>Para testar se o script está funcionando corretamente:</p>
                <button class='btn btn-primary' onclick='testarExecucao()'>
                    <i class='bi bi-play-circle me-1'></i>Testar Execução
                </button>
                <div id='resultadoTeste' class='mt-3'></div>
            </div>
          </div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
            <i class='bi bi-x-circle me-2'></i>
            <strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "
          </div>";
}

echo "                </div>
            </div>
        </div>
    </div>
</div>

<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
<script>
function testarExecucao() {
    const resultado = document.getElementById('resultadoTeste');
    resultado.innerHTML = '<div class=\"spinner-border spinner-border-sm me-2\"></div>Testando execução...';
    
    fetch('cron/sincronizacao_culto_automatica.php')
        .then(response => response.text())
        .then(data => {
            resultado.innerHTML = '<div class=\"alert alert-success\"><strong>Sucesso!</strong> Script executado com sucesso.</div>';
        })
        .catch(error => {
            resultado.innerHTML = '<div class=\"alert alert-danger\"><strong>Erro:</strong> ' + error.message + '</div>';
        });
}
</script>
</body>
</html>";
?>